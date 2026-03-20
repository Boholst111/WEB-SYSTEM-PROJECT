<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ShoppingCart;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\CartService;
use App\Services\Payment\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class CheckoutService
{
    protected CartService $cartService;
    protected PaymentService $paymentService;

    public function __construct(CartService $cartService, PaymentService $paymentService)
    {
        $this->cartService = $cartService;
        $this->paymentService = $paymentService;
    }

    /**
     * Initialize checkout session with cart validation
     */
    public function initializeCheckout(User $user): array
    {
        try {
            // Validate cart has items
            $cartSummary = $this->cartService->getCartSummary($user);
            if ($cartSummary['items_count'] === 0) {
                return [
                    'success' => false,
                    'error' => 'Cart is empty',
                ];
            }

            // Validate inventory
            $inventoryValidation = $this->cartService->validateCartInventory($user);
            if (!$inventoryValidation['valid']) {
                return [
                    'success' => false,
                    'error' => 'Some items in your cart are no longer available',
                    'inventory_errors' => $inventoryValidation['errors'],
                ];
            }

            // Get user addresses
            $addresses = UserAddress::where('user_id', $user->id)->get();

            // Get available payment methods
            $paymentMethods = $this->paymentService->getAvailablePaymentMethods();

            // Get cart totals
            $cartTotals = $this->cartService->calculateCartTotals($user);

            return [
                'success' => true,
                'data' => [
                    'cart_summary' => $cartSummary,
                    'addresses' => $addresses,
                    'payment_methods' => $paymentMethods,
                    'totals' => $cartTotals,
                ],
            ];
        } catch (Exception $e) {
            Log::error('Checkout initialization failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to initialize checkout: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Validate and calculate checkout totals
     */
    public function calculateCheckoutTotals(User $user, array $checkoutData): array
    {
        try {
            $creditsToUse = $checkoutData['credits_to_use'] ?? null;
            $shippingOption = $checkoutData['shipping_option'] ?? null;

            // Validate cart
            $inventoryValidation = $this->cartService->validateCartInventory($user);
            if (!$inventoryValidation['valid']) {
                return [
                    'success' => false,
                    'error' => 'Some items in your cart are no longer available',
                    'inventory_errors' => $inventoryValidation['errors'],
                ];
            }

            // Calculate totals
            $totals = $this->cartService->calculateCartTotals($user, $creditsToUse, $shippingOption);

            return [
                'success' => true,
                'data' => $totals,
            ];
        } catch (Exception $e) {
            Log::error('Checkout totals calculation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to calculate totals: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Create order from cart with inventory reservation
     */
    public function createOrder(User $user, array $orderData): array
    {
        DB::beginTransaction();

        try {
            // Validate cart one more time
            $inventoryValidation = $this->cartService->validateCartInventory($user);
            if (!$inventoryValidation['valid']) {
                DB::rollBack();
                return [
                    'success' => false,
                    'error' => 'Some items in your cart are no longer available',
                    'inventory_errors' => $inventoryValidation['errors'],
                ];
            }

            // Get cart items
            $cartItems = ShoppingCart::with('product')
                ->where('user_id', $user->id)
                ->get();

            if ($cartItems->isEmpty()) {
                DB::rollBack();
                return [
                    'success' => false,
                    'error' => 'Cart is empty',
                ];
            }

            // Validate shipping address
            $shippingAddress = $this->validateShippingAddress($orderData['shipping_address_id'] ?? null, $user);
            if (!$shippingAddress) {
                DB::rollBack();
                return [
                    'success' => false,
                    'error' => 'Invalid shipping address',
                ];
            }

            // Calculate totals
            $creditsToUse = $orderData['credits_to_use'] ?? 0;
            $shippingOption = $orderData['shipping_option'] ?? null;
            $totals = $this->cartService->calculateCartTotals($user, $creditsToUse, $shippingOption);

            // Validate loyalty credits
            if ($creditsToUse > 0) {
                if ($creditsToUse > $user->loyalty_credits) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'error' => 'Insufficient loyalty credits',
                    ];
                }
            }

            // Reserve inventory
            $reservationResult = $this->reserveInventory($cartItems);
            if (!$reservationResult['success']) {
                DB::rollBack();
                return [
                    'success' => false,
                    'error' => 'Failed to reserve inventory',
                    'details' => $reservationResult['error'],
                ];
            }

            // Create order
            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'user_id' => $user->id,
                'status' => Order::STATUS_PENDING,
                'subtotal' => $totals['subtotal'],
                'credits_used' => $totals['credits_applied'],
                'discount_amount' => $totals['discount_amount'],
                'shipping_fee' => $totals['shipping_cost'],
                'total_amount' => $totals['total'],
                'payment_method' => $orderData['payment_method'] ?? null,
                'payment_status' => Order::PAYMENT_PENDING,
                'shipping_address' => $shippingAddress,
                'notes' => $orderData['notes'] ?? null,
            ]);

            // Create order items
            foreach ($cartItems as $cartItem) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $cartItem->product_id,
                    'product_sku' => $cartItem->product->sku,
                    'product_name' => $cartItem->product->name,
                    'quantity' => $cartItem->quantity,
                    'unit_price' => $cartItem->price,
                    'total_price' => $cartItem->price * $cartItem->quantity,
                ]);
            }

            // Deduct loyalty credits if used
            if ($creditsToUse > 0) {
                $this->deductLoyaltyCredits($user, $order, $creditsToUse);
            }

            // Clear cart
            ShoppingCart::where('user_id', $user->id)->delete();

            DB::commit();

            // Load relationships
            $order->load(['items.product', 'user']);

            return [
                'success' => true,
                'data' => [
                    'order' => $order,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'total_amount' => $order->total_amount,
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Order creation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // For debugging in tests
            if (app()->environment('testing')) {
                \Log::info('Order creation error details', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }

            return [
                'success' => false,
                'error' => 'Failed to create order: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Process payment for order
     */
    public function processOrderPayment(Order $order, array $paymentData): array
    {
        try {
            // Validate order status
            if ($order->payment_status !== Order::PAYMENT_PENDING) {
                return [
                    'success' => false,
                    'error' => 'Order payment has already been processed',
                ];
            }

            // Prepare payment data
            $paymentRequest = [
                'order_id' => $order->id,
                'gateway' => $paymentData['gateway'],
                'amount' => $order->total_amount,
                'currency' => 'PHP',
                'reference_id' => $order->order_number,
                'customer_info' => [
                    'name' => $order->user->first_name . ' ' . $order->user->last_name,
                    'email' => $order->user->email,
                    'phone' => $order->user->phone,
                ],
            ];

            // Process payment
            $paymentResult = $this->paymentService->processPayment($paymentRequest);

            if ($paymentResult['success']) {
                // Update order with payment info
                $order->update([
                    'payment_method' => $paymentData['gateway'],
                ]);

                return [
                    'success' => true,
                    'data' => [
                        'payment_id' => $paymentResult['payment_id'],
                        'transaction_id' => $paymentResult['transaction_id'],
                        'payment_url' => $paymentResult['payment_url'] ?? null,
                        'payment_instructions' => $paymentResult['payment_instructions'] ?? null,
                        'status' => $paymentResult['status'],
                    ],
                ];
            }

            return [
                'success' => false,
                'error' => $paymentResult['error'] ?? 'Payment processing failed',
            ];
        } catch (Exception $e) {
            Log::error('Order payment processing failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to process payment: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Reserve inventory for cart items
     */
    private function reserveInventory($cartItems): array
    {
        try {
            foreach ($cartItems as $item) {
                $product = $item->product;

                // Double-check inventory
                if ($product->stock_quantity < $item->quantity) {
                    return [
                        'success' => false,
                        'error' => "Insufficient stock for {$product->name}",
                    ];
                }

                // Decrement stock
                $product->decrement('stock_quantity', $item->quantity);
            }

            return ['success' => true];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate shipping address
     */
    private function validateShippingAddress($addressId, User $user): ?array
    {
        if ($addressId) {
            $address = UserAddress::where('id', $addressId)
                ->where('user_id', $user->id)
                ->first();

            if ($address) {
                return [
                    'first_name' => $address->first_name,
                    'last_name' => $address->last_name,
                    'company' => $address->company,
                    'address_line_1' => $address->address_line_1,
                    'address_line_2' => $address->address_line_2,
                    'city' => $address->city,
                    'province' => $address->province,
                    'postal_code' => $address->postal_code,
                    'country' => $address->country,
                    'phone' => $address->phone,
                ];
            }
        }

        return null;
    }

    /**
     * Deduct loyalty credits from user
     */
    private function deductLoyaltyCredits(User $user, Order $order, float $creditsToUse): void
    {
        $balanceBefore = $user->loyalty_credits;
        $user->decrement('loyalty_credits', $creditsToUse);

        // Create loyalty transaction record
        $user->loyaltyTransactions()->create([
            'order_id' => $order->id,
            'transaction_type' => 'redeemed',
            'amount' => -$creditsToUse,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceBefore - $creditsToUse,
            'description' => "Credits redeemed for order #{$order->order_number}",
        ]);
    }
}
