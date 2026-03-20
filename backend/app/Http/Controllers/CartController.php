<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ShoppingCart;
use App\Services\CartService;
use App\Services\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    protected ShippingService $shippingService;
    protected CartService $cartService;

    public function __construct(ShippingService $shippingService, CartService $cartService)
    {
        $this->shippingService = $shippingService;
        $this->cartService = $cartService;
    }

    /**
     * Get user's cart with calculations
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $cartItems = ShoppingCart::with(['product.brand', 'product.category'])
            ->where('user_id', $user->id)
            ->get();

        // Calculate totals
        $subtotal = $cartItems->sum(function ($item) {
            return $item->price * $item->quantity;
        });

        // Get loyalty credits available
        $availableCredits = $user->loyalty_credits;
        $maxCreditsUsable = $this->calculateMaxCreditsUsable($subtotal);

        // Calculate shipping options
        $shippingOptions = $this->getShippingOptions($subtotal, $user);

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $cartItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product' => [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'sku' => $item->product->sku,
                            'brand' => $item->product->brand->name ?? null,
                            'category' => $item->product->category->name ?? null,
                            'main_image' => $item->product->main_image,
                            'current_price' => $item->product->current_price,
                            'stock_quantity' => $item->product->stock_quantity,
                            'is_available' => $item->product->isAvailable(),
                        ],
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'total' => $item->total,
                        'formatted_total' => $item->formatted_total,
                    ];
                }),
                'summary' => [
                    'subtotal' => $subtotal,
                    'formatted_subtotal' => '₱' . number_format($subtotal, 2),
                    'items_count' => $cartItems->count(),
                    'total_quantity' => $cartItems->sum('quantity'),
                ],
                'loyalty' => [
                    'available_credits' => $availableCredits,
                    'max_credits_usable' => $maxCreditsUsable,
                    'formatted_available' => '₱' . number_format($availableCredits, 2),
                    'formatted_max_usable' => '₱' . number_format($maxCreditsUsable, 2),
                ],
                'shipping_options' => $shippingOptions,
            ],
        ]);
    }

    /**
     * Add item to cart with inventory validation
     */
    public function addItem(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $productId = $request->product_id;
        $quantity = $request->quantity;

        // Get product and validate inventory
        $product = Product::findOrFail($productId);

        if (!$product->isAvailable()) {
            return response()->json([
                'success' => false,
                'message' => 'Product is not available for purchase',
            ], 400);
        }

        // Check if product is in stock
        if ($product->stock_quantity < $quantity) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient stock available',
                'available_quantity' => $product->stock_quantity,
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Check if item already exists in cart
            $cartItem = ShoppingCart::where('user_id', $user->id)
                ->where('product_id', $productId)
                ->first();

            if ($cartItem) {
                // Update quantity
                $newQuantity = $cartItem->quantity + $quantity;
                
                // Validate total quantity against stock
                if ($product->stock_quantity < $newQuantity) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot add more items. Insufficient stock available',
                        'available_quantity' => $product->stock_quantity,
                        'current_cart_quantity' => $cartItem->quantity,
                    ], 400);
                }

                $cartItem->quantity = $newQuantity;
                $cartItem->price = $product->current_price; // Update to current price
                $cartItem->save();
            } else {
                // Create new cart item
                $cartItem = ShoppingCart::create([
                    'user_id' => $user->id,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'price' => $product->current_price,
                ]);
            }

            DB::commit();

            // Load relationships
            $cartItem->load(['product.brand', 'product.category']);

            return response()->json([
                'success' => true,
                'message' => 'Item added to cart successfully',
                'data' => [
                    'id' => $cartItem->id,
                    'product_id' => $cartItem->product_id,
                    'quantity' => $cartItem->quantity,
                    'price' => $cartItem->price,
                    'total' => $cartItem->total,
                    'product' => [
                        'id' => $cartItem->product->id,
                        'name' => $cartItem->product->name,
                        'sku' => $cartItem->product->sku,
                        'main_image' => $cartItem->product->main_image,
                    ],
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to add item to cart',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update cart item quantity
     */
    public function updateItem(Request $request, int $itemId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $quantity = $request->quantity;

        $cartItem = ShoppingCart::where('id', $itemId)
            ->where('user_id', $user->id)
            ->first();

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found',
            ], 404);
        }

        // Validate inventory
        $product = $cartItem->product;

        if (!$product->isAvailable()) {
            return response()->json([
                'success' => false,
                'message' => 'Product is no longer available',
            ], 400);
        }

        if ($product->stock_quantity < $quantity) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient stock available',
                'available_quantity' => $product->stock_quantity,
            ], 400);
        }

        DB::beginTransaction();
        try {
            $cartItem->quantity = $quantity;
            $cartItem->price = $product->current_price; // Update to current price
            $cartItem->save();

            DB::commit();

            $cartItem->load(['product.brand', 'product.category']);

            return response()->json([
                'success' => true,
                'message' => 'Cart item updated successfully',
                'data' => [
                    'id' => $cartItem->id,
                    'quantity' => $cartItem->quantity,
                    'price' => $cartItem->price,
                    'total' => $cartItem->total,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update cart item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove item from cart
     */
    public function removeItem(Request $request, int $itemId): JsonResponse
    {
        $user = $request->user();

        $cartItem = ShoppingCart::where('id', $itemId)
            ->where('user_id', $user->id)
            ->first();

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found',
            ], 404);
        }

        try {
            $cartItem->delete();

            return response()->json([
                'success' => true,
                'message' => 'Item removed from cart successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove item from cart',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear all items from cart
     */
    public function clear(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            ShoppingCart::where('user_id', $user->id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Cart cleared successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cart',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculate cart totals with shipping and credits
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
        $creditsToUse = $request->input('credits_to_use');
        $shippingOption = $request->input('shipping_option');

        // Validate cart has items
        $cartSummary = $this->cartService->getCartSummary($user);
        if ($cartSummary['items_count'] === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cart is empty',
            ], 400);
        }

        // Validate inventory
        $inventoryValidation = $this->cartService->validateCartInventory($user);
        if (!$inventoryValidation['valid']) {
            return response()->json([
                'success' => false,
                'message' => 'Some items in your cart are no longer available',
                'errors' => $inventoryValidation['errors'],
            ], 400);
        }

        // Calculate totals
        $totals = $this->cartService->calculateCartTotals($user, $creditsToUse, $shippingOption);

        return response()->json([
            'success' => true,
            'data' => $totals,
        ]);
    }

    /**
     * Validate cart inventory
     */
    public function validateInventory(Request $request): JsonResponse
    {
        $user = $request->user();
        $validation = $this->cartService->validateCartInventory($user);

        return response()->json([
            'success' => true,
            'data' => $validation,
        ]);
    }

    /**
     * Calculate maximum loyalty credits that can be used
     */
    private function calculateMaxCreditsUsable(float $subtotal): float
    {
        return $this->cartService->calculateMaxCreditsUsable($subtotal);
    }

    /**
     * Get available shipping options with costs
     */
    private function getShippingOptions(float $subtotal, $user): array
    {
        return $this->cartService->getShippingOptions($subtotal, $user);
    }
}
