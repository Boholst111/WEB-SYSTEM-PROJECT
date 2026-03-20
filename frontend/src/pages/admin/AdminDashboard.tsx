import React, { useState, useEffect } from 'react';
import { 
  ChartBarIcon, 
  ShoppingCartIcon, 
  CubeIcon, 
  UsersIcon,
  ArrowTrendingUpIcon,
  ArrowTrendingDownIcon,
  ExclamationTriangleIcon,
  ClockIcon
} from '@heroicons/react/24/outline';
import { analyticsApi, type DashboardData, type RealTimeSummary } from '../../services/adminApi';
import SalesChart from '../../components/admin/SalesChart';
import ProductPerformanceChart from '../../components/admin/ProductPerformanceChart';
import CustomerAnalyticsChart from '../../components/admin/CustomerAnalyticsChart';
import TrafficAnalysisChart from '../../components/admin/TrafficAnalysisChart';
import LoyaltyMetricsCard from '../../components/admin/LoyaltyMetricsCard';
import InventoryInsightsCard from '../../components/admin/InventoryInsightsCard';
import DateRangePicker from '../../components/admin/DateRangePicker';

const AdminDashboard: React.FC = () => {
  const [dashboardData, setDashboardData] = useState<DashboardData | null>(null);
  const [realTimeSummary, setRealTimeSummary] = useState<RealTimeSummary | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [dateRange, setDateRange] = useState({
    from: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    to: new Date().toISOString().split('T')[0],
    period: 'daily' as 'daily' | 'weekly' | 'monthly'
  });

  const fetchDashboardData = async () => {
    try {
      setIsLoading(true);
      setError(null);

      const [dashboardResponse, realTimeResponse] = await Promise.all([
        analyticsApi.getDashboard({
          date_from: dateRange.from,
          date_to: dateRange.to,
          period: dateRange.period
        }),
        analyticsApi.getRealTimeSummary()
      ]);

      if (dashboardResponse.success) {
        setDashboardData(dashboardResponse.data);
      }

      if (realTimeResponse.success) {
        setRealTimeSummary(realTimeResponse.data);
      }
    } catch (err) {
      setError('Failed to load dashboard data');
      console.error('Dashboard error:', err);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchDashboardData();
  }, [dateRange]);

  // Auto-refresh real-time data every 30 seconds
  useEffect(() => {
    const interval = setInterval(async () => {
      try {
        const response = await analyticsApi.getRealTimeSummary();
        if (response.success) {
          setRealTimeSummary(response.data);
        }
      } catch (err) {
        console.error('Real-time update error:', err);
      }
    }, 30000);

    return () => clearInterval(interval);
  }, []);

  const handleDateRangeChange = (newDateRange: typeof dateRange) => {
    setDateRange(newDateRange);
  };

  if (isLoading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="animate-spin rounded-full h-32 w-32 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <ExclamationTriangleIcon className="h-12 w-12 text-red-500 mx-auto mb-4" />
          <h2 className="text-xl font-semibold text-gray-900 mb-2">Error Loading Dashboard</h2>
          <p className="text-gray-600 mb-4">{error}</p>
          <button
            onClick={fetchDashboardData}
            className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700"
          >
            Retry
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Header */}
        <div className="mb-8">
          <div className="flex justify-between items-center">
            <div>
              <h1 className="text-3xl font-bold text-gray-900">Admin Dashboard</h1>
              <p className="text-gray-600 mt-1">
                Overview of your Diecast Empire platform performance
              </p>
            </div>
            <DateRangePicker
              dateRange={dateRange}
              onChange={handleDateRangeChange}
            />
          </div>
        </div>

        {/* Real-time Summary Cards */}
        {realTimeSummary && (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-6 mb-8">
            <div className="bg-white rounded-lg shadow p-6">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <ShoppingCartIcon className="h-8 w-8 text-blue-600" />
                </div>
                <div className="ml-4">
                  <p className="text-sm font-medium text-gray-500">Today's Orders</p>
                  <p className="text-2xl font-semibold text-gray-900">
                    {realTimeSummary.today_orders}
                  </p>
                </div>
              </div>
            </div>

            <div className="bg-white rounded-lg shadow p-6">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <ArrowTrendingUpIcon className="h-8 w-8 text-green-600" />
                </div>
                <div className="ml-4">
                  <p className="text-sm font-medium text-gray-500">Today's Revenue</p>
                  <p className="text-2xl font-semibold text-gray-900">
                    ₱{(realTimeSummary?.today_revenue || 0).toLocaleString()}
                  </p>
                </div>
              </div>
            </div>

            <div className="bg-white rounded-lg shadow p-6">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <ClockIcon className="h-8 w-8 text-yellow-600" />
                </div>
                <div className="ml-4">
                  <p className="text-sm font-medium text-gray-500">Pending Orders</p>
                  <p className="text-2xl font-semibold text-gray-900">
                    {realTimeSummary.pending_orders}
                  </p>
                </div>
              </div>
            </div>

            <div className="bg-white rounded-lg shadow p-6">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <ExclamationTriangleIcon className="h-8 w-8 text-red-600" />
                </div>
                <div className="ml-4">
                  <p className="text-sm font-medium text-gray-500">Low Stock</p>
                  <p className="text-2xl font-semibold text-gray-900">
                    {realTimeSummary.low_stock_products}
                  </p>
                </div>
              </div>
            </div>

            <div className="bg-white rounded-lg shadow p-6">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <UsersIcon className="h-8 w-8 text-purple-600" />
                </div>
                <div className="ml-4">
                  <p className="text-sm font-medium text-gray-500">Active Users</p>
                  <p className="text-2xl font-semibold text-gray-900">
                    {realTimeSummary.active_users_today}
                  </p>
                </div>
              </div>
            </div>

            <div className="bg-white rounded-lg shadow p-6">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <ChartBarIcon className="h-8 w-8 text-indigo-600" />
                </div>
                <div className="ml-4">
                  <p className="text-sm font-medium text-gray-500">Conversion Rate</p>
                  <p className="text-2xl font-semibold text-gray-900">
                    {(realTimeSummary?.conversion_rate_today || 0).toFixed(1)}%
                  </p>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Main Dashboard Content */}
        {dashboardData && (
          <div className="space-y-8">
            {/* Sales Analytics */}
            <div className="bg-white rounded-lg shadow">
              <div className="px-6 py-4 border-b border-gray-200">
                <h2 className="text-lg font-semibold text-gray-900">Sales Analytics</h2>
              </div>
              <div className="p-6">
                <SalesChart data={dashboardData.sales_analytics} />
              </div>
            </div>

            {/* Product Performance and Customer Analytics */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
              <div className="bg-white rounded-lg shadow">
                <div className="px-6 py-4 border-b border-gray-200">
                  <h2 className="text-lg font-semibold text-gray-900">Product Performance</h2>
                </div>
                <div className="p-6">
                  <ProductPerformanceChart data={dashboardData.product_analytics} />
                </div>
              </div>

              <div className="bg-white rounded-lg shadow">
                <div className="px-6 py-4 border-b border-gray-200">
                  <h2 className="text-lg font-semibold text-gray-900">Customer Analytics</h2>
                </div>
                <div className="p-6">
                  <CustomerAnalyticsChart data={dashboardData.customer_analytics} />
                </div>
              </div>
            </div>

            {/* Traffic Analysis */}
            <div className="bg-white rounded-lg shadow">
              <div className="px-6 py-4 border-b border-gray-200">
                <h2 className="text-lg font-semibold text-gray-900">Traffic Analysis</h2>
              </div>
              <div className="p-6">
                <TrafficAnalysisChart data={dashboardData.traffic_analysis} />
              </div>
            </div>

            {/* Loyalty Metrics and Inventory Insights */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
              <LoyaltyMetricsCard data={dashboardData.loyalty_metrics} />
              <InventoryInsightsCard data={dashboardData.inventory_insights} />
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default AdminDashboard;