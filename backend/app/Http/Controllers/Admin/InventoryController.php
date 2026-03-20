<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\InventoryMovement;
use App\Models\PreOrder;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class InventoryController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Display inventory overview with real-time stock tracking.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['brand', 'category', 'inventoryMovements' => function ($q) {
            $q->latest()->limit(5);
        }]);

        // Filter by stock status
        if ($request->has('stock_status')) {
            switch ($request->stock_status) {
                case 'in_stock':
                    $query->where('stock_quantity', '>', 0)->where('is_preorder', false);
                    break;
                case 'low_stock':
                    $lowStockThreshold = $request->get('low_stock_threshold', 5);
                    $query->where('stock_quantity', '>', 0)
                          ->where('stock_quantity', '<=', $lowStockThreshold)
                          ->where('is_preorder', false);
                    break;
                case 'out_of_stock':
                    $query->where('stock_quantity', 0)->where('is_preorder', false);
                    break;
                case 'preorder':
                    $query->where('is_preorder', true);
                    break;
            }
        }

        // Filter by chase variants
        if ($request->has('chase_variants') && $request->chase_variants) {
            $query->where('is_chase_variant', true);
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by brand
        if ($request->has('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        // Search by name or SKU
        if ($request->has('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', '%' . $searchTerm . '%')
                  ->orWhere('sku', 'like', '%' . $searchTerm . '%');
            });
        }

        // Sort options
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        
        $allowedSorts = ['name', 'sku', 'stock_quantity', 'current_price', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $perPage = $request->get('per_page', 20);
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products,
            'summary' => [
                'total_products' => Product::count(),
                'in_stock' => Product::where('stock_quantity', '>', 0)->where('is_preorder', false)->count(),
                'low_stock' => Product::where('stock_quantity', '>', 0)
                    ->where('stock_quantity', '<=', $request->get('low_stock_threshold', 5))
                    ->where('is_preorder', false)->count(),
                'out_of_stock' => Product::where('stock_quantity', 0)->where('is_preorder', false)->count(),
                'preorders' => Product::where('is_preorder', true)->count(),
                'chase_variants' => Product::where('is_chase_variant', true)->count(),
            ]
        ]);
    }

    /**
     * Get low stock products with alerts.
     */
    public function lowStock(Request $request): JsonResponse
    {
        $threshold = $request->get('threshold', 5);
        
        $products = Product::with(['brand', 'category'])
            ->where('stock_quantity', '>', 0)
            ->where('stock_quantity', '<=', $threshold)
            ->where('is_preorder', false)
            ->where('status', 'active')
            ->orderBy('stock_quantity', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products,
            'threshold' => $threshold,
            'count' => $products->count()
        ]);
    }

    /**
     * Update product stock quantity.
     */
    public function updateStock(Request $request, Product $product): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer',
            'type' => 'required|in:restock,adjustment,damage,return',
            'reason' => 'required|string|max:255',
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
            $quantityBefore = $product->stock_quantity;
            $quantityChange = $request->quantity;
            
            // Calculate new quantity based on type
            switch ($request->type) {
                case 'restock':
                case 'return':
                    $newQuantity = $quantityBefore + $quantityChange;
                    break;
                case 'adjustment':
                    $newQuantity = $quantityChange; // Direct adjustment
                    $quantityChange = $newQuantity - $quantityBefore;
                    break;
                case 'damage':
                    $newQuantity = max(0, $quantityBefore - $quantityChange);
                    $quantityChange = -$quantityChange;
                    break;
                default:
                    throw new \Exception('Invalid stock update type');
            }

            // Update product stock
            $product->stock_quantity = $newQuantity;
            $product->save();

            // Create inventory movement record
            InventoryMovement::create([
                'product_id' => $product->id,
                'movement_type' => $request->type,
                'quantity_change' => $quantityChange,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $newQuantity,
                'reference_type' => 'manual_adjustment',
                'reference_id' => null,
                'reason' => $request->reason,
                'created_by' => auth()->id(),
            ]);

            // Check if product is now low stock and send alert
            if ($product->isLowStock()) {
                $this->notificationService->sendLowStockAlert($product);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock updated successfully',
                'data' => [
                    'product' => $product->fresh(['brand', 'category']),
                    'quantity_before' => $quantityBefore,
                    'quantity_after' => $newQuantity,
                    'quantity_change' => $quantityChange
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update stock: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inventory reports and analytics.
     */
    public function reports(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));

        // Stock movement summary
        $movements = InventoryMovement::whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('movement_type, COUNT(*) as count, SUM(ABS(quantity_change)) as total_quantity')
            ->groupBy('movement_type')
            ->get();

        // Top selling products (based on stock movements)
        $topSelling = InventoryMovement::with('product.brand')
            ->where('movement_type', 'sale')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('product_id, SUM(ABS(quantity_change)) as total_sold')
            ->groupBy('product_id')
            ->orderByDesc('total_sold')
            ->limit(10)
            ->get();

        // Slow moving products (no sales in period)
        $slowMoving = Product::with(['brand', 'category'])
            ->whereDoesntHave('inventoryMovements', function ($query) use ($dateFrom, $dateTo) {
                $query->where('movement_type', 'sale')
                      ->whereBetween('created_at', [$dateFrom, $dateTo]);
            })
            ->where('stock_quantity', '>', 0)
            ->where('is_preorder', false)
            ->where('status', 'active')
            ->orderBy('stock_quantity', 'desc')
            ->limit(20)
            ->get();

        // Stock value analysis
        $stockValue = Product::where('status', 'active')
            ->selectRaw('
                SUM(CASE WHEN is_preorder = 0 THEN stock_quantity * current_price ELSE 0 END) as in_stock_value,
                SUM(CASE WHEN is_preorder = 1 THEN current_price ELSE 0 END) as preorder_value,
                COUNT(CASE WHEN stock_quantity <= 5 AND is_preorder = 0 AND stock_quantity > 0 THEN 1 END) as low_stock_count,
                COUNT(CASE WHEN stock_quantity = 0 AND is_preorder = 0 THEN 1 END) as out_of_stock_count
            ')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ],
                'movements' => $movements,
                'top_selling' => $topSelling,
                'slow_moving' => $slowMoving,
                'stock_value' => $stockValue,
                'summary' => [
                    'total_movements' => $movements->sum('count'),
                    'total_quantity_moved' => $movements->sum('total_quantity'),
                    'stock_value' => $stockValue->in_stock_value ?? 0,
                    'preorder_value' => $stockValue->preorder_value ?? 0,
                    'low_stock_alerts' => $stockValue->low_stock_count ?? 0,
                    'out_of_stock_items' => $stockValue->out_of_stock_count ?? 0,
                ]
            ]
        ]);
    }

    /**
     * Get pre-order arrival tracking.
     */
    public function preOrderArrivals(Request $request): JsonResponse
    {
        $query = PreOrder::with(['product.brand', 'user'])
            ->where('status', 'deposit_paid');

        // Filter by arrival status
        if ($request->has('arrival_status')) {
            switch ($request->arrival_status) {
                case 'pending':
                    $query->whereNull('actual_arrival_date');
                    break;
                case 'arrived':
                    $query->whereNotNull('actual_arrival_date');
                    break;
                case 'overdue':
                    $query->whereNull('actual_arrival_date')
                          ->where('estimated_arrival_date', '<', now());
                    break;
            }
        }

        // Filter by date range
        if ($request->has('estimated_from')) {
            $query->where('estimated_arrival_date', '>=', $request->estimated_from);
        }
        
        if ($request->has('estimated_to')) {
            $query->where('estimated_arrival_date', '<=', $request->estimated_to);
        }

        $preorders = $query->orderBy('estimated_arrival_date', 'asc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $preorders
        ]);
    }

    /**
     * Update pre-order arrival status.
     */
    public function updatePreOrderArrival(Request $request, PreOrder $preorder): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'actual_arrival_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $preorder->actual_arrival_date = $request->actual_arrival_date;
            $preorder->admin_notes = $request->notes;
            
            // Mark as ready for payment if deposit was paid
            if ($preorder->status === PreOrder::STATUS_DEPOSIT_PAID) {
                $preorder->markReadyForPayment();
                
                // Send arrival notification
                $this->notificationService->sendPreOrderArrivalNotification($preorder);
            }
            
            $preorder->save();

            return response()->json([
                'success' => true,
                'message' => 'Pre-order arrival updated successfully',
                'data' => $preorder->fresh(['product.brand', 'user'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update arrival: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get chase variant special handling workflows.
     */
    public function chaseVariants(Request $request): JsonResponse
    {
        $query = Product::with(['brand', 'category', 'inventoryMovements' => function ($q) {
            $q->latest()->limit(3);
        }])
        ->where('is_chase_variant', true);

        // Filter by availability
        if ($request->has('availability')) {
            switch ($request->availability) {
                case 'available':
                    $query->where('stock_quantity', '>', 0);
                    break;
                case 'reserved':
                    // Products with pending orders but still in stock
                    $query->whereHas('orderItems', function ($q) {
                        $q->whereHas('order', function ($orderQuery) {
                            $orderQuery->whereIn('status', ['pending', 'confirmed']);
                        });
                    })->where('stock_quantity', '>', 0);
                    break;
                case 'sold_out':
                    $query->where('stock_quantity', 0);
                    break;
            }
        }

        $chaseVariants = $query->orderBy('stock_quantity', 'asc')
                              ->orderBy('current_price', 'desc')
                              ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $chaseVariants,
            'summary' => [
                'total_chase_variants' => Product::where('is_chase_variant', true)->count(),
                'available' => Product::where('is_chase_variant', true)->where('stock_quantity', '>', 0)->count(),
                'sold_out' => Product::where('is_chase_variant', true)->where('stock_quantity', 0)->count(),
                'average_price' => Product::where('is_chase_variant', true)->avg('current_price'),
            ]
        ]);
    }

    /**
     * Create purchase order for suppliers.
     */
    public function createPurchaseOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'supplier_name' => 'required|string|max:255',
            'supplier_email' => 'required|email',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.unit_cost' => 'required|numeric|min:0',
            'expected_delivery_date' => 'required|date|after:today',
            'notes' => 'nullable|string|max:1000',
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
            $purchaseOrderNumber = 'PO-' . now()->format('ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $totalAmount = 0;
            $orderItems = [];
            
            foreach ($request->products as $item) {
                $product = Product::find($item['product_id']);
                $lineTotal = $item['quantity'] * $item['unit_cost'];
                $totalAmount += $lineTotal;
                
                $orderItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'line_total' => $lineTotal,
                ];
            }

            // For now, we'll store this as a JSON record in inventory movements
            // In a full implementation, you'd have a separate purchase_orders table
            InventoryMovement::create([
                'product_id' => $request->products[0]['product_id'], // Reference first product
                'movement_type' => 'purchase_order',
                'quantity_change' => 0, // No immediate stock change
                'quantity_before' => 0,
                'quantity_after' => 0,
                'reference_type' => 'purchase_order',
                'reference_id' => $purchaseOrderNumber,
                'reason' => json_encode([
                    'purchase_order_number' => $purchaseOrderNumber,
                    'supplier_name' => $request->supplier_name,
                    'supplier_email' => $request->supplier_email,
                    'expected_delivery_date' => $request->expected_delivery_date,
                    'total_amount' => $totalAmount,
                    'items' => $orderItems,
                    'notes' => $request->notes,
                    'status' => 'pending',
                    'created_by' => auth()->id(),
                ]),
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Purchase order created successfully',
                'data' => [
                    'purchase_order_number' => $purchaseOrderNumber,
                    'supplier_name' => $request->supplier_name,
                    'total_amount' => $totalAmount,
                    'items_count' => count($orderItems),
                    'expected_delivery_date' => $request->expected_delivery_date,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create purchase order: ' . $e->getMessage()
            ], 500);
        }
    }
}