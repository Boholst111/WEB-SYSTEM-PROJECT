<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\LoyaltyTransaction;
use App\Services\Payment\PaymentService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class OrderManagementService
{
    protected PaymentService $paymentService;
    protected NotificationService $notificationService;
    protected ShippingService $shippingService;

    public function __construct(
        PaymentService $paymentService,
        NotificationService $notificationService,
        ShippingService $shippingService
    ) {
        $this->paymentService = $paymentService;
        $this->notificationService = $notificationService;
        $this->shippingService = $shippingService;
    }

    /**
     * Update order status with proper validation and side effects.
     */
    public function updateOrderStatus(Order $order, string $newStatus, array $options = []): array
    {
        $oldStatus = $order->status;

        // Validate status transition
        if (!$order->updateStatus($newStatus)) {
            return [
                'success' => false,
                'message' => "Invalid status transition from {$oldStatus} to {$newStatus}"
            ];
        }

        // Update additional fields
        if (isset($options['tracking_number'])) {
            $order->tracking_number = $options['tracking_number'];
        }

        if (isset($options['courier_service'])) {
            $order->courier_service = $options['courier_service'];
        }

        if (isset($options['admin_notes'])) {
            $order->admin_notes = $options['admin_notes'];
        }

        $order->save();

        // Load relationships if not already loaded for side effects
        if (!$order->relationLoaded('items')) {
            $order->load('items.product');
        }
        if (!$order->relationLoaded('user')) {
            $order->load('user');
        }

        // Handle status-specific side effects
        $this->handleStatusSideEffects($order, $oldStatus, $newStatus, $options);

        // Send notifications if requested
        if ($options['notify_customer'] ?? true) {
            $this->notificationService->sendOrderStatusUpdate($order, $oldStatus, $newStatus);
        }

        // Log the status change
        $this->logOrderStatusChange($order, $oldStatus, $newStatus, $options);

        return [
            'success' => true,
            'message' => "Order status updated from {$oldStatus} to {$newStatus}"
        ];
    }

    /**
     * Handle bulk order updates.
     */
    public function bulkUpdateOrders(array $orderIds, string $action, array $options = []): array
    {
        $orders = Order::with(['items.product', 'user'])->whereIn('id', $orderIds)->get();
        $results = [];
        $processed = 0;
        $failed = 0;

        foreach ($orders as $order) {
            try {
                DB::beginTransaction();

                $result = match ($action) {
                    'update_status' => $this->updateOrderStatus($order, $options['status'] ?? '', $options),
                    'add_tracking' => $this->addTrackingInfo($order, $options),
                    'cancel' => $this->cancelOrder($order, $options),
                    'export' => $this->exportOrder($order, $options),
                    default => ['success' => false, 'message' => 'Invalid action']
                };

                if ($result['success']) {
                    $processed++;
                    DB::commit();
                } else {
                    $failed++;
                    DB::rollBack();
                }

                $results[] = [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'success' => $result['success'],
                    'message' => $result['message']
                ];

            } catch (\Exception $e) {
                $failed++;
                DB::rollBack();
                
                $results[] = [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
        }

        return [
            'message' => "Processed {$processed} orders successfully, {$failed} failed",
            'processed' => $processed,
            'failed' => $failed,
            'results' => $results
        ];
    }

    /**
     * Handle payment exceptions.
     */
    public function handlePaymentException(Order $order, string $action, array $options = []): array
    {
        try {
            DB::beginTransaction();

            $result = match ($action) {
                'retry_payment' => $this->retryPayment($order, $options),
                'mark_paid' => $this->markOrderPaid($order, $options),
                'refund' => $this->processRefund($order, $options),
                'cancel' => $this->cancelOrder($order, $options),
                default => ['success' => false, 'message' => 'Invalid payment action']
            };

            if ($result['success']) {
                DB::commit();
                
                // Log the exception handling
                $this->logPaymentException($order, $action, $options);
                
                // Notify customer if needed
                if ($options['notify_customer'] ?? true) {
                    $this->notificationService->sendPaymentExceptionNotification($order, $action, $result);
                }
            } else {
                DB::rollBack();
            }

            return $result;

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Payment exception handling failed', [
                'order_id' => $order->id,
                'action' => $action,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to handle payment exception: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Handle inventory exceptions.
     */
    public function handleInventoryException(Order $order, string $action, array $items, array $options = []): array
    {
        try {
            DB::beginTransaction();

            $result = match ($action) {
                'substitute_product' => $this->substituteProducts($order, $items, $options),
                'partial_fulfillment' => $this->partialFulfillment($order, $items, $options),
                'cancel_items' => $this->cancelOrderItems($order, $items, $options),
                'wait_restock' => $this->waitForRestock($order, $items, $options),
                default => ['success' => false, 'message' => 'Invalid inventory action']
            };

            if ($result['success']) {
                DB::commit();
                
                // Log the exception handling
                $this->logInventoryException($order, $action, $items, $options);
                
                // Notify customer if needed
                if ($options['notify_customer'] ?? true) {
                    $this->notificationService->sendInventoryExceptionNotification($order, $action, $result);
                }
            } else {
                DB::rollBack();
            }

            return $result;

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Inventory exception handling failed', [
                'order_id' => $order->id,
                'action' => $action,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to handle inventory exception: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get order timeline for admin view.
     */
    public function getOrderTimeline(Order $order): array
    {
        $timeline = [];

        // Order created
        $timeline[] = [
            'event' => 'order_created',
            'title' => 'Order Created',
            'description' => "Order #{$order->order_number} was created",
            'timestamp' => $order->created_at,
            'status' => 'completed'
        ];

        // Payment events
        if ($order->payment) {
            $timeline[] = [
                'event' => 'payment_processed',
                'title' => 'Payment Processed',
                'description' => "Payment via {$order->payment_method}",
                'timestamp' => $order->payment->created_at,
                'status' => $order->payment_status === 'paid' ? 'completed' : 'pending'
            ];
        }

        // Status changes
        if ($order->status !== Order::STATUS_PENDING) {
            $timeline[] = [
                'event' => 'status_confirmed',
                'title' => 'Order Confirmed',
                'description' => 'Order has been confirmed and is being processed',
                'timestamp' => $order->updated_at,
                'status' => 'completed'
            ];
        }

        if ($order->status === Order::STATUS_PROCESSING) {
            $timeline[] = [
                'event' => 'processing',
                'title' => 'Processing',
                'description' => 'Order is being prepared for shipment',
                'timestamp' => $order->updated_at,
                'status' => 'active'
            ];
        }

        if ($order->shipped_at) {
            $timeline[] = [
                'event' => 'shipped',
                'title' => 'Shipped',
                'description' => "Shipped via {$order->courier_service}. Tracking: {$order->tracking_number}",
                'timestamp' => $order->shipped_at,
                'status' => 'completed'
            ];
        }

        if ($order->delivered_at) {
            $timeline[] = [
                'event' => 'delivered',
                'title' => 'Delivered',
                'description' => 'Order has been delivered successfully',
                'timestamp' => $order->delivered_at,
                'status' => 'completed'
            ];
        }

        // Sort by timestamp
        usort($timeline, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);

        return $timeline;
    }

    /**
     * Get order analytics and metrics.
     */
    public function getOrderAnalytics(string $dateFrom, string $dateTo, string $groupBy = 'day'): array
    {
        // Use SQLite compatible date formatting
        $dateFormat = match ($groupBy) {
            'week' => '%Y-%W',
            'month' => '%Y-%m',
            default => '%Y-%m-%d'
        };

        // Check if we're using SQLite (common in tests)
        $isSqlite = config('database.default') === 'sqlite' || 
                   (config('database.connections.testing.driver') === 'sqlite' && app()->environment('testing'));

        if ($isSqlite) {
            // SQLite compatible query
            $orderTrends = Order::selectRaw("
                strftime('{$dateFormat}', created_at) as period,
                COUNT(*) as order_count,
                SUM(total_amount) as revenue,
                AVG(total_amount) as avg_order_value
            ")
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('period')
            ->orderBy('period')
            ->get();
        } else {
            // MySQL compatible query
            $orderTrends = Order::selectRaw("
                DATE_FORMAT(created_at, '{$dateFormat}') as period,
                COUNT(*) as order_count,
                SUM(total_amount) as revenue,
                AVG(total_amount) as avg_order_value
            ")
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('period')
            ->orderBy('period')
            ->get();
        }

        // Status distribution
        $statusDistribution = Order::selectRaw('status, COUNT(*) as count')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Payment method performance
        $paymentMethods = Order::selectRaw('
            payment_method,
            COUNT(*) as order_count,
            SUM(total_amount) as revenue,
            AVG(total_amount) as avg_value
        ')
        ->whereBetween('created_at', [$dateFrom, $dateTo])
        ->whereNotNull('payment_method')
        ->groupBy('payment_method')
        ->get();

        // Top products by order frequency
        $topProducts = OrderItem::selectRaw('
            products.name,
            products.sku,
            SUM(order_items.quantity) as total_quantity,
            COUNT(DISTINCT order_items.order_id) as order_count,
            SUM(order_items.unit_price * order_items.quantity) as revenue
        ')
        ->join('products', 'order_items.product_id', '=', 'products.id')
        ->join('orders', 'order_items.order_id', '=', 'orders.id')
        ->whereBetween('orders.created_at', [$dateFrom, $dateTo])
        ->groupBy('products.id', 'products.name', 'products.sku')
        ->orderByDesc('total_quantity')
        ->limit(10)
        ->get();

        // Customer metrics
        $customerMetrics = [
            'new_customers' => User::whereBetween('created_at', [$dateFrom, $dateTo])->count(),
            'returning_customers' => Order::selectRaw('COUNT(DISTINCT user_id) as count')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->whereIn('user_id', function ($query) use ($dateFrom) {
                    $query->select('user_id')
                          ->from('orders')
                          ->where('created_at', '<', $dateFrom)
                          ->distinct();
                })
                ->value('count') ?? 0,
            'avg_orders_per_customer' => Order::whereBetween('created_at', [$dateFrom, $dateTo])
                ->selectRaw('CAST(COUNT(*) AS FLOAT) / COUNT(DISTINCT user_id) as avg')
                ->value('avg') ?? 0
        ];

        return [
            'order_trends' => $orderTrends,
            'status_distribution' => $statusDistribution,
            'payment_methods' => $paymentMethods,
            'top_products' => $topProducts,
            'customer_metrics' => $customerMetrics,
            'summary' => [
                'total_orders' => Order::whereBetween('created_at', [$dateFrom, $dateTo])->count(),
                'total_revenue' => Order::whereBetween('created_at', [$dateFrom, $dateTo])->sum('total_amount') ?? 0,
                'avg_order_value' => Order::whereBetween('created_at', [$dateFrom, $dateTo])->avg('total_amount') ?? 0,
                'completion_rate' => $this->calculateCompletionRate($dateFrom, $dateTo),
            ]
        ];
    }

    /**
     * Export orders to various formats.
     */
    public function exportOrders(string $format, array $filters = [], array $columns = []): array
    {
        // This would integrate with a proper export service
        // For now, return a placeholder response
        $filename = "orders_export_" . now()->format('Y-m-d_H-i-s') . ".{$format}";
        
        return [
            'url' => "/admin/exports/{$filename}",
            'filename' => $filename,
            'expires_at' => now()->addHours(24)->toISOString()
        ];
    }

    /**
     * Handle status-specific side effects.
     */
    private function handleStatusSideEffects(Order $order, string $oldStatus, string $newStatus, array $options): void
    {
        switch ($newStatus) {
            case Order::STATUS_CONFIRMED:
                $this->reserveInventory($order);
                break;
                
            case Order::STATUS_SHIPPED:
                if (isset($options['tracking_number'])) {
                    $this->shippingService->createShipment($order, $options);
                }
                break;
                
            case Order::STATUS_DELIVERED:
                $this->completeOrder($order);
                break;
                
            case Order::STATUS_CANCELLED:
                $this->releaseInventory($order);
                $this->handleCancellationRefund($order);
                break;
        }
    }

    /**
     * Reserve inventory for confirmed orders.
     */
    private function reserveInventory(Order $order): void
    {
        foreach ($order->items as $item) {
            $product = $item->product;
            if ($product->stock_quantity >= $item->quantity) {
                $quantityBefore = $product->stock_quantity;
                $product->decrement('stock_quantity', $item->quantity);
                
                // Log inventory movement
                $product->inventoryMovements()->create([
                    'movement_type' => 'reservation',
                    'quantity_change' => -$item->quantity,
                    'quantity_before' => $quantityBefore,
                    'quantity_after' => $quantityBefore - $item->quantity,
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'reason' => "Reserved for order #{$order->order_number}"
                ]);
            }
        }
    }

    /**
     * Release inventory for cancelled orders.
     */
    private function releaseInventory(Order $order): void
    {
        foreach ($order->items as $item) {
            $product = $item->product;
            $quantityBefore = $product->stock_quantity;
            $product->increment('stock_quantity', $item->quantity);
            
            // Log inventory movement
            $product->inventoryMovements()->create([
                'movement_type' => 'release',
                'quantity_change' => $item->quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityBefore + $item->quantity,
                'reference_type' => 'order',
                'reference_id' => $order->id,
                'reason' => "Released from cancelled order #{$order->order_number}"
            ]);
        }
    }

    /**
     * Complete order and award loyalty credits.
     */
    private function completeOrder(Order $order): void
    {
        // Award loyalty credits
        $order->awardLoyaltyCredits();
        
        // Update customer metrics
        $user = $order->user;
        $user->increment('total_orders');
        $user->touch('last_order_at');
    }

    /**
     * Calculate completion rate for analytics.
     */
    private function calculateCompletionRate(string $dateFrom, string $dateTo): float
    {
        $totalOrders = Order::whereBetween('created_at', [$dateFrom, $dateTo])->count();
        $completedOrders = Order::whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('status', Order::STATUS_DELIVERED)
            ->count();

        return $totalOrders > 0 ? ($completedOrders / $totalOrders) * 100 : 0;
    }

    /**
     * Log order status changes.
     */
    private function logOrderStatusChange(Order $order, string $oldStatus, string $newStatus, array $options): void
    {
        Log::info('Order status changed', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'admin_user_id' => $options['admin_user_id'] ?? null,
            'admin_notes' => $options['admin_notes'] ?? null
        ]);
    }

    /**
     * Log payment exception handling.
     */
    private function logPaymentException(Order $order, string $action, array $options): void
    {
        Log::warning('Payment exception handled', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'action' => $action,
            'admin_user_id' => $options['admin_user_id'] ?? null,
            'reason' => $options['reason'] ?? null
        ]);
    }

    /**
     * Log inventory exception handling.
     */
    private function logInventoryException(Order $order, string $action, array $items, array $options): void
    {
        Log::warning('Inventory exception handled', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'action' => $action,
            'affected_items' => count($items),
            'admin_user_id' => $options['admin_user_id'] ?? null
        ]);
    }

    // Placeholder methods for payment and inventory exception handling
    // These would be implemented based on specific business requirements

    private function retryPayment(Order $order, array $options): array
    {
        return ['success' => true, 'message' => 'Payment retry initiated'];
    }

    private function markOrderPaid(Order $order, array $options): array
    {
        $order->payment_status = Order::PAYMENT_PAID;
        $order->save();
        return ['success' => true, 'message' => 'Order marked as paid'];
    }

    private function processRefund(Order $order, array $options): array
    {
        return ['success' => true, 'message' => 'Refund processed'];
    }

    private function cancelOrder(Order $order, array $options): array
    {
        return $this->updateOrderStatus($order, Order::STATUS_CANCELLED, $options);
    }

    private function addTrackingInfo(Order $order, array $options): array
    {
        if (isset($options['tracking_numbers'])) {
            $trackingNumbers = $options['tracking_numbers'];
            $order->tracking_number = $trackingNumbers[$order->id] ?? null;
        }
        
        if (isset($options['courier_service'])) {
            $order->courier_service = $options['courier_service'];
        }
        
        $order->save();
        
        return ['success' => true, 'message' => 'Tracking information updated'];
    }

    private function exportOrder(Order $order, array $options): array
    {
        return ['success' => true, 'message' => 'Order exported'];
    }

    private function substituteProducts(Order $order, array $items, array $options): array
    {
        return ['success' => true, 'message' => 'Products substituted'];
    }

    private function partialFulfillment(Order $order, array $items, array $options): array
    {
        return ['success' => true, 'message' => 'Partial fulfillment processed'];
    }

    private function cancelOrderItems(Order $order, array $items, array $options): array
    {
        return ['success' => true, 'message' => 'Order items cancelled'];
    }

    private function waitForRestock(Order $order, array $items, array $options): array
    {
        return ['success' => true, 'message' => 'Order set to wait for restock'];
    }

    private function handleCancellationRefund(Order $order): void
    {
        if ($order->isPaid()) {
            // Process refund logic here
            $order->payment_status = Order::PAYMENT_REFUNDED;
            $order->save();
        }
    }
}