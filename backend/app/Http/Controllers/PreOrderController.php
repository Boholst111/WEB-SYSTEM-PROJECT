<?php

namespace App\Http\Controllers;

use App\Models\PreOrder;
use App\Models\Product;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PreOrderController extends Controller
{
    /**
     * Display a listing of the user's pre-orders.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $query = PreOrder::with(['product', 'product.brand', 'product.category'])
            ->byUser($user->id);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        // Filter by product if provided
        if ($request->has('product_id')) {
            $query->byProduct($request->product_id);
        }

        // Sort by created date (newest first) by default
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min($request->get('per_page', 15), 50);
        $preorders = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $preorders->items(),
            'pagination' => [
                'current_page' => $preorders->currentPage(),
                'last_page' => $preorders->lastPage(),
                'per_page' => $preorders->perPage(),
                'total' => $preorders->total(),
            ]
        ]);
    }

    /**
     * Store a newly created pre-order.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1|max:10',
            'deposit_percentage' => 'sometimes|numeric|min:0.1|max:1.0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $product = Product::findOrFail($request->product_id);

        // Check if product is available for pre-order
        if (!$product->is_preorder) {
            return response()->json([
                'success' => false,
                'message' => 'This product is not available for pre-order'
            ], 400);
        }

        // Check if user already has a pre-order for this product
        $existingPreOrder = PreOrder::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->whereIn('status', ['deposit_pending', 'deposit_paid', 'ready_for_payment'])
            ->first();

        if ($existingPreOrder) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an active pre-order for this product'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $preorder = new PreOrder([
                'preorder_number' => PreOrder::generatePreOrderNumber(),
                'user_id' => $user->id,
                'product_id' => $product->id,
                'quantity' => $request->quantity,
                'estimated_arrival_date' => $product->preorder_date,
                'status' => 'deposit_pending',
            ]);

            // Calculate deposit and remaining amounts
            $depositPercentage = $request->get('deposit_percentage', 0.3); // Default 30%
            $preorder->calculateAmounts($depositPercentage);
            
            $preorder->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pre-order created successfully',
                'data' => $preorder->load(['product', 'product.brand', 'product.category'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create pre-order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified pre-order.
     */
    public function show(PreOrder $preorder): JsonResponse
    {
        $user = Auth::user();

        // Check if user owns this pre-order
        if ($preorder->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to pre-order'
            ], 403);
        }

        $preorder->load(['product', 'product.brand', 'product.category', 'loyaltyTransactions']);

        return response()->json([
            'success' => true,
            'data' => $preorder
        ]);
    }

    /**
     * Process deposit payment for pre-order.
     */
    public function payDeposit(Request $request, PreOrder $preorder): JsonResponse
    {
        $user = Auth::user();

        // Check if user owns this pre-order
        if ($preorder->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to pre-order'
            ], 403);
        }

        // Check if deposit can be paid
        if ($preorder->status !== 'deposit_pending') {
            return response()->json([
                'success' => false,
                'message' => 'Deposit has already been paid or pre-order is not in valid state'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|in:gcash,maya,bank_transfer',
            'gateway_data' => 'sometimes|array',
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
            // Create payment record
            $payment = new Payment([
                'payment_id' => 'PAY_' . uniqid(),
                'preorder_id' => $preorder->id,
                'user_id' => $user->id,
                'payment_method' => $request->payment_method,
                'payment_type' => 'deposit',
                'amount' => $preorder->deposit_amount,
                'currency' => 'PHP',
                'status' => Payment::STATUS_PENDING,
            ]);

            // Process payment through gateway (simplified for this implementation)
            $gatewayResponse = $this->processPaymentGateway(
                $request->payment_method,
                $preorder->deposit_amount,
                $request->get('gateway_data', [])
            );

            if ($gatewayResponse['success']) {
                $payment->gateway_transaction_id = $gatewayResponse['transaction_id'];
                $payment->gateway_response = $gatewayResponse;
                $payment->status = Payment::STATUS_COMPLETED;
                $payment->paid_at = now();
                $payment->save();

                // Update pre-order status
                $preorder->processDepositPayment($request->payment_method);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Deposit payment processed successfully',
                    'data' => [
                        'preorder' => $preorder->fresh()->load(['product']),
                        'payment' => $payment
                    ]
                ]);
            } else {
                $payment->status = Payment::STATUS_FAILED;
                $payment->failed_at = now();
                $payment->failure_reason = $gatewayResponse['error'];
                $payment->gateway_response = $gatewayResponse;
                $payment->save();

                DB::commit();

                return response()->json([
                    'success' => false,
                    'message' => 'Payment failed: ' . $gatewayResponse['error'],
                    'data' => ['payment' => $payment]
                ], 400);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process deposit payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete final payment for pre-order.
     */
    public function completePayment(Request $request, PreOrder $preorder): JsonResponse
    {
        $user = Auth::user();

        // Check if user owns this pre-order
        if ($preorder->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to pre-order'
            ], 403);
        }

        // Check if final payment can be made
        if ($preorder->status !== 'ready_for_payment') {
            return response()->json([
                'success' => false,
                'message' => 'Pre-order is not ready for final payment'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|in:gcash,maya,bank_transfer',
            'gateway_data' => 'sometimes|array',
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
            // Create payment record for remaining amount
            $payment = new Payment([
                'payment_id' => 'PAY_' . uniqid(),
                'preorder_id' => $preorder->id,
                'user_id' => $user->id,
                'payment_method' => $request->payment_method,
                'payment_type' => 'remaining_payment',
                'amount' => $preorder->remaining_amount,
                'currency' => 'PHP',
                'status' => Payment::STATUS_PENDING,
            ]);

            // Process payment through gateway
            $gatewayResponse = $this->processPaymentGateway(
                $request->payment_method,
                $preorder->remaining_amount,
                $request->get('gateway_data', [])
            );

            if ($gatewayResponse['success']) {
                $payment->gateway_transaction_id = $gatewayResponse['transaction_id'];
                $payment->gateway_response = $gatewayResponse;
                $payment->status = Payment::STATUS_COMPLETED;
                $payment->paid_at = now();
                $payment->save();

                // Complete pre-order
                $preorder->completePayment();

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Final payment completed successfully',
                    'data' => [
                        'preorder' => $preorder->fresh()->load(['product']),
                        'payment' => $payment
                    ]
                ]);
            } else {
                $payment->status = Payment::STATUS_FAILED;
                $payment->failed_at = now();
                $payment->failure_reason = $gatewayResponse['error'];
                $payment->gateway_response = $gatewayResponse;
                $payment->save();

                DB::commit();

                return response()->json([
                    'success' => false,
                    'message' => 'Payment failed: ' . $gatewayResponse['error'],
                    'data' => ['payment' => $payment]
                ], 400);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete final payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pre-order status and details.
     */
    public function status(PreOrder $preorder): JsonResponse
    {
        $user = Auth::user();

        // Check if user owns this pre-order
        if ($preorder->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to pre-order'
            ], 403);
        }

        $preorder->load(['product']);

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $preorder->status,
                'status_label' => $preorder->status_label,
                'deposit_paid' => $preorder->isDepositPaid(),
                'payment_completed' => $preorder->isPaymentCompleted(),
                'payment_overdue' => $preorder->isPaymentOverdue(),
                'can_be_cancelled' => $preorder->canBeCancelled(),
                'days_until_due' => $preorder->days_until_due,
                'amounts' => [
                    'deposit' => $preorder->formatted_deposit,
                    'remaining' => $preorder->formatted_remaining,
                    'total' => $preorder->formatted_total,
                ],
                'dates' => [
                    'estimated_arrival' => $preorder->estimated_arrival_date,
                    'actual_arrival' => $preorder->actual_arrival_date,
                    'deposit_paid_at' => $preorder->deposit_paid_at,
                    'full_payment_due_date' => $preorder->full_payment_due_date,
                ]
            ]
        ]);
    }

    /**
     * Get pre-order notifications.
     */
    public function notifications(PreOrder $preorder): JsonResponse
    {
        $user = Auth::user();

        // Check if user owns this pre-order
        if ($preorder->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to pre-order'
            ], 403);
        }

        // Get notifications related to this pre-order
        // This would typically come from a notifications table
        // For now, we'll return status-based notifications
        $notifications = [];

        if ($preorder->status === 'deposit_pending') {
            $notifications[] = [
                'type' => 'deposit_reminder',
                'title' => 'Deposit Payment Required',
                'message' => 'Please complete your deposit payment to secure your pre-order.',
                'created_at' => $preorder->created_at,
                'read' => false
            ];
        }

        if ($preorder->status === 'ready_for_payment') {
            $notifications[] = [
                'type' => 'payment_ready',
                'title' => 'Final Payment Ready',
                'message' => 'Your pre-ordered item has arrived! Complete your final payment to receive it.',
                'created_at' => $preorder->actual_arrival_date ?? now(),
                'read' => false
            ];
        }

        if ($preorder->isPaymentOverdue()) {
            $notifications[] = [
                'type' => 'payment_overdue',
                'title' => 'Payment Overdue',
                'message' => 'Your final payment is overdue. Please complete payment to avoid cancellation.',
                'created_at' => $preorder->full_payment_due_date,
                'read' => false
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $notifications
        ]);
    }

    /**
     * Process payment through gateway (simplified implementation).
     */
    private function processPaymentGateway(string $method, float $amount, array $gatewayData): array
    {
        // This is a simplified implementation
        // In a real application, you would integrate with actual payment gateways
        
        try {
            switch ($method) {
                case 'gcash':
                    return $this->processGCashPayment($amount, $gatewayData);
                case 'maya':
                    return $this->processMayaPayment($amount, $gatewayData);
                case 'bank_transfer':
                    return $this->processBankTransferPayment($amount, $gatewayData);
                default:
                    return [
                        'success' => false,
                        'error' => 'Unsupported payment method'
                    ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process GCash payment (mock implementation).
     */
    private function processGCashPayment(float $amount, array $gatewayData): array
    {
        // Mock successful payment
        return [
            'success' => true,
            'transaction_id' => 'GCASH_' . uniqid(),
            'gateway_response' => [
                'status' => 'success',
                'amount' => $amount,
                'currency' => 'PHP',
                'timestamp' => now()->toISOString()
            ]
        ];
    }

    /**
     * Process Maya payment (mock implementation).
     */
    private function processMayaPayment(float $amount, array $gatewayData): array
    {
        // Mock successful payment
        return [
            'success' => true,
            'transaction_id' => 'MAYA_' . uniqid(),
            'gateway_response' => [
                'status' => 'success',
                'amount' => $amount,
                'currency' => 'PHP',
                'timestamp' => now()->toISOString()
            ]
        ];
    }

    /**
     * Process bank transfer payment (mock implementation).
     */
    private function processBankTransferPayment(float $amount, array $gatewayData): array
    {
        // Mock successful payment
        return [
            'success' => true,
            'transaction_id' => 'BANK_' . uniqid(),
            'gateway_response' => [
                'status' => 'success',
                'amount' => $amount,
                'currency' => 'PHP',
                'timestamp' => now()->toISOString()
            ]
        ];
    }
}