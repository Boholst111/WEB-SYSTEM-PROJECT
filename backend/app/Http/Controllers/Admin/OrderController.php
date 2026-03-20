<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderManagementService;
use App\Services\ShippingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    protected OrderManagementService $orderManagementService;
    protected ShippingService $shippingService;

    public function __construct(
        OrderManagementService $orderManagementService,
        ShippingService $shippingService
    ) {
        $this->orderManagementService = $orderManagementService;
        $this->shippingService = $shippingService;
    }

    /**
     * Display a listing of orders with advanced filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|string|in:pending,confirmed,processing,shipped,delivered,cancelled,refunded',
            'payment_status' => 'nullable|string|in:pending,paid,failed,refunded,partially_refunded',
            'search' => 'nullable|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'user_id' => 'nullable|integer|exists:users,id',
            'sort_by' => 'nullable|string|in:created_at,total_amount,status,payment_status',
            'sort_direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
            'include_items' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Order::with(['user', 'payment']);

        // Apply filters
        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        if ($request->filled('payment_status')) {
            $query->byPaymentStatus($request->payment_status);
        }

        if ($request->filled('user_id')) {
            $query->byUser($request->user_id);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->byDateRange($request->date_from, $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('first_name', 'like', "%{$search}%")
                               ->orWhere('last_name', 'like', "%{$search}%")
                               ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);

        // Include order items if requested
        if ($request->boolean('include_items')) {
            $query->with(['items.product']);
        }

        $perPage = $request->get('per_page', 20);
        $orders = $query->paginate($perPage);

        // Add summary statistics
        $summary = $this->getOrdersSummary($request);

        return response()->json([
            'success' => true,
            'data' => $orders,
            'summary' => $summary
        ]);
    }

    /**
     * Display the specified order with full details.
     */
    public function show(Order $order): JsonResponse
    {
        $order->load([
            'user',
            'items.product',
            'payment',
            'loyaltyTransactions'
        ]);

        // Add order timeline
        $timeline = $this->orderManagementService->getOrderTimeline($order);

        // Add shipping information if available
        $shippingInfo = null;
        if ($order->tracking_number) {
            $shippingInfo = $this->shippingService->getTrackingInfo($order->tracking_number);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order' => $order,
                'timeline' => $timeline,
                'shipping_info' => $shippingInfo,
                'can_cancel' => $order->canBeCancelled(),
                'can_refund' => $order->canBeRefunded(),
            ]
        ]);
    }

    /**
     * Update order status with validation and side effects.
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => [
                'required',
                'string',
                Rule::in(['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'])
            ],
            'tracking_number' => 'nullable|string|max:100',
            'courier_service' => 'nullable|string|max:100',
            'admin_notes' => 'nullable|string|max:1000',
            'notify_customer' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $result = $this->orderManagementService->updateOrderStatus(
                $order,
                $request->status,
                [
                    'tracking_number' => $request->tracking_number,
                    'courier_service' => $request->courier_service,
                    'admin_notes' => $request->admin_notes,
                    'notify_customer' => $request->boolean('notify_customer', true),
                    'admin_user_id' => auth()->id(),
                ]
            );

            if (!$result['success']) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 400);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully',
                'data' => $order->fresh(['user', 'items.product', 'payment'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update order status',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Bulk update multiple orders.
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_ids' => 'required|array|min:1|max:100',
            'order_ids.*' => 'integer|exists:orders,id',
            'action' => 'required|string|in:update_status,add_tracking,cancel,export',
            'status' => 'nullable|string|in:pending,confirmed,processing,shipped,delivered,cancelled,refunded',
            'tracking_numbers' => 'nullable|array',
            'tracking_numbers.*' => 'string|max:100',
            'courier_service' => 'nullable|string|max:100',
            'admin_notes' => 'nullable|string|max:1000',
            'notify_customers' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->orderManagementService->bulkUpdateOrders(
                $request->order_ids,
                $request->action,
                $request->except(['order_ids', 'action']) + ['admin_user_id' => auth()->id()]
            );

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'processed' => $result['processed'],
                    'failed' => $result['failed'],
                    'results' => $result['results']
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Bulk operation failed',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Handle payment exceptions and issues.
     */
    public function handlePaymentException(Request $request, Order $order): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|string|in:retry_payment,mark_paid,refund,cancel',
            'reason' => 'nullable|string|max:500',
            'refund_amount' => 'nullable|numeric|min:0',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->orderManagementService->handlePaymentException(
                $order,
                $request->action,
                $request->only(['reason', 'refund_amount', 'admin_notes']) + ['admin_user_id' => auth()->id()]
            );

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result['data'] ?? null
            ], $result['success'] ? 200 : 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to handle payment exception',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Handle inventory exceptions and issues.
     */
    public function handleInventoryException(Request $request, Order $order): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|string|in:substitute_product,partial_fulfillment,cancel_items,wait_restock',
            'items' => 'required|array',
            'items.*.order_item_id' => 'required|integer|exists:order_items,id',
            'items.*.substitute_product_id' => 'nullable|integer|exists:products,id',
            'items.*.new_quantity' => 'nullable|integer|min:0',
            'admin_notes' => 'nullable|string|max:1000',
            'notify_customer' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->orderManagementService->handleInventoryException(
                $order,
                $request->action,
                $request->items,
                $request->only(['admin_notes', 'notify_customer']) + ['admin_user_id' => auth()->id()]
            );

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result['data'] ?? null
            ], $result['success'] ? 200 : 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to handle inventory exception',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Generate shipping labels for orders.
     */
    public function generateShippingLabels(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_ids' => 'required|array|min:1|max:50',
            'order_ids.*' => 'integer|exists:orders,id',
            'courier_service' => 'required|string|in:lbc,jnt,ninjavan,2go',
            'service_type' => 'nullable|string|in:standard,express,same_day',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->shippingService->generateBulkLabels(
                $request->order_ids,
                $request->courier_service,
                $request->service_type ?? 'standard'
            );

            return response()->json([
                'success' => true,
                'message' => 'Shipping labels generated successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate shipping labels',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get order analytics and metrics.
     */
    public function analytics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'group_by' => 'nullable|string|in:day,week,month',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));
        $groupBy = $request->get('group_by', 'day');

        $analytics = $this->orderManagementService->getOrderAnalytics($dateFrom, $dateTo, $groupBy);

        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }

    /**
     * Export orders to various formats.
     */
    public function export(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'format' => 'required|string|in:csv,excel,pdf',
            'filters' => 'nullable|array',
            'columns' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->orderManagementService->exportOrders(
                $request->format,
                $request->get('filters', []),
                $request->get('columns', [])
            );

            return response()->json([
                'success' => true,
                'message' => 'Export generated successfully',
                'data' => [
                    'download_url' => $result['url'],
                    'filename' => $result['filename'],
                    'expires_at' => $result['expires_at']
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Export failed',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get orders summary statistics.
     */
    private function getOrdersSummary(Request $request): array
    {
        $query = Order::query();

        // Apply same filters as main query
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->byDateRange($request->date_from, $request->date_to);
        }

        return [
            'total_orders' => $query->count(),
            'total_revenue' => $query->sum('total_amount'),
            'status_breakdown' => $query->selectRaw('status, COUNT(*) as count')
                                       ->groupBy('status')
                                       ->pluck('count', 'status')
                                       ->toArray(),
            'payment_status_breakdown' => $query->selectRaw('payment_status, COUNT(*) as count')
                                               ->groupBy('payment_status')
                                               ->pluck('count', 'payment_status')
                                               ->toArray(),
            'average_order_value' => $query->avg('total_amount'),
        ];
    }
}