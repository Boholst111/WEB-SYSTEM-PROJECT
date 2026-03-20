<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\LoyaltyTransaction;
use App\Models\PreOrder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AnalyticsController extends Controller
{
    protected AnalyticsService $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Get comprehensive analytics dashboard data.
     */
    public function index(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', now()->subMonth()->toDateString());
        $dateTo = $request->get('date_to', now()->toDateString());
        $period = $request->get('period', 'daily'); // daily, weekly, monthly
        
        // Cache key for analytics data
        $cacheKey = "analytics_dashboard_{$dateFrom}_{$dateTo}_{$period}";
        
        $data = Cache::remember($cacheKey, 300, function () use ($dateFrom, $dateTo, $period) {
            $salesAnalytics = $this->analyticsService->getSalesAnalytics($dateFrom, $dateTo, $period);
            $productAnalytics = $this->analyticsService->getProductAnalytics($dateFrom, $dateTo);
            $customerAnalytics = $this->analyticsService->getCustomerAnalytics($dateFrom, $dateTo);
            
            // Transform sales analytics to match frontend expectations
            $transformedSalesAnalytics = [
                'total_revenue' => $salesAnalytics['revenue_metrics']['total_revenue'] ?? 0,
                'total_orders' => $salesAnalytics['order_metrics']['total_orders'] ?? 0,
                'average_order_value' => $salesAnalytics['order_metrics']['average_order_value'] ?? 0,
                'conversion_rate' => $salesAnalytics['order_metrics']['conversion_rate'] ?? 0,
                'growth_rate' => $salesAnalytics['growth_comparison']['growth']['revenue_growth'] ?? 0,
                'revenue_by_period' => array_map(function ($item) {
                    return [
                        'date' => $item['period'] ?? '',
                        'revenue' => $item['revenue'] ?? 0,
                        'orders' => $item['orders'] ?? 0,
                    ];
                }, $salesAnalytics['time_series'] ?? []),
            ];
            
            // Transform product analytics to match frontend expectations
            $transformedProductAnalytics = [
                'best_sellers' => $productAnalytics['best_sellers'] ?? [],
                'slow_movers' => $productAnalytics['slow_movers'] ?? [],
                'inventory_turnover' => is_array($productAnalytics['inventory_turnover'] ?? null) 
                    ? (count($productAnalytics['inventory_turnover']) > 0 
                        ? ($productAnalytics['inventory_turnover'][0]['turnover_rate'] ?? 0)
                        : 0)
                    : 0,
                'category_performance' => $productAnalytics['category_performance'] ?? [],
                'brand_performance' => $productAnalytics['brand_performance'] ?? [],
            ];
            
            // Transform customer analytics to match frontend expectations
            $transformedCustomerAnalytics = [
                'total_customers' => $customerAnalytics['acquisition_metrics']['total_customers'] ?? 0,
                'new_customers' => $customerAnalytics['acquisition_metrics']['new_customers'] ?? 0,
                'returning_customers' => $customerAnalytics['retention_metrics']['returning_customers'] ?? 0,
                'customer_retention_rate' => $customerAnalytics['retention_metrics']['retention_rate'] ?? 0,
                'loyalty_tier_distribution' => $customerAnalytics['loyalty_analysis']['tier_distribution'] ?? [],
                'top_customers' => $customerAnalytics['lifetime_value']['top_customers'] ?? [],
            ];
            
            return [
                'sales_analytics' => $transformedSalesAnalytics,
                'product_analytics' => $transformedProductAnalytics,
                'customer_analytics' => $transformedCustomerAnalytics,
                'traffic_analysis' => $this->getTrafficAnalysis($dateFrom, $dateTo),
                'loyalty_metrics' => $this->getLoyaltyMetrics($dateFrom, $dateTo),
                'inventory_insights' => $this->getInventoryInsights(),
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo,
                    'period' => $period
                ]
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get sales metrics and revenue reporting.
     */
    public function salesMetrics(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', now()->subMonth()->toDateString());
        $dateTo = $request->get('date_to', now()->toDateString());
        $period = $request->get('period', 'daily');

        $data = $this->analyticsService->getSalesAnalytics($dateFrom, $dateTo, $period);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get product performance analytics.
     */
    public function productPerformance(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', now()->subMonth()->toDateString());
        $dateTo = $request->get('date_to', now()->toDateString());
        $limit = min($request->get('limit', 20), 100);

        $data = $this->analyticsService->getProductAnalytics($dateFrom, $dateTo, $limit);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get customer behavior and loyalty analytics.
     */
    public function customerAnalytics(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', now()->subMonth()->toDateString());
        $dateTo = $request->get('date_to', now()->toDateString());

        $data = $this->analyticsService->getCustomerAnalytics($dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get traffic analysis and conversion tracking.
     */
    public function trafficAnalysis(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', now()->subMonth()->toDateString());
        $dateTo = $request->get('date_to', now()->toDateString());

        $data = $this->getTrafficAnalysis($dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get real-time dashboard summary.
     */
    public function realTimeSummary(): JsonResponse
    {
        $today = now()->toDateString();
        
        $summary = [
            'today_orders' => Order::whereDate('created_at', $today)->count(),
            'today_revenue' => Order::whereDate('created_at', $today)
                                  ->where('payment_status', 'paid')
                                  ->sum('total_amount'),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'low_stock_products' => Product::where('stock_quantity', '<=', 5)
                                         ->where('is_preorder', false)
                                         ->count(),
            'active_users_today' => User::whereDate('last_login_at', $today)->count(),
            'conversion_rate_today' => $this->calculateDailyConversionRate($today),
        ];

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }

    /**
     * Get traffic analysis and conversion tracking.
     */
    private function getTrafficAnalysis(string $dateFrom, string $dateTo): array
    {
        // Note: This is simplified traffic analysis based on order data
        // In a real implementation, you'd integrate with Google Analytics or similar

        $totalOrders = Order::whereBetween('created_at', [$dateFrom, $dateTo])->count();
        $paidOrders = Order::whereBetween('created_at', [$dateFrom, $dateTo])
                          ->where('payment_status', 'paid')
                          ->count();

        // Estimated traffic (simplified calculation)
        $estimatedVisitors = $this->estimateVisitors($dateFrom, $dateTo);
        $conversionRate = $estimatedVisitors > 0 ? ($paidOrders / $estimatedVisitors) * 100 : 0;

        // Cart abandonment (orders created but not paid)
        $abandonedCarts = Order::whereBetween('created_at', [$dateFrom, $dateTo])
                              ->where('payment_status', '!=', 'paid')
                              ->count();
        
        $cartAbandonmentRate = $totalOrders > 0 ? ($abandonedCarts / $totalOrders) * 100 : 0;

        // Popular products (based on order frequency)
        $popularProducts = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->whereBetween('orders.created_at', [$dateFrom, $dateTo])
            ->select('products.id', 'products.name', 'products.sku', 
                    DB::raw('count(*) as view_count'))
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderBy('view_count', 'desc')
            ->limit(10)
            ->get();

        // Traffic by device type (simplified estimation)
        $deviceTypes = [
            'desktop' => $estimatedVisitors * 0.6,
            'mobile' => $estimatedVisitors * 0.35,
            'tablet' => $estimatedVisitors * 0.05,
        ];

        // Peak traffic hours (based on order creation times)
        $hourClause = config('database.default') === 'mysql' 
            ? 'HOUR(created_at) as hour' 
            : 'strftime("%H", created_at) as hour';

        $peakHours = Order::whereBetween('created_at', [$dateFrom, $dateTo])
            ->select(DB::raw($hourClause), DB::raw('count(*) as count'))
            ->groupBy('hour')
            ->orderBy('count', 'desc')
            ->get();

        return [
            'summary' => [
                'estimated_visitors' => $estimatedVisitors,
                'conversion_rate' => round($conversionRate, 2),
                'cart_abandonment_rate' => round($cartAbandonmentRate, 2),
                'bounce_rate' => 45.2, // Placeholder - would come from analytics
                'avg_session_duration' => '3:24', // Placeholder
            ],
            'popular_products' => $popularProducts,
            'device_types' => $deviceTypes,
            'peak_hours' => $peakHours,
        ];
    }

    /**
     * Get loyalty metrics data.
     */
    private function getLoyaltyMetrics(string $dateFrom, string $dateTo): array
    {
        // Credits earned and redeemed
        $creditsEarned = LoyaltyTransaction::whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('transaction_type', 'earned')
            ->sum('amount');

        $creditsRedeemed = LoyaltyTransaction::whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('transaction_type', 'redeemed')
            ->sum('amount');

        // Active loyalty members
        $activeLoyaltyMembers = User::where('loyalty_credits', '>', 0)->count();

        // Credits utilization rate
        $utilizationRate = $creditsEarned > 0 ? (abs($creditsRedeemed) / $creditsEarned) * 100 : 0;

        // Tier progression
        $tierProgression = LoyaltyTransaction::whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('transaction_type', 'tier_bonus')
            ->count();

        // Top loyalty earners
        $topEarners = User::whereHas('loyaltyTransactions', function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('created_at', [$dateFrom, $dateTo])
                      ->where('transaction_type', 'earned');
            })
            ->withSum(['loyaltyTransactions' => function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('created_at', [$dateFrom, $dateTo])
                      ->where('transaction_type', 'earned');
            }], 'amount')
            ->orderBy('loyalty_transactions_sum_amount', 'desc')
            ->limit(10)
            ->get();

        // Credits expiring soon
        $expiringCredits = LoyaltyTransaction::where('transaction_type', 'earned')
            ->where('is_expired', false)
            ->whereBetween('expires_at', [now(), now()->addDays(30)])
            ->sum('amount');

        return [
            'summary' => [
                'credits_earned' => round($creditsEarned, 2),
                'credits_redeemed' => round(abs($creditsRedeemed), 2),
                'active_members' => $activeLoyaltyMembers,
                'utilization_rate' => round($utilizationRate, 2),
                'tier_progressions' => $tierProgression,
                'expiring_credits' => round($expiringCredits, 2),
            ],
            'top_earners' => $topEarners,
        ];
    }

    /**
     * Get inventory insights.
     */
    private function getInventoryInsights(): array
    {
        // Low stock alerts
        $lowStockProducts = Product::where('stock_quantity', '<=', 5)
            ->where('is_preorder', false)
            ->where('status', 'active')
            ->with(['brand', 'category'])
            ->orderBy('stock_quantity', 'asc')
            ->limit(20)
            ->get();

        // Out of stock products
        $outOfStockProducts = Product::where('stock_quantity', 0)
            ->where('is_preorder', false)
            ->where('status', 'active')
            ->count();

        // Total inventory value
        $totalInventoryValue = Product::where('status', 'active')
            ->sum(DB::raw('stock_quantity * current_price'));

        // Pre-order statistics
        $preOrderStats = [
            'total_preorders' => PreOrder::count(),
            'pending_arrivals' => PreOrder::whereNull('actual_arrival_date')
                                        ->where('estimated_arrival_date', '>', now())
                                        ->count(),
            'overdue_arrivals' => PreOrder::whereNull('actual_arrival_date')
                                        ->where('estimated_arrival_date', '<', now())
                                        ->count(),
        ];

        return [
            'low_stock_products' => $lowStockProducts,
            'out_of_stock_count' => $outOfStockProducts,
            'total_inventory_value' => round($totalInventoryValue, 2),
            'preorder_stats' => $preOrderStats,
        ];
    }

    /**
     * Estimate visitors based on orders (simplified calculation).
     */
    private function estimateVisitors(string $dateFrom, string $dateTo): int
    {
        $orders = Order::whereBetween('created_at', [$dateFrom, $dateTo])->count();
        // Assume 1 order per 20 visitors (5% conversion rate)
        return $orders * 20;
    }

    /**
     * Calculate daily conversion rate.
     */
    private function calculateDailyConversionRate(string $date): float
    {
        $orders = Order::whereDate('created_at', $date)->count();
        $paidOrders = Order::whereDate('created_at', $date)
                          ->where('payment_status', 'paid')
                          ->count();

        return $orders > 0 ? ($paidOrders / $orders) * 100 : 0;
    }
}