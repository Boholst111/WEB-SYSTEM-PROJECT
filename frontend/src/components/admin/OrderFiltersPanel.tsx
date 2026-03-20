import React, { useState } from 'react';
import { XMarkIcon, MagnifyingGlassIcon } from '@heroicons/react/24/outline';
import type { OrderFilters } from '../../services/adminApi';

interface OrderFiltersPanelProps {
  filters: OrderFilters;
  onChange: (filters: Partial<OrderFilters>) => void;
  onClose: () => void;
}

const OrderFiltersPanel: React.FC<OrderFiltersPanelProps> = ({
  filters,
  onChange,
  onClose
}) => {
  const [localFilters, setLocalFilters] = useState<OrderFilters>(filters);

  const handleApplyFilters = () => {
    onChange(localFilters);
    onClose();
  };

  const handleClearFilters = () => {
    const clearedFilters: OrderFilters = {
      per_page: 20,
      sort_by: 'created_at',
      sort_direction: 'desc',
      include_items: true
    };
    setLocalFilters(clearedFilters);
    onChange(clearedFilters);
    onClose();
  };

  const handleLocalFilterChange = (key: keyof OrderFilters, value: any) => {
    setLocalFilters(prev => ({ ...prev, [key]: value }));
  };

  return (
    <div className="bg-white rounded-lg shadow border border-gray-200">
      <div className="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
        <h3 className="text-lg font-medium text-gray-900">Filter Orders</h3>
        <button
          onClick={onClose}
          className="text-gray-400 hover:text-gray-500"
        >
          <XMarkIcon className="h-5 w-5" />
        </button>
      </div>

      <div className="p-6">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          {/* Search */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Search
            </label>
            <div className="relative">
              <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <MagnifyingGlassIcon className="h-5 w-5 text-gray-400" />
              </div>
              <input
                type="text"
                value={localFilters.search || ''}
                onChange={(e) => handleLocalFilterChange('search', e.target.value)}
                placeholder="Order number, customer name..."
                className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
          </div>

          {/* Status */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Order Status
            </label>
            <select
              value={localFilters.status || ''}
              onChange={(e) => handleLocalFilterChange('status', e.target.value || undefined)}
              className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">All Statuses</option>
              <option value="pending">Pending</option>
              <option value="confirmed">Confirmed</option>
              <option value="processing">Processing</option>
              <option value="shipped">Shipped</option>
              <option value="delivered">Delivered</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>

          {/* Payment Status */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Payment Status
            </label>
            <select
              value={localFilters.payment_status || ''}
              onChange={(e) => handleLocalFilterChange('payment_status', e.target.value || undefined)}
              className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">All Payment Statuses</option>
              <option value="pending">Pending</option>
              <option value="paid">Paid</option>
              <option value="failed">Failed</option>
              <option value="refunded">Refunded</option>
            </select>
          </div>

          {/* Sort By */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Sort By
            </label>
            <select
              value={localFilters.sort_by || 'created_at'}
              onChange={(e) => handleLocalFilterChange('sort_by', e.target.value)}
              className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="created_at">Date Created</option>
              <option value="total_amount">Total Amount</option>
              <option value="status">Status</option>
              <option value="payment_status">Payment Status</option>
            </select>
          </div>

          {/* Date From */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Date From
            </label>
            <input
              type="date"
              value={localFilters.date_from || ''}
              onChange={(e) => handleLocalFilterChange('date_from', e.target.value || undefined)}
              className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
            />
          </div>

          {/* Date To */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Date To
            </label>
            <input
              type="date"
              value={localFilters.date_to || ''}
              onChange={(e) => handleLocalFilterChange('date_to', e.target.value || undefined)}
              className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
            />
          </div>

          {/* Sort Direction */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Sort Direction
            </label>
            <select
              value={localFilters.sort_direction || 'desc'}
              onChange={(e) => handleLocalFilterChange('sort_direction', e.target.value as 'asc' | 'desc')}
              className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="desc">Newest First</option>
              <option value="asc">Oldest First</option>
            </select>
          </div>

          {/* Per Page */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Results Per Page
            </label>
            <select
              value={localFilters.per_page || 20}
              onChange={(e) => handleLocalFilterChange('per_page', parseInt(e.target.value))}
              className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
            >
              <option value={10}>10</option>
              <option value={20}>20</option>
              <option value={50}>50</option>
              <option value={100}>100</option>
            </select>
          </div>
        </div>

        {/* Actions */}
        <div className="mt-6 flex justify-end space-x-3">
          <button
            onClick={handleClearFilters}
            className="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
          >
            Clear All
          </button>
          <button
            onClick={handleApplyFilters}
            className="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
          >
            Apply Filters
          </button>
        </div>
      </div>
    </div>
  );
};

export default OrderFiltersPanel;