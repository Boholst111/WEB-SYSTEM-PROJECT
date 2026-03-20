import React from 'react';
import { PieChart, Pie, Cell, ResponsiveContainer, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip } from 'recharts';
import type { CustomerAnalytics } from '../../services/adminApi';

interface CustomerAnalyticsChartProps {
  data: CustomerAnalytics;
}

const CustomerAnalyticsChart: React.FC<CustomerAnalyticsChartProps> = ({ data }) => {
  const formatCurrency = (value: number) => `₱${value.toLocaleString()}`;

  // Prepare loyalty tier distribution data for pie chart
  const loyaltyTierData = Object.entries(data?.loyalty_tier_distribution || {}).map(([tier, count]) => ({
    name: tier.charAt(0).toUpperCase() + tier.slice(1),
    value: count,
    percentage: (data?.total_customers > 0 ? ((count / data.total_customers) * 100).toFixed(1) : '0')
  }));

  // Prepare top customers data for bar chart
  const topCustomersData = (data?.top_customers || []).map(customer => ({
    name: `${customer.name.split(' ')[0]}...`,
    fullName: customer.name,
    email: customer.email,
    total_spent: customer.total_spent,
    order_count: customer.order_count
  }));

  const COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6'];

  return (
    <div className="space-y-6">
      {/* Summary Cards */}
      <div className="grid grid-cols-2 gap-4">
        <div className="bg-blue-50 rounded-lg p-4">
          <p className="text-sm font-medium text-blue-600">Total Customers</p>
          <p className="text-2xl font-bold text-blue-900">
            {(data?.total_customers || 0).toLocaleString()}
          </p>
        </div>
        <div className="bg-green-50 rounded-lg p-4">
          <p className="text-sm font-medium text-green-600">Retention Rate</p>
          <p className="text-2xl font-bold text-green-900">
            {(data?.customer_retention_rate || 0).toFixed(1)}%
          </p>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div className="bg-purple-50 rounded-lg p-4">
          <p className="text-sm font-medium text-purple-600">New Customers</p>
          <p className="text-2xl font-bold text-purple-900">
            {(data?.new_customers || 0).toLocaleString()}
          </p>
        </div>
        <div className="bg-orange-50 rounded-lg p-4">
          <p className="text-sm font-medium text-orange-600">Returning Customers</p>
          <p className="text-2xl font-bold text-orange-900">
            {(data?.returning_customers || 0).toLocaleString()}
          </p>
        </div>
      </div>

      {/* Loyalty Tier Distribution */}
      <div>
        <h4 className="text-md font-medium text-gray-900 mb-4">Loyalty Tier Distribution</h4>
        <div className="h-64">
          <ResponsiveContainer width="100%" height="100%">
            <PieChart>
              <Pie
                data={loyaltyTierData}
                cx="50%"
                cy="50%"
                labelLine={false}
                label={({ name, percentage }) => `${name} (${percentage}%)`}
                outerRadius={80}
                fill="#8884d8"
                dataKey="value"
              >
                {loyaltyTierData.map((entry, index) => (
                  <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                ))}
              </Pie>
              <Tooltip formatter={(value: number) => [value, 'Customers']} />
            </PieChart>
          </ResponsiveContainer>
        </div>
      </div>

      {/* Top Customers */}
      <div>
        <h4 className="text-md font-medium text-gray-900 mb-4">Top Customers by Spending</h4>
        <div className="h-64">
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={topCustomersData} layout="horizontal">
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis type="number" tickFormatter={(value) => `₱${(value / 1000).toFixed(0)}k`} />
              <YAxis 
                type="category" 
                dataKey="name" 
                tick={{ fontSize: 10 }}
                width={80}
              />
              <Tooltip 
                formatter={(value: number) => [formatCurrency(value), 'Total Spent']}
                labelFormatter={(label, payload) => {
                  const item = payload?.[0]?.payload;
                  return item ? `${item.fullName} (${item.order_count} orders)` : label;
                }}
              />
              <Bar dataKey="total_spent" fill="#8b5cf6" />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </div>
    </div>
  );
};

export default CustomerAnalyticsChart;