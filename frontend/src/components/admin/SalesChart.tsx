import React from 'react';
import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  BarChart,
  Bar
} from 'recharts';
import { ArrowTrendingUpIcon, ArrowTrendingDownIcon } from '@heroicons/react/24/outline';
import type { SalesMetrics } from '../../services/adminApi';

interface SalesChartProps {
  data: SalesMetrics;
}

const SalesChart: React.FC<SalesChartProps> = ({ data }) => {
  const formatCurrency = (value: number) => `₱${(value || 0).toLocaleString()}`;
  const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  };

  const isPositiveGrowth = (data?.growth_rate || 0) >= 0;

  return (
    <div className="space-y-6">
      {/* Summary Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div className="bg-blue-50 rounded-lg p-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-blue-600">Total Revenue</p>
              <p className="text-2xl font-bold text-blue-900">
                {formatCurrency(data?.total_revenue || 0)}
              </p>
            </div>
            <div className={`flex items-center ${isPositiveGrowth ? 'text-green-600' : 'text-red-600'}`}>
              {isPositiveGrowth ? (
                <ArrowTrendingUpIcon className="h-5 w-5 mr-1" />
              ) : (
                <ArrowTrendingDownIcon className="h-5 w-5 mr-1" />
              )}
              <span className="text-sm font-medium">
                {Math.abs(data?.growth_rate || 0).toFixed(1)}%
              </span>
            </div>
          </div>
        </div>

        <div className="bg-green-50 rounded-lg p-4">
          <div>
            <p className="text-sm font-medium text-green-600">Total Orders</p>
            <p className="text-2xl font-bold text-green-900">
              {(data?.total_orders || 0).toLocaleString()}
            </p>
          </div>
        </div>

        <div className="bg-purple-50 rounded-lg p-4">
          <div>
            <p className="text-sm font-medium text-purple-600">Average Order Value</p>
            <p className="text-2xl font-bold text-purple-900">
              {formatCurrency(data?.average_order_value || 0)}
            </p>
          </div>
        </div>

        <div className="bg-orange-50 rounded-lg p-4">
          <div>
            <p className="text-sm font-medium text-orange-600">Conversion Rate</p>
            <p className="text-2xl font-bold text-orange-900">
              {(data?.conversion_rate || 0).toFixed(1)}%
            </p>
          </div>
        </div>
      </div>

      {/* Revenue Trend Chart */}
      <div>
        <h3 className="text-lg font-medium text-gray-900 mb-4">Revenue Trend</h3>
        <div className="h-80">
          <ResponsiveContainer width="100%" height="100%">
            <LineChart data={data?.revenue_by_period || []}>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis 
                dataKey="date" 
                tickFormatter={formatDate}
                tick={{ fontSize: 12 }}
              />
              <YAxis 
                tickFormatter={(value) => `₱${(value / 1000).toFixed(0)}k`}
                tick={{ fontSize: 12 }}
              />
              <Tooltip 
                formatter={(value: number) => [formatCurrency(value), 'Revenue']}
                labelFormatter={(label) => `Date: ${formatDate(label)}`}
              />
              <Line 
                type="monotone" 
                dataKey="revenue" 
                stroke="#2563eb" 
                strokeWidth={2}
                dot={{ fill: '#2563eb', strokeWidth: 2, r: 4 }}
                activeDot={{ r: 6 }}
              />
            </LineChart>
          </ResponsiveContainer>
        </div>
      </div>

      {/* Orders Trend Chart */}
      <div>
        <h3 className="text-lg font-medium text-gray-900 mb-4">Orders Trend</h3>
        <div className="h-80">
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={data?.revenue_by_period || []}>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis 
                dataKey="date" 
                tickFormatter={formatDate}
                tick={{ fontSize: 12 }}
              />
              <YAxis tick={{ fontSize: 12 }} />
              <Tooltip 
                formatter={(value: number) => [value, 'Orders']}
                labelFormatter={(label) => `Date: ${formatDate(label)}`}
              />
              <Bar 
                dataKey="orders" 
                fill="#10b981"
                radius={[2, 2, 0, 0]}
              />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </div>
    </div>
  );
};

export default SalesChart;