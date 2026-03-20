import React, { useState } from 'react';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import type { ProductPerformance } from '../../services/adminApi';

interface ProductPerformanceChartProps {
  data: ProductPerformance;
}

const ProductPerformanceChart: React.FC<ProductPerformanceChartProps> = ({ data }) => {
  const [activeTab, setActiveTab] = useState<'best_sellers' | 'slow_movers'>('best_sellers');

  const formatCurrency = (value: number) => `₱${value.toLocaleString()}`;

  const bestSellersData = (data?.best_sellers || []).map(product => ({
    name: product.name.length > 20 ? `${product.name.substring(0, 20)}...` : product.name,
    fullName: product.name,
    sku: product.sku,
    total_sold: product.total_sold,
    revenue: product.revenue
  }));

  const slowMoversData = (data?.slow_movers || []).map(product => ({
    name: product.name.length > 20 ? `${product.name.substring(0, 20)}...` : product.name,
    fullName: product.name,
    sku: product.sku,
    stock_quantity: product.stock_quantity,
    days_since_last_sale: product.days_since_last_sale
  }));

  return (
    <div className="space-y-6">
      {/* Summary Stats */}
      <div className="bg-gray-50 rounded-lg p-4">
        <div className="text-center">
          <p className="text-sm font-medium text-gray-600">Inventory Turnover Rate</p>
          <p className="text-2xl font-bold text-gray-900">
            {(typeof data?.inventory_turnover === 'number' ? data.inventory_turnover : 0).toFixed(1)}x
          </p>
        </div>
      </div>

      {/* Tab Navigation */}
      <div className="border-b border-gray-200">
        <nav className="-mb-px flex space-x-8">
          <button
            onClick={() => setActiveTab('best_sellers')}
            className={`py-2 px-1 border-b-2 font-medium text-sm ${
              activeTab === 'best_sellers'
                ? 'border-blue-500 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            Best Sellers
          </button>
          <button
            onClick={() => setActiveTab('slow_movers')}
            className={`py-2 px-1 border-b-2 font-medium text-sm ${
              activeTab === 'slow_movers'
                ? 'border-blue-500 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            Slow Movers
          </button>
        </nav>
      </div>

      {/* Chart Content */}
      {activeTab === 'best_sellers' ? (
        <div>
          <h4 className="text-md font-medium text-gray-900 mb-4">Top Selling Products</h4>
          <div className="h-80">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={bestSellersData} layout="horizontal">
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis type="number" tick={{ fontSize: 12 }} />
                <YAxis 
                  type="category" 
                  dataKey="name" 
                  tick={{ fontSize: 10 }}
                  width={120}
                />
                <Tooltip 
                  formatter={(value: number, name: string) => [
                    name === 'total_sold' ? value : formatCurrency(value),
                    name === 'total_sold' ? 'Units Sold' : 'Revenue'
                  ]}
                  labelFormatter={(label, payload) => {
                    const item = payload?.[0]?.payload;
                    return item ? `${item.fullName} (${item.sku})` : label;
                  }}
                />
                <Bar dataKey="total_sold" fill="#3b82f6" />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>
      ) : (
        <div>
          <h4 className="text-md font-medium text-gray-900 mb-4">Slow Moving Products</h4>
          <div className="h-80">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={slowMoversData} layout="horizontal">
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis type="number" tick={{ fontSize: 12 }} />
                <YAxis 
                  type="category" 
                  dataKey="name" 
                  tick={{ fontSize: 10 }}
                  width={120}
                />
                <Tooltip 
                  formatter={(value: number, name: string) => [
                    value,
                    name === 'stock_quantity' ? 'Stock Quantity' : 'Days Since Last Sale'
                  ]}
                  labelFormatter={(label, payload) => {
                    const item = payload?.[0]?.payload;
                    return item ? `${item.fullName} (${item.sku})` : label;
                  }}
                />
                <Bar dataKey="days_since_last_sale" fill="#ef4444" />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>
      )}
    </div>
  );
};

export default ProductPerformanceChart;