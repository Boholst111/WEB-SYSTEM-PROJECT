import React, { useState, useEffect } from 'react';
import { 
  MagnifyingGlassIcon, 
  FunnelIcon, 
  ArrowDownTrayIcon,
  CheckIcon,
  XMarkIcon
} from '@heroicons/react/24/outline';
import { orderManagementApi, type OrderFilters } from '../../services/adminApi';
import { Order, PaginatedResponse } from '../../types';
import OrderTable from '../../components/admin/OrderTable';
import OrderFiltersPanel from '../../components/admin/OrderFiltersPanel';
import BulkActionsPanel from '../../components/admin/BulkActionsPanel';
import OrderDetailModal from '../../components/admin/OrderDetailModal';

const OrderManagementPage: React.FC = () => {
  const [orders, setOrders] = useState<PaginatedResponse<Order> | null>(null);
  const [selectedOrders, setSelectedOrders] = useState<number[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showFilters, setShowFilters] = useState(false);
  const [selectedOrder, setSelectedOrder] = useState<Order | null>(null);
  const [showOrderDetail, setShowOrderDetail] = useState(false);
  
  const [filters, setFilters] = useState<OrderFilters>({
    per_page: 20,
    sort_by: 'created_at',
    sort_direction: 'desc',
    include_items: true
  });

  const fetchOrders = async () => {
    try {
      setIsLoading(true);
      setError(null);
      
      const response = await orderManagementApi.getOrders(filters);
      
      if (response.success) {
        setOrders(response.data);
      } else {
        setError('Failed to load orders');
      }
    } catch (err) {
      setError('Failed to load orders');
      console.error('Orders fetch error:', err);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchOrders();
  }, [filters]);

  const handleFilterChange = (newFilters: Partial<OrderFilters>) => {
    setFilters(prev => ({ ...prev, ...newFilters, page: 1 }));
    setSelectedOrders([]);
  };

  const handlePageChange = (page: number) => {
    setFilters(prev => ({ ...prev, page }));
    setSelectedOrders([]);
  };

  const handleOrderSelect = (orderId: number, selected: boolean) => {
    if (selected) {
      setSelectedOrders(prev => [...prev, orderId]);
    } else {
      setSelectedOrders(prev => prev.filter(id => id !== orderId));
    }
  };

  const handleSelectAll = (selected: boolean) => {
    if (selected && orders) {
      setSelectedOrders(orders.data.map(order => order.id));
    } else {
      setSelectedOrders([]);
    }
  };

  const handleOrderClick = async (order: Order) => {
    try {
      const response = await orderManagementApi.getOrder(order.id);
      if (response.success) {
        setSelectedOrder(response.data.order);
        setShowOrderDetail(true);
      }
    } catch (err) {
      console.error('Failed to load order details:', err);
    }
  };

  const handleBulkAction = async (action: string, data: any) => {
    try {
      setIsLoading(true);
      
      const response = await orderManagementApi.bulkUpdateOrders({
        order_ids: selectedOrders,
        action: action as any,
        ...data
      });

      if (response.success) {
        await fetchOrders();
        setSelectedOrders([]);
      } else {
        setError(response.message || 'Bulk action failed');
      }
    } catch (err) {
      setError('Bulk action failed');
      console.error('Bulk action error:', err);
    } finally {
      setIsLoading(false);
    }
  };

  const handleExport = async (format: 'csv' | 'excel' | 'pdf') => {
    try {
      const response = await orderManagementApi.exportOrders({
        format,
        filters: filters
      });

      if (response.success) {
        // Create download link
        const link = document.createElement('a');
        link.href = response.data.download_url;
        link.download = response.data.filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
      }
    } catch (err) {
      console.error('Export failed:', err);
    }
  };

  const getStatusColor = (status: string) => {
    const colors = {
      pending: 'bg-yellow-100 text-yellow-800',
      confirmed: 'bg-blue-100 text-blue-800',
      processing: 'bg-purple-100 text-purple-800',
      shipped: 'bg-indigo-100 text-indigo-800',
      delivered: 'bg-green-100 text-green-800',
      cancelled: 'bg-red-100 text-red-800'
    };
    return colors[status as keyof typeof colors] || 'bg-gray-100 text-gray-800';
  };

  const getPaymentStatusColor = (status: string) => {
    const colors = {
      pending: 'bg-yellow-100 text-yellow-800',
      paid: 'bg-green-100 text-green-800',
      failed: 'bg-red-100 text-red-800',
      refunded: 'bg-gray-100 text-gray-800'
    };
    return colors[status as keyof typeof colors] || 'bg-gray-100 text-gray-800';
  };

  if (error) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <XMarkIcon className="h-12 w-12 text-red-500 mx-auto mb-4" />
          <h2 className="text-xl font-semibold text-gray-900 mb-2">Error Loading Orders</h2>
          <p className="text-gray-600 mb-4">{error}</p>
          <button
            onClick={fetchOrders}
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
              <h1 className="text-3xl font-bold text-gray-900">Order Management</h1>
              <p className="text-gray-600 mt-1">
                Manage and track all customer orders
              </p>
            </div>
            <div className="flex space-x-3">
              <button
                onClick={() => setShowFilters(!showFilters)}
                className="flex items-center space-x-2 px-4 py-2 border border-gray-300 rounded-md bg-white text-sm font-medium text-gray-700 hover:bg-gray-50"
              >
                <FunnelIcon className="h-4 w-4" />
                <span>Filters</span>
              </button>
              <div className="relative">
                <button className="flex items-center space-x-2 px-4 py-2 border border-gray-300 rounded-md bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                  <ArrowDownTrayIcon className="h-4 w-4" />
                  <span>Export</span>
                </button>
                <div className="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg border border-gray-200 z-10 hidden group-hover:block">
                  <div className="py-1">
                    <button
                      onClick={() => handleExport('csv')}
                      className="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                    >
                      Export as CSV
                    </button>
                    <button
                      onClick={() => handleExport('excel')}
                      className="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                    >
                      Export as Excel
                    </button>
                    <button
                      onClick={() => handleExport('pdf')}
                      className="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                    >
                      Export as PDF
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Filters Panel */}
        {showFilters && (
          <div className="mb-6">
            <OrderFiltersPanel
              filters={filters}
              onChange={handleFilterChange}
              onClose={() => setShowFilters(false)}
            />
          </div>
        )}

        {/* Bulk Actions */}
        {selectedOrders.length > 0 && (
          <div className="mb-6">
            <BulkActionsPanel
              selectedCount={selectedOrders.length}
              onAction={handleBulkAction}
              onClear={() => setSelectedOrders([])}
            />
          </div>
        )}

        {/* Orders Table */}
        <div className="bg-white rounded-lg shadow">
          <OrderTable
            orders={orders}
            selectedOrders={selectedOrders}
            isLoading={isLoading}
            onOrderSelect={handleOrderSelect}
            onSelectAll={handleSelectAll}
            onOrderClick={handleOrderClick}
            onPageChange={handlePageChange}
            getStatusColor={getStatusColor}
            getPaymentStatusColor={getPaymentStatusColor}
          />
        </div>

        {/* Order Detail Modal */}
        {showOrderDetail && selectedOrder && (
          <OrderDetailModal
            order={selectedOrder}
            isOpen={showOrderDetail}
            onClose={() => {
              setShowOrderDetail(false);
              setSelectedOrder(null);
            }}
            onOrderUpdate={fetchOrders}
          />
        )}
      </div>
    </div>
  );
};

export default OrderManagementPage;