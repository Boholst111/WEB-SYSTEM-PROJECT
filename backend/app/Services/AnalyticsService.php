<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\LoyaltyTransaction;
use App\Models\OrderItem;
use App\Models\PreOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * Get comprehensive sales analytics.
     */
    public function getSalesAnalytics(string $dateFrom, string $dateTo, string $period = 'daily'): array
    {
        return [
            'revenue_metrics' => $this->getRevenueMetrics($dateFrom, $dateTo),
            'order_metrics' => $this->getOrderMetrics($dateFrom, $dateTo),
            'payment_analytics' => $this->getPaymentAnalytics($dateFrom, $dateTo),
            'time_series' => $this->getRevenueTimeSeries($dateFrom, $dateTo, $period),
            'growth_comparison' => $this->getGrowthComparison($dateFrom, $dateTo),
        ];
    }

    /**
     * Get product performance analytics.
     */
    public function getProductAnalytics(string $dateFrom, string $dateTo, int $limit = 20): array
    {
        return [
            'best_sellers' => $this->getBestSellingProducts($dateFrom, $dateTo, $limit),
            'slow_movers' => $this->getSlowMovingProducts($dateFrom, $dateTo, $limit),
            'inventory_turnover' => $this->getInventoryTurnover($dateFrom, $dateTo, $limit),
            'category_performance' => $this->getCategoryPerformance($dateFrom, $dateTo),
            'brand_performance' => $this->getBrandPerformance($dateFrom, $dateTo),
            'price_analysis' => $this->getPriceAnalysis($dateFrom, $dateTo),
        ];
    }

    /**
     * Get customer behavior analytics.
     */
    public function getCustomerAnalytics(string $dateFrom, string $dateTo): array
    {
        return [
            'acquisition_metrics' => $this->getCustomerAcquisition($dateFrom, $dateTo),
            'retention_metrics' => $this->getCustomerRetention($dateFrom, $dateTo),
            'lifetime_value' => $this->getCustomerLifetimeValue($dateFrom, $dateTo),
            'loyalty_analysis' => $this->getLoyaltyAnalysis($dateFrom, $dateTo),
            'segmentation' => $this->getCustomerSegmentation($dateFrom, $dateTo),
        ];
    }

    /**
     * Get revenue metrics.
     */
    private function getRevenueMetrics(string $dateFrom, string $dateTo): array
    {
        $totalRevenue = Order::whereBetween('created_at', [$dateFrom, $dateTo])
                           ->where('payment_status', 'paid')
                           ->sum('total_amount');

        $grossRevenue = Order::whereBetween('created_at', [$dateFrom, $dateTo])
                            ->where('payment_status', 'paid')
                            ->sum('subtotal');

        $discountAmount = Order::whereBetween('created_at', [$dateFrom, $dateTo])
                              ->where('payment_status', 'paid')
                              ->sum('discount_amount');

        $creditsUsed = Order::whereBetween('created_at', [$dateFrom, $dateTo])
                           ->where('payment_status', 'paid')
                           ->sum('credits_used');

        $shippingRevenue = Order::whereBetween('created_at', [$dateFrom, $dateTo])
                               ->where('payment_status', 'paid')
                               ->sum('shipping_fee');

        return [
            'total_revenue' => round($totalRevenue, 2),
            'gross_revenue' => round($grossRevenue, 2),
            'discount_amount' => round($discountAmount, 2),
            'credits_used' => round($creditsUsed, 2),
            'shipping_revenue' => round($shippingRevenue, 2),
            'net_revenue' => round($totalRevenue - $discountAmount - $creditsUsed, 2),
        ];
    }

    /**
     * Get order metrics.
     */
    private function getOrderMetrics(string $dateFrom, string $dateTo): array
    {
        $totalOrders = Order::whereBetween('created_at', [$dateFrom, $dateTo])->count();
        $paidOrders = Order::whereBetween('created_at', [$dateFrom, $dateTo])
                          ->where('payment_status', 'paid')
                          ->count();

        $averageOrderValue = $paidOrders > 0 ? 
            Order::whereBetween('created_at', [$dateFrom, $dateTo])
                 ->where('payment_status', 'paid')
                 ->avg('total_amount') : 0;

        $orderStatusBreakdown = Order::whereBetween('created_at', [$dateFrom, $dateTo])
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'total_orders' => $totalOrders,
            'paid_orders' => $paidOrders,
            'average_order_value' => round($averageOrderValue, 2),
            'conversion_rate' => $totalOrders > 0 ? round(($paidOrders / $totalOrders) * 100, 2) : 0,
            'status_breakdown' => $orderStatusBreakdown,
        ];
    }

    /**
     * Get payment analytics.
     */
    private function getPaymentAnalytics(string $dateFrom, string $dateTo): array
    {
        $paymentMethods = Order::whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('payment_status', 'paid')
            ->select('payment_method', 
                    DB::raw('count(*) as count'),
                    DB::raw('sum(total_amount) as revenue'),
                    DB::raw('avg(total_amount) as avg_amount'))
            ->groupBy('payment_method')
            ->get();

        $paymentStatusBreakdown = Order::whereBetween('created_at', [$dateFrom, $dateTo])
            ->select('payment_status', DB::raw('count(*) as count'))
            ->groupBy('payment_status')
            ->pluck('count', 'payment_status')
            ->toArray();

        return [
            'payment_methods' => $paymentMethods,
            'payment_status_breakdown' => $paymentStatusBreakdown,
        ];
    }

    /**
     * Get best selling products.
     */
    private function getBestSellingProducts(string $dateFrom, string $dateTo, int $limit): array
    {
        return OrderItem::with(['product', 'product.brand', 'product.category'])
            ->whereHas('order', function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('created_at', [$dateFrom, $dateTo])
                      ->where('payment_status', 'paid');
            })
            ->select('product_id', 
                    DB::raw('sum(quantity) as total_sold'),
                    DB::raw('sum(unit_price * quantity) as total_revenue'),
                    DB::raw('count(distinct order_id) as order_count'),
                    DB::raw('avg(unit_price) as avg_price'))
            ->groupBy('product_id')
            ->orderBy('total_sold', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get slow moving products.
     */
    private function getSlowMovingProducts(string $dateFrom, string $dateTo, int $limit): array
    {
        return Product::with(['brand', 'category'])
            ->leftJoin('order_items', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('orders', function ($join) use ($dateFrom, $dateTo) {
                $join->on('order_items.order_id', '=', 'orders.id')
                     ->whereBetween('orders.created_at', [$dateFrom, $dateTo])
                     ->where('orders.payment_status', '=', 'paid');
            })
            ->select('products.id',
                    'products.sku',
                    'products.name',
                    'products.current_price',
                    'products.stock_quantity',
                    'products.brand_id',
                    'products.category_id',
                    DB::raw('COALESCE(sum(order_items.quantity), 0) as total_sold'),
                    DB::raw('products.stock_quantity as current_stock'))
            ->where('products.status', 'active')
            ->where('products.is_preorder', false)
            ->where('products.stock_quantity', '>', 0)
            ->groupBy('products.id', 'products.sku', 'products.name', 'products.current_price', 'products.stock_quantity', 'products.brand_id', 'products.category_id')
            ->orderBy('total_sold', 'asc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get inventory turnover analysis.
     */
    private function getInventoryTurnover(string $dateFrom, string $dateTo, int $limit): array
    {
        $products = Product::with(['brand', 'category'])
            ->where('status', 'active')
            ->where('is_preorder', false)
            ->get();

        $result = [];
        
        foreach ($products as $product) {
            $totalSold = OrderItem::whereHas('order', function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('created_at', [$dateFrom, $dateTo])
                      ->where('payment_status', 'paid');
            })
            ->where('product_id', $product->id)
            ->sum('quantity');

            $turnoverRate = $product->stock_quantity > 0 
                ? $totalSold / $product->stock_quantity 
                : 0;

            $result[] = array_merge($product->toArray(), [
                'total_sold' => $totalSold,
                'turnover_rate' => $turnoverRate,
                'inventory_value' => $product->stock_quantity * $product->current_price,
            ]);
        }

        // Sort by turnover rate descending
        usort($result, function ($a, $b) {
            return $b['turnover_rate'] <=> $a['turnover_rate'];
        });

        return array_slice($result, 0, $limit);
    }

    /**
     * Get category performance.
     */
    private function getCategoryPerformance(string $dateFrom, string $dateTo): array
    {
        return OrderItem::whereHas('order', function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('created_at', [$dateFrom, $dateTo])
                      ->where('payment_status', 'paid');
            })
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select('categories.name as category_name',
                    'categories.id as category_id',
                    DB::raw('sum(order_items.quantity) as total_sold'),
                    DB::raw('sum(order_items.unit_price * order_items.quantity) as total_revenue'),
                    DB::raw('count(distinct order_items.order_id) as order_count'),
                    DB::raw('avg(order_items.unit_price) as avg_price'))
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('total_revenue', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get brand performance.
     */
    private function getBrandPerformance(string $dateFrom, string $dateTo): array
    {
        return OrderItem::whereHas('order', function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('created_at', [$dateFrom, $dateTo])
                      ->where('payment_status', 'paid');
            })
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('brands', 'products.brand_id', '=', 'brands.id')
            ->select('brands.name as brand_name',
                    'brands.id as brand_id',
                    DB::raw('sum(order_items.quantity) as total_sold'),
                    DB::raw('sum(order_items.unit_price * order_items.quantity) as total_revenue'),
                    DB::raw('count(distinct order_items.order_id) as order_count'),
                    DB::raw('avg(order_items.unit_price) as avg_price'))
            ->groupBy('brands.id', 'brands.name')
            ->orderBy('total_revenue', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get price analysis.
     */
    private function getPriceAnalysis(string $dateFrom, string $dateTo): array
    {
        // Use simpler approach for SQLite compatibility
        $orderItems = OrderItem::whereHas('order', function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('created_at', [$dateFrom, $dateTo])
                      ->where('payment_status', 'paid');
            })
            ->get();

        $priceRanges = [
            'Under ₱500' => ['count' => 0, 'revenue' => 0],
            '₱500-₱999' => ['count' => 0, 'revenue' => 0],
            '₱1000-₱1999' => ['count' => 0, 'revenue' => 0],
            '₱2000-₱4999' => ['count' => 0, 'revenue' => 0],
            '₱5000+' => ['count' => 0, 'revenue' => 0],
        ];

        foreach ($orderItems as $item) {
            $price = $item->unit_price;
            $revenue = $price * $item->quantity;
            
            if ($price < 500) {
                $priceRanges['Under ₱500']['count']++;
                $priceRanges['Under ₱500']['revenue'] += $revenue;
            } elseif ($price < 1000) {
                $priceRanges['₱500-₱999']['count']++;
                $priceRanges['₱500-₱999']['revenue'] += $revenue;
            } elseif ($price < 2000) {
                $priceRanges['₱1000-₱1999']['count']++;
                $priceRanges['₱1000-₱1999']['revenue'] += $revenue;
            } elseif ($price < 5000) {
                $priceRanges['₱2000-₱4999']['count']++;
                $priceRanges['₱2000-₱4999']['revenue'] += $revenue;
            } else {
                $priceRanges['₱5000+']['count']++;
                $priceRanges['₱5000+']['revenue'] += $revenue;
            }
        }

        return [
            'price_ranges' => array_map(function ($range, $key) {
                return [
                    'price_range' => $key,
                    'count' => $range['count'],
                    'revenue' => $range['revenue']
                ];
            }, $priceRanges, array_keys($priceRanges)),
            'avg_selling_price' => $orderItems->avg('unit_price') ?? 0,
        ];
    }

    /**
     * Get customer acquisition metrics.
     */
    private function getCustomerAcquisition(string $dateFrom, string $dateTo): array
    {
        $newCustomers = User::whereBetween('created_at', [$dateFrom, $dateTo])->count();
        $totalCustomers = User::count();

        return [
            'new_customers' => $newCustomers,
            'total_customers' => $totalCustomers,
            'growth_rate' => $totalCustomers > $newCustomers ? 
                round(($newCustomers / ($totalCustomers - $newCustomers)) * 100, 2) : 0,
        ];
    }

    /**
     * Get customer retention metrics.
     */
    private function getCustomerRetention(string $dateFrom, string $dateTo): array
    {
        $returningCustomers = User::whereHas('orders', function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('created_at', [$dateFrom, $dateTo]);
            })
            ->whereHas('orders', function ($query) use ($dateFrom) {
                $query->where('created_at', '<', $dateFrom);
            })
            ->count();

        $totalCustomersWithOrders = User::whereHas('orders')->count();
        $retentionRate = $totalCustomersWithOrders > 0 ? 
            round(($returningCustomers / $totalCustomersWithOrders) * 100, 2) : 0;

        return [
            'returning_customers' => $returningCustomers,
            'retention_rate' => $retentionRate,
        ];
    }

    /**
     * Get customer lifetime value.
     */
    private function getCustomerLifetimeValue(string $dateFrom, string $dateTo): array
    {
        // Calculate average lifetime value using a subquery
        $avgLifetimeValue = DB::table(DB::raw('(SELECT user_id, SUM(total_amount) as total FROM orders WHERE payment_status = "paid" GROUP BY user_id) as order_totals'))
            ->select(DB::raw('AVG(order_totals.total) as avg_value'))
            ->value('avg_value') ?? 0;

        $topCustomers = User::whereHas('orders', function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('created_at', [$dateFrom, $dateTo])
                      ->where('payment_status', 'paid');
            })
            ->withSum(['orders' => function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('created_at', [$dateFrom, $dateTo])
                      ->where('payment_status', 'paid');
            }], 'total_amount')
            ->get()
            ->sortByDesc('orders_sum_total_amount')
            ->take(10)
            ->values();

        return [
            'avg_lifetime_value' => round($avgLifetimeValue, 2),
            'top_customers' => $topCustomers,
        ];
    }

    /**
     * Get loyalty analysis.
     */
    private function getLoyaltyAnalysis(string $dateFrom, string $dateTo): array
    {
        $loyaltyTierDistribution = User::select('loyalty_tier', DB::raw('count(*) as count'))
            ->groupBy('loyalty_tier')
            ->pluck('count', 'loyalty_tier')
            ->toArray();

        $creditsEarned = LoyaltyTransaction::whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('transaction_type', 'earned')
            ->sum('amount');

        $creditsRedeemed = LoyaltyTransaction::whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('transaction_type', 'redeemed')
            ->sum('amount');

        return [
            'tier_distribution' => $loyaltyTierDistribution,
            'credits_earned' => round($creditsEarned, 2),
            'credits_redeemed' => round(abs($creditsRedeemed), 2),
            'utilization_rate' => $creditsEarned > 0 ? 
                round((abs($creditsRedeemed) / $creditsEarned) * 100, 2) : 0,
        ];
    }

    /**
     * Get customer segmentation.
     */
    private function getCustomerSegmentation(string $dateFrom, string $dateTo): array
    {
        // Segment customers by order frequency and value
        $segments = User::whereHas('orders', function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('created_at', [$dateFrom, $dateTo]);
            })
            ->withCount(['orders' => function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('created_at', [$dateFrom, $dateTo]);
            }])
            ->withSum(['orders' => function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('created_at', [$dateFrom, $dateTo])
                      ->where('payment_status', 'paid');
            }], 'total_amount')
            ->get()
            ->groupBy(function ($user) {
                $orderCount = $user->orders_count;
                $totalSpent = $user->orders_sum_total_amount ?? 0;

                if ($orderCount >= 5 && $totalSpent >= 10000) {
                    return 'VIP';
                } elseif ($orderCount >= 3 && $totalSpent >= 5000) {
                    return 'Loyal';
                } elseif ($orderCount >= 2) {
                    return 'Regular';
                } else {
                    return 'New';
                }
            })
            ->map(function ($group) {
                return $group->count();
            })
            ->toArray();

        return [
            'segments' => $segments,
        ];
    }

    /**
     * Get revenue time series.
     */
    private function getRevenueTimeSeries(string $dateFrom, string $dateTo, string $period): array
    {
        // Use database-agnostic date formatting
        $selectClause = match ($period) {
            'daily' => "DATE(created_at) as period",
            'weekly' => "strftime('%Y-%W', created_at) as period", // SQLite format
            'monthly' => "strftime('%Y-%m', created_at) as period", // SQLite format
            default => "DATE(created_at) as period",
        };

        // For MySQL, use different format
        if (config('database.default') === 'mysql') {
            $selectClause = match ($period) {
                'daily' => "DATE(created_at) as period",
                'weekly' => "DATE_FORMAT(created_at, '%Y-%u') as period",
                'monthly' => "DATE_FORMAT(created_at, '%Y-%m') as period",
                default => "DATE(created_at) as period",
            };
        }

        return Order::whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('payment_status', 'paid')
            ->select(
                DB::raw($selectClause),
                DB::raw('sum(total_amount) as revenue'),
                DB::raw('count(*) as orders'),
                DB::raw('avg(total_amount) as avg_order_value')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->toArray();
    }

    /**
     * Get growth comparison with previous period.
     */
    private function getGrowthComparison(string $dateFrom, string $dateTo): array
    {
        $currentRevenue = Order::whereBetween('created_at', [$dateFrom, $dateTo])
                              ->where('payment_status', 'paid')
                              ->sum('total_amount');

        $currentOrders = Order::whereBetween('created_at', [$dateFrom, $dateTo])->count();

        // Calculate previous period
        $from = Carbon::parse($dateFrom);
        $to = Carbon::parse($dateTo);
        $diff = $from->diffInDays($to);
        
        $previousFrom = $from->copy()->subDays($diff + 1);
        $previousTo = $from->copy()->subDay();

        $previousRevenue = Order::whereBetween('created_at', [$previousFrom, $previousTo])
                               ->where('payment_status', 'paid')
                               ->sum('total_amount');

        $previousOrders = Order::whereBetween('created_at', [$previousFrom, $previousTo])->count();

        $revenueGrowth = $previousRevenue > 0 ? 
            round((($currentRevenue - $previousRevenue) / $previousRevenue) * 100, 2) : 0;

        $orderGrowth = $previousOrders > 0 ? 
            round((($currentOrders - $previousOrders) / $previousOrders) * 100, 2) : 0;

        return [
            'current_period' => [
                'revenue' => round($currentRevenue, 2),
                'orders' => $currentOrders,
            ],
            'previous_period' => [
                'revenue' => round($previousRevenue, 2),
                'orders' => $previousOrders,
            ],
            'growth' => [
                'revenue_growth' => $revenueGrowth,
                'order_growth' => $orderGrowth,
            ],
        ];
    }
}