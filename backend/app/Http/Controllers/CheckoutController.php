<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\UserAddress;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CheckoutController extends Controller
{
    protected CheckoutService $checkoutService;

    public function __construct(CheckoutService $checkoutService)
    {
        $this->checkoutService = $checkoutService;
    }

    /**
     * Initialize checkout session
     */
    public function initialize(Request $request): JsonResponse
    {
        $user = $request->user();
        $result = $this->checkoutService->initializeCheckout($user);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
                'errors' => $result['inventory_errors'] ?? null,
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data'],
        ]);
    }

    /**
     * Calculate checkout totals
     */
    public function calculateTotals(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'credits_to_use' => 'nullable|numeric|min:0',
            'shipping_option' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $result = $this->checkoutService->calculateCheckoutTotals($user, $request->all());

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
                'errors' => $result['inventory_errors'] ?? null,
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data'],
        ]);
    }

    /**
     * Get user addresses
     */
    public function getAddresses(Request $request): JsonResponse
    {
        $user = $request->user();
        $addresses = UserAddress::where('user_id', $user->id)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $addresses,
        ]);
    }

    /**
     * Create new address
     */
    public function createAddress(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'nullable|string|in:shipping,billing',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'company' => 'nullable|string|max:100',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'province' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'country' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'is_default' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        // If setting as default, unset other defaults
        if ($request->is_default) {
            UserAddress::where('user_id', $user->id)
                ->update(['is_default' => false]);
        }

        $address = UserAddress::create([
            'user_id' => $user->id,
            'type' => $request->type ?? 'shipping',
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'company' => $request->company,
            'address_line_1' => $request->address_line_1,
            'address_line_2' => $request->address_line_2,
            'city' => $request->city,
            'province' => $request->province,
            'postal_code' => $request->postal_code,
            'country' => $request->country,
            'phone' => $request->phone,
            'is_default' => $request->is_default ?? false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Address created successfully',
            'data' => $address,
        ], 201);
    }

    /**
     * Update address
     */
    public function updateAddress(Request $request, int $addressId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'nullable|string|in:shipping,billing',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'company' => 'nullable|string|max:100',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'province' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'country' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'is_default' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $address = UserAddress::where('id', $addressId)
            ->where('user_id', $user->id)
            ->first();

        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'Address not found',
            ], 404);
        }

        // If setting as default, unset other defaults
        if ($request->is_default && !$address->is_default) {
            UserAddress::where('user_id', $user->id)
                ->where('id', '!=', $addressId)
                ->update(['is_default' => false]);
        }

        $address->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Address updated successfully',
            'data' => $address,
        ]);
    }

    /**
     * Delete address
     */
    public function deleteAddress(Request $request, int $addressId): JsonResponse
    {
        $user = $request->user();
        $address = UserAddress::where('id', $addressId)
            ->where('user_id', $user->id)
            ->first();

        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'Address not found',
            ], 404);
        }

        $address->delete();

        return response()->json([
            'success' => true,
            'message' => 'Address deleted successfully',
        ]);
    }

    /**
     * Create order from cart
     */
    public function createOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'shipping_address_id' => 'required|exists:user_addresses,id',
            'payment_method' => 'required|string|in:gcash,maya,bank_transfer',
            'shipping_option' => 'required|string',
            'credits_to_use' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $result = $this->checkoutService->createOrder($user, $request->all());

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
                'errors' => $result['inventory_errors'] ?? $result['details'] ?? null,
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully',
            'data' => $result['data'],
        ], 201);
    }

    /**
     * Process payment for order
     */
    public function processPayment(Request $request, int $orderId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'gateway' => 'required|string|in:gcash,maya,bank_transfer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $order = Order::where('id', $orderId)
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        $result = $this->checkoutService->processOrderPayment($order, $request->all());

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment initiated successfully',
            'data' => $result['data'],
        ]);
    }

    /**
     * Get order details
     */
    public function getOrder(Request $request, int $orderId): JsonResponse
    {
        $user = $request->user();
        $order = Order::with(['items.product', 'payment'])
            ->where('id', $orderId)
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order' => $order,
                'items' => $order->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product' => [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'sku' => $item->product->sku,
                            'main_image' => $item->product->main_image,
                        ],
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'subtotal' => $item->subtotal,
                    ];
                }),
                'summary' => [
                    'subtotal' => $order->subtotal,
                    'credits_used' => $order->credits_used,
                    'discount_amount' => $order->discount_amount,
                    'shipping_fee' => $order->shipping_fee,
                    'total_amount' => $order->total_amount,
                ],
            ],
        ]);
    }
}
