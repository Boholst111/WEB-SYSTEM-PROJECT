<?php

namespace App\Http\Controllers;

use App\Services\Payment\PaymentService;
use App\Models\Order;
use App\Models\PreOrder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    private PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Process GCash payment.
     */
    public function processGCash(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'nullable|exists:orders,id',
            'preorder_id' => 'nullable|exists:preorders,id',
            'amount' => 'required|numeric|min:1',
            'success_url' => 'required|url',
            'failure_url' => 'required|url',
            'cancel_url' => 'required|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        $paymentData = [
            'gateway' => 'gcash',
            'order_id' => $request->order_id,
            'preorder_id' => $request->preorder_id,
            'amount' => $request->amount,
            'currency' => 'PHP',
            'reference_id' => $this->generateReferenceId($request),
            'description' => $this->getPaymentDescription($request),
            'customer_name' => $user->first_name . ' ' . $user->last_name,
            'customer_email' => $user->email,
            'customer_phone' => $user->phone,
            'success_url' => $request->success_url,
            'failure_url' => $request->failure_url,
            'cancel_url' => $request->cancel_url,
            'webhook_url' => route('payments.webhook.gcash'),
        ];

        $result = $this->paymentService->processPayment($paymentData);

        // Handle verification required response
        if (isset($result['verification_required']) && $result['verification_required']) {
            return response()->json($result, 200);
        }

        return response()->json($result, isset($result['success']) && $result['success'] ? 200 : 400);
    }

    /**
     * Process Maya payment.
     */
    public function processMaya(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'nullable|exists:orders,id',
            'preorder_id' => 'nullable|exists:preorders,id',
            'amount' => 'required|numeric|min:1',
            'success_url' => 'required|url',
            'failure_url' => 'required|url',
            'cancel_url' => 'required|url',
            'items' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        $paymentData = [
            'gateway' => 'maya',
            'order_id' => $request->order_id,
            'preorder_id' => $request->preorder_id,
            'amount' => $request->amount,
            'currency' => 'PHP',
            'reference_id' => $this->generateReferenceId($request),
            'description' => $this->getPaymentDescription($request),
            'customer_first_name' => $user->first_name,
            'customer_last_name' => $user->last_name,
            'customer_email' => $user->email,
            'customer_phone' => $user->phone,
            'success_url' => $request->success_url,
            'failure_url' => $request->failure_url,
            'cancel_url' => $request->cancel_url,
            'items' => $request->items,
        ];

        $result = $this->paymentService->processPayment($paymentData);

        // Handle verification required response
        if (isset($result['verification_required']) && $result['verification_required']) {
            return response()->json($result, 200);
        }

        return response()->json($result, isset($result['success']) && $result['success'] ? 200 : 400);
    }

    /**
     * Process bank transfer payment.
     */
    public function processBankTransfer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'nullable|exists:orders,id',
            'preorder_id' => 'nullable|exists:preorders,id',
            'amount' => 'required|numeric|min:1',
            'bank' => 'required|string|in:bpi,bdo,metrobank',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $paymentData = [
            'gateway' => 'bank_transfer',
            'order_id' => $request->order_id,
            'preorder_id' => $request->preorder_id,
            'amount' => $request->amount,
            'currency' => 'PHP',
            'reference_id' => $this->generateReferenceId($request),
            'description' => $this->getPaymentDescription($request),
            'bank' => $request->bank,
        ];

        $result = $this->paymentService->processPayment($paymentData);

        // Handle verification required response
        if (isset($result['verification_required']) && $result['verification_required']) {
            return response()->json($result, 200);
        }

        return response()->json($result, isset($result['success']) && $result['success'] ? 200 : 400);
    }

    /**
     * Get payment status.
     */
    public function status(Request $request, int $paymentId): JsonResponse
    {
        $result = $this->paymentService->verifyPayment($paymentId);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Verify payment manually.
     */
    public function verify(Request $request, int $paymentId): JsonResponse
    {
        $result = $this->paymentService->verifyPayment($paymentId);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Handle GCash webhook.
     */
    public function handleGCashWebhook(Request $request): JsonResponse
    {
        $result = $this->paymentService->handleWebhook('gcash', $request->all());

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Handle Maya webhook.
     */
    public function handleMayaWebhook(Request $request): JsonResponse
    {
        $result = $this->paymentService->handleWebhook('maya', $request->all());

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Handle bank transfer webhook (manual verification).
     */
    public function handleBankTransferWebhook(Request $request): JsonResponse
    {
        $result = $this->paymentService->handleWebhook('bank_transfer', $request->all());

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Get available payment methods.
     */
    public function getPaymentMethods(): JsonResponse
    {
        $methods = $this->paymentService->getAvailablePaymentMethods();

        return response()->json([
            'success' => true,
            'payment_methods' => $methods,
        ]);
    }

    /**
     * Refund a payment.
     */
    public function refund(Request $request, int $paymentId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'nullable|numeric|min:1',
            'reason' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->paymentService->refundPayment($paymentId, $request->amount);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Generate reference ID for payment.
     */
    private function generateReferenceId(Request $request): string
    {
        if ($request->order_id) {
            $order = Order::find($request->order_id);
            return $order ? $order->order_number : 'ORDER-' . $request->order_id;
        }

        if ($request->preorder_id) {
            return 'PREORDER-' . $request->preorder_id;
        }

        return 'PAYMENT-' . now()->format('YmdHis') . '-' . Auth::id();
    }

    /**
     * Get payment description.
     */
    private function getPaymentDescription(Request $request): string
    {
        if ($request->order_id) {
            return 'Diecast Empire Order Payment';
        }

        if ($request->preorder_id) {
            return 'Diecast Empire Pre-order Payment';
        }

        return 'Diecast Empire Payment';
    }
}