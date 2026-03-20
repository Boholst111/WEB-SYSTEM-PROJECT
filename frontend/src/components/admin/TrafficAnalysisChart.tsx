import React, { useState } from 'react';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell } from 'recharts';
import type { TrafficAnalysis } from '../../services/adminApi';

interface TrafficAnalysisChartProps {
  data: TrafficAnalysis;
}

const TrafficAnalysisChart: React.FC<TrafficAnalysisChartProps> = ({ data }) => {
  const [activeTab, setActiveTab] = useState<'popular_products' | 'device_types' | 'peak_hours'>('popular_products');

  // Prepare device types data for pie chart
  const deviceTypesData = Object.entries(data.device_types).map(([device, count]) => ({
    name: device.charAt(0).toUpperCase() + device.slice(1),
    value: Math.round(count),
    percentage: ((count / data.summary.estimated_visitors) * 100).toFixed(1)
  }));

  // Prepare peak hours data for bar chart
  const peakHoursData = data.peak_hours.map(hour => ({
    hour: `${hour.hour}:00`,
    count: hour.count
  }));

  // Prepare popular products data
  const popularProductsData = data.popular_products.map(product => ({
    name: product.name.length > 20 ? `${product.name.substring(0, 20)}...` : product.name,
    fullName: product.name,
    sku: product.sku,
    view_count: product.view_count
  }));

  const COLORS = ['#3b82f6', '#10b981', '#f59e0b'];

  return (
    <div className="space-y-6">
      {/* Summary Cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div className="bg-blue-50 rounded-lg p-4">
          <p className="text-sm font-medium text-blue-600">Estimated Visitors</p>
          <p className="text-2xl font-bold text-blue-900">
            {data.summary.estimated_visitors.toLocaleString()}
          </p>
        </div>
        <div className="bg-green-50 rounded-lg p-4">
          <p className="text-sm font-medium text-green-600">Conversion Rate</p>
          <p className="text-2xl font-bold text-green-900">
            {data.summary.conversion_rate}%
          </p>
        </div>
        <div className="bg-red-50 rounded-lg p-4">
          <p className="text-sm font-medium text-red-600">Cart Abandonment</p>
          <p className="text-2xl font-bold text-red-900">
            {data.summary.cart_abandonment_rate}%
          </p>
        </div>
        <div className="bg-purple-50 rounded-lg p-4">
          <p className="text-sm font-medium text-purple-600">Avg. Session</p>
          <p className="text-2xl font-bold text-purple-900">
            {data.summary.avg_session_duration}
          </p>
        </div>
      </div>

      {/* Tab Navigation */}
      <div className="border-b border-gray-200">
        <nav className="-mb-px flex space-x-8">
          <button
            onClick={() => setActiveTab('popular_products')}
            className={`py-2 px-1 border-b-2 font-medium text-sm ${
              activeTab === 'popular_products'
                ? 'border-blue-500 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            Popular Products
          </button>
          <button
            onClick={() => setActiveTab('device_types')}
            className={`py-2 px-1 border-b-2 font-medium text-sm ${
              activeTab === 'device_types'
                ? 'border-blue-500 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            Device Types
          </button>
          <button
            onClick={() => setActiveTab('peak_hours')}
            className={`py-2 px-1 border-b-2 font-medium text-sm ${
              activeTab === 'peak_hours'
                ? 'border-blue-500 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            Peak Hours
          </button>
        </nav>
      </div>

      {/* Chart Content */}
      <div className="h-80">
        {activeTab === 'popular_products' && (
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={popularProductsData} layout="horizontal">
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis type="number" />
              <YAxis 
                type="category" 
                dataKey="name" 
                tick={{ fontSize: 10 }}
                width={120}
              />
              <Tooltip 
                formatter={(value: number) => [value, 'Views']}
                labelFormatter={(label, payload) => {
                  const item = payload?.[0]?.payload;
                  return item ? `${item.fullName} (${item.sku})` : label;
                }}
              />
              <Bar dataKey="view_count" fill="#3b82f6" />
            </BarChart>
          </ResponsiveContainer>
        )}

        {activeTab === 'device_types' && (
          <ResponsiveContainer width="100%" height="100%">
            <PieChart>
              <Pie
                data={deviceTypesData}
                cx="50%"
                cy="50%"
                labelLine={false}
                label={({ name, percentage }) => `${name} (${percentage}%)`}
                outerRadius={120}
                fill="#8884d8"
                dataKey="value"
              >
                {deviceTypesData.map((entry, index) => (
                  <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                ))}
              </Pie>
              <Tooltip formatter={(value: number) => [value, 'Visitors']} />
            </PieChart>
          </ResponsiveContainer>
        )}

        {activeTab === 'peak_hours' && (
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={peakHoursData}>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis dataKey="hour" />
              <YAxis />
              <Tooltip formatter={(value: number) => [value, 'Orders']} />
              <Bar dataKey="count" fill="#10b981" />
            </BarChart>
          </ResponsiveContainer>
        )}
      </div>
    </div>
  );
};

export default TrafficAnalysisChart;