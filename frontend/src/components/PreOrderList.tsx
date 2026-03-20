import React, { useEffect, useState } from 'react';
import { useAppDispatch, useAppSelector } from '../store';
import { fetchPreOrders, setFilters, clearFilters } from '../store/slices/preorderSlice';
import { PreOrderFilters } from '../services/preorderApi';
import PreOrderCard from './PreOrderCard';
import { 
  FunnelIcon, 
  XMarkIcon,
  MagnifyingGlassIcon 
} from '@heroicons/react/24/outline';

interface PreOrderListProps {
  onPayDeposit?: (preorderId: number) => void;
  onCompletePayment?: (preorderId: number) => void;
  onCancel?: (preorderId: number) => void;
}

const PreOrderList: React.FC<PreOrderListProps> = ({
  onPayDeposit,
  onCompletePayment,
  onCancel
}) => {
  const dispatch = useAppDispatch();
  const { preorders, isLoading, error, pagination, filters } = useAppSelector(state => state.preorders);
  
  const [showFilters, setShowFilters] = useState(false);
  const [localFilters, setLocalFilters] = useState<PreOrderFilters>(filters);

  useEffect(() => {
    dispatch(fetchPreOrders(filters));
  }, [dispatch, filters]);

  const handleFilterChange = (key: keyof PreOrderFilters, value: any) => {
    const newFilters = { ...localFilters, [key]: value };
    setLocalFilters(newFilters);
  };

  const applyFilters = () => {
    dispatch(setFilters(localFilters));
    setShowFilters(false);
  };

  const clearAllFilters = () => {
    setLocalFilters({});
    dispatch(clearFilters());
    setShowFilters(false);
  };

  const handlePageChange = (page: number) => {
    dispatch(setFilters({ ...filters, page }));
  };

  const getActiveFilterCount = () => {
    return Object.values(filters).filter(value => 
      value !== undefined && value !== null && value !== ''
    ).length;
  };

  const getStatusCounts = () => {
    return preorders.reduce((counts, preorder) => {
      counts[preorder.status] = (counts[preorder.status] || 0) + 1;
      return counts;
    }, {} as Record<string, number>);
  };

  const statusCounts = getStatusCounts();

  if (error) {
    return (
      <div className="bg-red-50 border border-red-200 rounded-lg p-4">
        <div className="flex">
          <div className="flex-shrink-0">
            <XMarkIcon className="h-5 w-5 text-red-400" />
          </div>
          <div className="ml-3">
            <h3 className="text-sm font-medium text-red-800">Error loading pre-orders</h3>
            <div className="mt-2 text-sm text-red-700">
              <p>{error}</p>
            </div>
            <div className="mt-4">
              <button
                onClick={() => dispatch(fetchPreOrders(filters))}
                className="bg-red-100 px-3 py-2 rounded-md text-sm font-medium text-red-800 hover:bg-red-200"
              >
                Try Again
              </button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header with Filters */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-2xl font-bold text-gray-900">My Pre-Orders</h2>
          <p className="text-sm text-gray-600 mt-1">
            {pagination.total} pre-order{pagination.total !== 1 ? 's' : ''} found
          </p>
        </div>
        
        <div className="flex items-center gap-3">
          <button
            onClick={() => setShowFilters(!showFilters)}
            className={`inline-flex items-center px-4 py-2 border rounded-lg text-sm font-medium transition-colors ${
              showFilters || getActiveFilterCount() > 0
                ? 'border-primary-300 bg-primary-50 text-primary-700'
                : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
            }`}
          >
            <FunnelIcon className="w-4 h-4 mr-2" />
            Filters
            {getActiveFilterCount() > 0 && (
              <span className="ml-2 bg-primary-600 text-white text-xs rounded-full px-2 py-0.5">
                {getActiveFilterCount()}
              </span>
            )}
          </button>
        </div>
      </div>

      {/* Status Summary */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
        {[
          { key: 'deposit_pending', label: 'Deposit Pending', color: 'yellow' },
          { key: 'deposit_paid', label: 'Deposit Paid', color: 'blue' },
          { key: 'ready_for_payment', label: 'Ready to Pay', color: 'green' },
          { key: 'completed', label: 'Completed', color: 'gray' },
          { key: 'cancelled', label: 'Cancelled', color: 'red' }
        ].map(({ key, label, color }) => (
          <div
            key={key}
            className={`p-3 rounded-lg border cursor-pointer transition-colors ${
              filters.status === key
                ? `border-${color}-300 bg-${color}-50`
                : 'border-gray-200 bg-white hover:bg-gray-50'
            }`}
            onClick={() => {
              const newStatus = filters.status === key ? undefined : key as any;
              dispatch(setFilters({ ...filters, status: newStatus }));
            }}
          >
            <div className="text-2xl font-bold text-gray-900">
              {statusCounts[key] || 0}
            </div>
            <div className="text-sm text-gray-600">{label}</div>
          </div>
        ))}
      </div>

      {/* Filter Panel */}
      {showFilters && (
        <div className="bg-white border border-gray-200 rounded-lg p-4">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Status
              </label>
              <select
                value={localFilters.status || ''}
                onChange={(e) => handleFilterChange('status', e.target.value || undefined)}
                className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              >
                <option value="">All Statuses</option>
                <option value="deposit_pending">Deposit Pending</option>
                <option value="deposit_paid">Deposit Paid</option>
                <option value="ready_for_payment">Ready for Payment</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Sort By
              </label>
              <select
                value={localFilters.sortBy || 'created_at'}
                onChange={(e) => handleFilterChange('sortBy', e.target.value)}
                className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              >
                <option value="created_at">Date Created</option>
                <option value="estimated_arrival_date">Arrival Date</option>
                <option value="full_payment_due_date">Payment Due Date</option>
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Sort Order
              </label>
              <select
                value={localFilters.sortOrder || 'desc'}
                onChange={(e) => handleFilterChange('sortOrder', e.target.value as 'asc' | 'desc')}
                className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              >
                <option value="desc">Newest First</option>
                <option value="asc">Oldest First</option>
              </select>
            </div>
          </div>

          <div className="flex justify-end gap-3 mt-4">
            <button
              onClick={clearAllFilters}
              className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
            >
              Clear All
            </button>
            <button
              onClick={applyFilters}
              className="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-md hover:bg-primary-700"
            >
              Apply Filters
            </button>
          </div>
        </div>
      )}

      {/* Pre-orders List */}
      {isLoading ? (
        <div className="space-y-4">
          {[...Array(3)].map((_, index) => (
            <div key={index} className="bg-white rounded-lg shadow-md border border-gray-200 p-4 animate-pulse">
              <div className="flex">
                <div className="w-32 h-32 bg-gray-300 rounded"></div>
                <div className="flex-1 ml-4">
                  <div className="h-6 bg-gray-300 rounded w-3/4 mb-2"></div>
                  <div className="h-4 bg-gray-300 rounded w-1/2 mb-2"></div>
                  <div className="h-4 bg-gray-300 rounded w-1/4"></div>
                </div>
              </div>
            </div>
          ))}
        </div>
      ) : preorders.length === 0 ? (
        <div className="text-center py-12">
          <MagnifyingGlassIcon className="mx-auto h-12 w-12 text-gray-400" />
          <h3 className="mt-2 text-sm font-medium text-gray-900">No pre-orders found</h3>
          <p className="mt-1 text-sm text-gray-500">
            {getActiveFilterCount() > 0 
              ? 'Try adjusting your filters or browse products to create your first pre-order.'
              : 'You haven\'t made any pre-orders yet. Browse our upcoming releases to get started.'
            }
          </p>
          {getActiveFilterCount() > 0 && (
            <button
              onClick={clearAllFilters}
              className="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-primary-600 bg-primary-100 hover:bg-primary-200"
            >
              Clear Filters
            </button>
          )}
        </div>
      ) : (
        <div className="space-y-4">
          {preorders.map((preorder) => (
            <PreOrderCard
              key={preorder.id}
              preorder={preorder}
              onPayDeposit={onPayDeposit}
              onCompletePayment={onCompletePayment}
              onCancel={onCancel}
            />
          ))}
        </div>
      )}

      {/* Pagination */}
      {pagination.lastPage > 1 && (
        <div className="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6">
          <div className="flex flex-1 justify-between sm:hidden">
            <button
              onClick={() => handlePageChange(pagination.currentPage - 1)}
              disabled={pagination.currentPage === 1}
              className="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Previous
            </button>
            <button
              onClick={() => handlePageChange(pagination.currentPage + 1)}
              disabled={pagination.currentPage === pagination.lastPage}
              className="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Next
            </button>
          </div>
          <div className="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <div>
              <p className="text-sm text-gray-700">
                Showing{' '}
                <span className="font-medium">{(pagination.currentPage - 1) * pagination.perPage + 1}</span>
                {' '}to{' '}
                <span className="font-medium">
                  {Math.min(pagination.currentPage * pagination.perPage, pagination.total)}
                </span>
                {' '}of{' '}
                <span className="font-medium">{pagination.total}</span>
                {' '}results
              </p>
            </div>
            <div>
              <nav className="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                <button
                  onClick={() => handlePageChange(pagination.currentPage - 1)}
                  disabled={pagination.currentPage === 1}
                  className="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Previous
                </button>
                {[...Array(pagination.lastPage)].map((_, index) => {
                  const page = index + 1;
                  return (
                    <button
                      key={page}
                      onClick={() => handlePageChange(page)}
                      className={`relative inline-flex items-center px-4 py-2 text-sm font-semibold ${
                        page === pagination.currentPage
                          ? 'z-10 bg-primary-600 text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600'
                          : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0'
                      }`}
                    >
                      {page}
                    </button>
                  );
                })}
                <button
                  onClick={() => handlePageChange(pagination.currentPage + 1)}
                  disabled={pagination.currentPage === pagination.lastPage}
                  className="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Next
                </button>
              </nav>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default PreOrderList;