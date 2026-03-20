<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PreOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class PreOrderController extends Controller
{
    /**
     * Display a listing of all pre-orders for admin.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PreOrder::with(['user', 'product', 'product.brand', 'product.category']);

        // Filter by status
        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        // Filter by product
        if ($request->has('product_id')) {
            $query->byProduct($request->product_id);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->byUser($request->user_id);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Filter by arrival status
        if ($request->has('arrival_status')) {
            switch ($request->arrival_status) {
                case 'arrived':
                    $query->whereNotNull('actual_arrival_date');
                    break;
                case 'pending':
                    $query->whereNull('actual_arrival_date')
                          ->where('estimated_arrival_date', '>', now());
                    break;
                case 'overdue':
                    $query->whereNull('actual_arrival_date')
                          ->where('estimated_arrival_date', '<', now());
                    break;
            }
        }

        // Search by pre-order number or user email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('preorder_number', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('email', 'like', "%{$search}%")
                               ->orWhere('first_name', 'like', "%{$search}%")
                               ->orWhere('last_name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('product', function ($productQuery) use ($search) {
                      $productQuery->where('name', 'like', "%{$search}%")
                                  ->orWhere('sku', 'like', "%{$search}%");
                  });
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min($request->get('per_page', 20), 100);
        $preorders = $query->paginate($perPage);

        // Add summary statistics
        $stats = [
            'total_preorders' => PreOrder::count(),
            'deposit_pending' => PreOrder::where('status', 'deposit_pending')->count(),
            'deposit_paid' => PreOrder::where('status', 'deposit_paid')->count(),
            'ready_for_payment' => PreOrder::where('status', 'ready_for_payment')->count(),
            'completed' => PreOrder::where('status', 'payment_completed')->count(),
            'cancelled' => PreOrder::where('status', 'cancelled')->count(),
            'total_value' => PreOrder::whereIn('status', ['deposit_paid', 'ready_for_payment', 'completed'])
                                   ->sum(DB::raw('deposit_amount + remaining_amount')),
            'pending_arrivals' => PreOrder::whereNull('actual_arrival_date')
                                        ->where('estimated_arrival_date', '>', now())
                                        ->count(),
            'overdue_arrivals' => PreOrder::whereNull('actual_arrival_date')
                                        ->where('estimated_arrival_date', '<', now())
                                        ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $preorders->items(),
            'pagination' => [
                'current_page' => $preorders->currentPage(),
                'last_page' => $preorders->lastPage(),
                'per_page' => $preorders->perPage(),
                'total' => $preorders->total(),
            ],
            'stats' => $stats
        ]);
    }

    /**
     * Display the specified pre-order for admin.
     */
    public function show(PreOrder $preorder): JsonResponse
    {
        $preorder->load([
            'user',
            'product',
            'product.brand',
            'product.category',
            'loyaltyTransactions'
        ]);

        // Get payment history
        $payments = $preorder->payments()->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'preorder' => $preorder,
                'payments' => $payments
            ]
        ]);
    }

    /**
     * Update pre-order arrival status.
     */
    public function updateArrival(Request $request, PreOrder $preorder): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'actual_arrival_date' => 'required|date',
            'notify_customers' => 'sometimes|boolean',
            'notes' => 'sometimes|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $preorder->actual_arrival_date = $request->actual_arrival_date;
            
            if ($request->has('notes')) {
                $preorder->notes = $request->notes;
            }

            // If deposit is paid, mark as ready for payment
            if ($preorder->status === 'deposit_paid') {
                $preorder->markReadyForPayment();
            }

            $preorder->save();

            // Send notification to customer if requested
            if ($request->get('notify_customers', true)) {
                $this->sendArrivalNotification($preorder);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pre-order arrival updated successfully',
                'data' => $preorder->fresh()->load(['user', 'product'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update pre-order arrival',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update pre-order status.
     */
    public function updateStatus(Request $request, PreOrder $preorder): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:deposit_pending,deposit_paid,ready_for_payment,payment_completed,shipped,delivered,cancelled,expired',
            'reason' => 'sometimes|string|max:500',
            'notify_customer' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $oldStatus = $preorder->status;
        $newStatus = $request->status;

        // Validate status transition
        if (!$this->isValidStatusTransition($oldStatus, $newStatus)) {
            return response()->json([
                'success' => false,
                'message' => "Invalid status transition from {$oldStatus} to {$newStatus}"
            ], 400);
        }

        DB::beginTransaction();
        try {
            $preorder->updateStatus($newStatus);
            
            if ($request->has('reason')) {
                $preorder->notes = ($preorder->notes ? $preorder->notes . "\n\n" : '') . 
                                  "Status changed to {$newStatus}: " . $request->reason;
                $preorder->save();
            }

            // Send notification to customer if requested
            if ($request->get('notify_customer', true)) {
                $this->sendStatusChangeNotification($preorder, $oldStatus, $newStatus);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pre-order status updated successfully',
                'data' => $preorder->fresh()->load(['user', 'product'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update pre-order status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notify customers about pre-order updates.
     */
    public function notifyCustomers(Request $request, PreOrder $preorder): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notification_type' => 'required|in:arrival,payment_reminder,status_update,custom',
            'message' => 'required_if:notification_type,custom|string|max:1000',
            'subject' => 'sometimes|string|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $notificationType = $request->notification_type;
            
            switch ($notificationType) {
                case 'arrival':
                    $this->sendArrivalNotification($preorder);
                    break;
                case 'payment_reminder':
                    $this->sendPaymentReminder($preorder);
                    break;
                case 'status_update':
                    $this->sendStatusUpdateNotification($preorder);
                    break;
                case 'custom':
                    $this->sendCustomNotification($preorder, $request->message, $request->subject);
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification sent successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update pre-orders.
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'preorder_ids' => 'required|array|min:1',
            'preorder_ids.*' => 'exists:preorders,id',
            'action' => 'required|in:update_status,mark_arrived,send_notification,cancel',
            'status' => 'required_if:action,update_status|in:deposit_pending,deposit_paid,ready_for_payment,payment_completed,shipped,delivered,cancelled,expired',
            'arrival_date' => 'required_if:action,mark_arrived|date',
            'notification_type' => 'required_if:action,send_notification|in:arrival,payment_reminder,status_update',
            'reason' => 'sometimes|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $preorderIds = $request->preorder_ids;
        $action = $request->action;
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        DB::beginTransaction();
        try {
            foreach ($preorderIds as $preorderId) {
                try {
                    $preorder = PreOrder::findOrFail($preorderId);
                    
                    switch ($action) {
                        case 'update_status':
                            if ($this->isValidStatusTransition($preorder->status, $request->status)) {
                                $preorder->updateStatus($request->status);
                                $results['success']++;
                            } else {
                                $results['failed']++;
                                $results['errors'][] = "Invalid status transition for pre-order {$preorder->preorder_number}";
                            }
                            break;
                            
                        case 'mark_arrived':
                            $preorder->actual_arrival_date = $request->arrival_date;
                            if ($preorder->status === 'deposit_paid') {
                                $preorder->markReadyForPayment();
                            }
                            $preorder->save();
                            $results['success']++;
                            break;
                            
                        case 'send_notification':
                            $this->sendNotificationByType($preorder, $request->notification_type);
                            $results['success']++;
                            break;
                            
                        case 'cancel':
                            if ($preorder->canBeCancelled()) {
                                $preorder->updateStatus('cancelled');
                                $results['success']++;
                            } else {
                                $results['failed']++;
                                $results['errors'][] = "Cannot cancel pre-order {$preorder->preorder_number}";
                            }
                            break;
                    }
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = "Error processing pre-order {$preorderId}: " . $e->getMessage();
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Bulk update completed. {$results['success']} successful, {$results['failed']} failed.",
                'data' => $results
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Bulk update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pre-order analytics and reports.
     */
    public function analytics(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', now()->subMonth()->toDateString());
        $dateTo = $request->get('date_to', now()->toDateString());

        // Pre-order statistics
        $stats = [
            'total_preorders' => PreOrder::whereBetween('created_at', [$dateFrom, $dateTo])->count(),
            'total_value' => PreOrder::whereBetween('created_at', [$dateFrom, $dateTo])
                                   ->sum(DB::raw('deposit_amount + remaining_amount')),
            'average_order_value' => PreOrder::whereBetween('created_at', [$dateFrom, $dateTo])
                                           ->avg(DB::raw('deposit_amount + remaining_amount')),
            'conversion_rate' => $this->calculateConversionRate($dateFrom, $dateTo),
        ];

        // Status breakdown
        $statusBreakdown = PreOrder::whereBetween('created_at', [$dateFrom, $dateTo])
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        // Top products
        $topProducts = PreOrder::with('product')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->select('product_id', DB::raw('count(*) as preorder_count'), DB::raw('sum(deposit_amount + remaining_amount) as total_value'))
            ->groupBy('product_id')
            ->orderBy('preorder_count', 'desc')
            ->limit(10)
            ->get();

        // Monthly trends
        $monthlyTrends = PreOrder::whereBetween('created_at', [$dateFrom, $dateTo])
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('count(*) as count'),
                DB::raw('sum(deposit_amount + remaining_amount) as value')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'status_breakdown' => $statusBreakdown,
                'top_products' => $topProducts,
                'monthly_trends' => $monthlyTrends,
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]
        ]);
    }

    /**
     * Check if status transition is valid.
     */
    private function isValidStatusTransition(string $from, string $to): bool
    {
        $validTransitions = [
            'deposit_pending' => ['deposit_paid', 'cancelled', 'expired'],
            'deposit_paid' => ['ready_for_payment', 'cancelled'],
            'ready_for_payment' => ['payment_completed', 'cancelled', 'expired'],
            'payment_completed' => ['shipped', 'cancelled'],
            'shipped' => ['delivered'],
            'delivered' => [], // No transitions from delivered
            'cancelled' => [], // No transitions from cancelled
            'expired' => [], // No transitions from expired
        ];

        return in_array($to, $validTransitions[$from] ?? []);
    }

    /**
     * Calculate conversion rate from deposit_pending to completed.
     */
    private function calculateConversionRate(string $dateFrom, string $dateTo): float
    {
        $totalPreorders = PreOrder::whereBetween('created_at', [$dateFrom, $dateTo])->count();
        $completedPreorders = PreOrder::whereBetween('created_at', [$dateFrom, $dateTo])
                                    ->where('status', 'payment_completed')
                                    ->count();

        return $totalPreorders > 0 ? ($completedPreorders / $totalPreorders) * 100 : 0;
    }

    /**
     * Send arrival notification to customer.
     */
    private function sendArrivalNotification(PreOrder $preorder): void
    {
        // This would integrate with your notification system
        // For now, we'll just call the model method
        $preorder->sendArrivalNotification();
    }

    /**
     * Send payment reminder to customer.
     */
    private function sendPaymentReminder(PreOrder $preorder): void
    {
        $preorder->sendPaymentReminder();
    }

    /**
     * Send status change notification.
     */
    private function sendStatusChangeNotification(PreOrder $preorder, string $oldStatus, string $newStatus): void
    {
        // Implementation would depend on your notification system
        // This is a placeholder for the actual notification logic
    }

    /**
     * Send status update notification.
     */
    private function sendStatusUpdateNotification(PreOrder $preorder): void
    {
        // Implementation for status update notifications
    }

    /**
     * Send custom notification.
     */
    private function sendCustomNotification(PreOrder $preorder, string $message, ?string $subject = null): void
    {
        // Implementation for custom notifications
    }

    /**
     * Send notification by type.
     */
    private function sendNotificationByType(PreOrder $preorder, string $type): void
    {
        switch ($type) {
            case 'arrival':
                $this->sendArrivalNotification($preorder);
                break;
            case 'payment_reminder':
                $this->sendPaymentReminder($preorder);
                break;
            case 'status_update':
                $this->sendStatusUpdateNotification($preorder);
                break;
        }
    }
}