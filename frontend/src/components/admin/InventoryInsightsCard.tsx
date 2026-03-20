import React from 'react';
import { 
  CubeIcon, 
  ExclamationTriangleIcon, 
  ClockIcon,
  CheckCircleIcon 
} from '@heroicons/react/24/outline';
import type { InventoryInsights } from '../../services/adminApi';

interface InventoryInsightsCardProps {
  data: InventoryInsights;
}

const InventoryInsightsCard: React.FC<InventoryInsightsCardProps> = ({ data }) => {
  const formatCurrency = (value: number) => `₱${value.toLocaleString()}`;

  return (
    <div className="bg-white rounded-lg shadow">
      <div className="px-6 py-4 border-b border-gray-200">
        <h2 className="text-lg font-semibold text-gray-900 flex items-center">
          <CubeIcon className="h-5 w-5 mr-2 text-blue-600" />
          Inventory Insights
        </h2>
      </div>
      <div className="p-6 space-y-6">
        {/* Summary Stats */}
        <div className="grid grid-cols-2 gap-4">
          <div className="bg-red-50 rounded-lg p-4">
            <div className="flex items-center">
              <ExclamationTriangleIcon className="h-8 w-8 text-red-600 mr-3" />
              <div>
                <p className="text-sm font-medium text-red-600">Out of Stock</p>
                <p className="text-xl font-bold text-red-900">
                  {data.out_of_stock_count}
                </p>
              </div>
            </div>
          </div>

          <div className="bg-green-50 rounded-lg p-4">
            <div className="flex items-center">
              <CubeIcon className="h-8 w-8 text-green-600 mr-3" />
              <div>
                <p className="text-sm font-medium text-green-600">Inventory Value</p>
                <p className="text-xl font-bold text-green-900">
                  {formatCurrency(data.total_inventory_value)}
                </p>
              </div>
            </div>
          </div>
        </div>

        {/* Pre-order Stats */}
        <div>
          <h3 className="text-md font-medium text-gray-900 mb-4">Pre-order Status</h3>
          <div className="grid grid-cols-3 gap-3">
            <div className="bg-blue-50 rounded-lg p-3 text-center">
              <p className="text-sm font-medium text-blue-600">Total</p>
              <p className="text-lg font-bold text-blue-900">
                {data.preorder_stats.total_preorders}
              </p>
            </div>
            <div className="bg-yellow-50 rounded-lg p-3 text-center">
              <div className="flex items-center justify-center mb-1">
                <ClockIcon className="h-4 w-4 text-yellow-600 mr-1" />
              </div>
              <p className="text-sm font-medium text-yellow-600">Pending</p>
              <p className="text-lg font-bold text-yellow-900">
                {data.preorder_stats.pending_arrivals}
              </p>
            </div>
            <div className="bg-red-50 rounded-lg p-3 text-center">
              <div className="flex items-center justify-center mb-1">
                <ExclamationTriangleIcon className="h-4 w-4 text-red-600 mr-1" />
              </div>
              <p className="text-sm font-medium text-red-600">Overdue</p>
              <p className="text-lg font-bold text-red-900">
                {data.preorder_stats.overdue_arrivals}
              </p>
            </div>
          </div>
        </div>

        {/* Low Stock Products */}
        <div>
          <h3 className="text-md font-medium text-gray-900 mb-4">Low Stock Alert</h3>
          <div className="space-y-3 max-h-64 overflow-y-auto">
            {data.low_stock_products.length === 0 ? (
              <div className="text-center py-4">
                <CheckCircleIcon className="h-12 w-12 text-green-500 mx-auto mb-2" />
                <p className="text-sm text-gray-500">All products are well stocked!</p>
              </div>
            ) : (
              data.low_stock_products.map((product) => (
                <div key={product.id} className="flex items-center justify-between p-3 bg-red-50 rounded-lg border border-red-200">
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-gray-900 truncate">
                      {product.name}
                    </p>
                    <div className="flex items-center space-x-2 mt-1">
                      <span className="text-xs text-gray-500">SKU: {product.sku}</span>
                      <span className="text-xs text-gray-400">•</span>
                      <span className="text-xs text-gray-500">{product.brand.name}</span>
                      <span className="text-xs text-gray-400">•</span>
                      <span className="text-xs text-gray-500">{product.category.name}</span>
                    </div>
                  </div>
                  <div className="flex-shrink-0 ml-4">
                    <div className="flex items-center">
                      <ExclamationTriangleIcon className="h-4 w-4 text-red-500 mr-1" />
                      <span className={`text-sm font-medium ${
                        product.stock_quantity === 0 
                          ? 'text-red-700' 
                          : product.stock_quantity <= 2 
                            ? 'text-red-600' 
                            : 'text-orange-600'
                      }`}>
                        {product.stock_quantity} left
                      </span>
                    </div>
                  </div>
                </div>
              ))
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

export default InventoryInsightsCard;