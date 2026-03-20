import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAppDispatch, useAppSelector } from '../store';
import { fetchPreOrders } from '../store/slices/preorderSlice';
import PreOrderCard from './PreOrderCard';
import PreOrderTracker from './PreOrderTracker';
import { 
  ClockIcon,
  CurrencyDollarIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  PlusIcon,
  ChartBarIcon
} from '@heroicons/react/24/outline';

interface PreOrderDashboardProps {
  onPayDeposit?: (preorderId: number) => void;
  onCompletePayment?: (preorderId: number) => void;
  onCancel?: (preorderId: number) => void;
}

const PreOrderDashboard: React.FC<PreOrderDashboardProps> = ({
  onPayDeposit,
  onCompletePayment,
  onCancel
}) => {
  const dispatch = useAppDispatch();
  const { preorders, isLoading, error } = useAppSelector(state => state.preorders);
  const { user } = useAppSelector(state => state.auth);
  
  const [activeTab, setActiveTab] = useState<'overview' | 'active' | 'completed'>('overview');

  useEffect(() => {
    dispatch(fetchPreOrders({ sortBy: 'created_at', sortOrder: 'desc' }));
  }, [dispatch]);

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP'
    }).format(amount);
  };

  const getPreOrderStats = () => {
    const stats = {
      total: preorders.length,
      depositPending: 0,
      depositPaid: 0,
      readyForPayment: 0,
      completed: 0,
      cancelled: 0,
      totalDeposits: 0,
      totalRemaining: 0,
      overdueCount: 0
    };

    const today = new Date();

    preorders.forEach(preorder => {
      stats[preorder.status as keyof typeof stats]++;
      stats.totalDeposits += preorder.depositPaidAt ? preorder.depositAmount : 0;
      
      if (preorder.status !== 'completed' && preorder.status !== 'cancelled') {
        stats.totalRemaining += preorder.remainingAmount;
      }

      // Check if overdue
      if (preorder.status === 'ready_for_payment' && preorder.fullPaymentDueDate) {
        const dueDate = new Date(preorder.fullPaymentDueDate);
        if (dueDate < today) {
          stats.overdueCount++;
        }
      }
    });

    return stats;
  };

  const getFilteredPreOrders = () => {
    switch (activeTab) {
      case 'active':
        return preorders.filter(p => 
          p.status !== 'completed' && p.status !== 'cancelled'
        );
      case 'completed':
        return preorders.filter(p => 
          p.status === 'completed' || p.status === 'cancelled'
        );
      default:
        return preorders;
    }
  };

  const getUrgentPreOrders = () => {
    const today = new Date();
    return preorders.filter(preorder => {
      if (preorder.status === 'deposit_pending') return true;
      
      if (preorder.status === 'ready_for_payment' && preorder.fullPaymentDueDate) {
        const dueDate = new Date(preorder.fullPaymentDueDate);
        const diffTime = dueDate.getTime() - today.getTime();
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        return diffDays <= 7; // Due within 7 days or overdue
      }
      
      return false;
    }).slice(0, 3); // Show top 3 urgent items
  };

  const stats = getPreOrderStats();
  const filteredPreOrders = getFilteredPreOrders();
  const urgentPreOrders = getUrgentPreOrders();

  if (isLoading && preorders.length === 0) {
    return (
      <div className="space-y-6">
        <div className="animate-pulse">
          <div className="h-8 bg-gray-300 rounded w-1/4 mb-4"></div>
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            {[...Array(4)].map((_, i) => (
              <div key={i} className="bg-gray-300 h-24 rounded-lg"></div>
            ))}
          </div>
          <div className="space-y-4">
            {[...Array(3)].map((_, i) => (
              <div key={i} className="bg-gray-300 h-32 rounded-lg"></div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">Pre-Order Dashboard</h1>
          <p className="text-gray-600 mt-1">
            Welcome back, {user?.firstName}! Manage your pre-orders and track their progress.
          </p>
        </div>
        <Link
          to="/products?isPreorder=true"
          className="mt-4 sm:mt-0 inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg font-medium hover:bg-primary-700 transition-colors"
        >
          <PlusIcon className="w-5 h-5 mr-2" />
          Browse Pre-Orders
        </Link>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-white rounded-lg shadow-md border border-gray-200 p-6">
          <div className="flex items-center">
            <div className="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
              <ChartBarIcon className="w-6 h-6 text-blue-600" />
            </div>
            <div className="ml-4">
              <p className="text-sm text-gray-600">Total Pre-Orders</p>
              <p className="text-2xl font-bold text-gray-900">{stats.total}</p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow-md border border-gray-200 p-6">
          <div className="flex items-center">
            <div className="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
              <ClockIcon className="w-6 h-6 text-yellow-600" />
            </div>
            <div className="ml-4">
              <p className="text-sm text-gray-600">Pending Action</p>
              <p className="text-2xl font-bold text-gray-900">
                {stats.depositPending + stats.readyForPayment}
              </p>
              {stats.overdueCount > 0 && (
                <p className="text-xs text-red-600 font-medium">
                  {stats.overdueCount} overdue
                </p>
              )}
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow-md border border-gray-200 p-6">
          <div className="flex items-center">
            <div className="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
              <CurrencyDollarIcon className="w-6 h-6 text-green-600" />
            </div>
            <div className="ml-4">
              <p className="text-sm text-gray-600">Deposits Paid</p>
              <p className="text-2xl font-bold text-gray-900">{formatCurrency(stats.totalDeposits)}</p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow-md border border-gray-200 p-6">
          <div className="flex items-center">
            <div className="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
              <CheckCircleIcon className="w-6 h-6 text-purple-600" />
            </div>
            <div className="ml-4">
              <p className="text-sm text-gray-600">Completed</p>
              <p className="text-2xl font-bold text-gray-900">{stats.completed}</p>
            </div>
          </div>
        </div>
      </div>

      {/* Urgent Actions */}
      {urgentPreOrders.length > 0 && (
        <div className="bg-white rounded-lg shadow-md border border-gray-200 p-6">
          <div className="flex items-center mb-4">
            <ExclamationTriangleIcon className="w-6 h-6 text-red-600 mr-2" />
            <h2 className="text-lg font-semibold text-gray-900">Urgent Actions Required</h2>
          </div>
          <div className="space-y-4">
            {urgentPreOrders.map(preorder => (
              <div key={preorder.id} className="border border-red-200 rounded-lg p-4 bg-red-50">
                <div className="flex items-center justify-between">
                  <div className="flex items-center">
                    <img
                      src={preorder.product.images[0] || '/placeholder-product.jpg'}
                      alt={preorder.product.name}
                      className="w-12 h-12 object-cover rounded-lg mr-3"
                    />
                    <div>
                      <h3 className="font-semibold text-gray-900">{preorder.product.name}</h3>
                      <p className="text-sm text-gray-600">
                        {preorder.status === 'deposit_pending' 
                          ? `Deposit pending: ${formatCurrency(preorder.depositAmount)}`
                          : `Payment due: ${formatCurrency(preorder.remainingAmount)}`
                        }
                      </p>
                    </div>
                  </div>
                  <div className="flex gap-2">
                    {preorder.status === 'deposit_pending' && onPayDeposit && (
                      <button
                        onClick={() => onPayDeposit(preorder.id)}
                        className="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition-colors"
                      >
                        Pay Deposit
                      </button>
                    )}
                    {preorder.status === 'ready_for_payment' && onCompletePayment && (
                      <button
                        onClick={() => onCompletePayment(preorder.id)}
                        className="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors"
                      >
                        Pay Now
                      </button>
                    )}
                    <Link
                      to={`/preorders/${preorder.id}`}
                      className="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors"
                    >
                      View Details
                    </Link>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Tabs */}
      <div className="border-b border-gray-200">
        <nav className="-mb-px flex space-x-8">
          {[
            { key: 'overview', label: 'Overview', count: stats.total },
            { key: 'active', label: 'Active', count: stats.total - stats.completed - stats.cancelled },
            { key: 'completed', label: 'Completed', count: stats.completed + stats.cancelled }
          ].map(tab => (
            <button
              key={tab.key}
              onClick={() => setActiveTab(tab.key as any)}
              className={`py-2 px-1 border-b-2 font-medium text-sm ${
                activeTab === tab.key
                  ? 'border-primary-500 text-primary-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
            >
              {tab.label}
              <span className="ml-2 bg-gray-100 text-gray-900 py-0.5 px-2.5 rounded-full text-xs">
                {tab.count}
              </span>
            </button>
          ))}
        </nav>
      </div>

      {/* Content */}
      {activeTab === 'overview' && (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Recent Pre-Orders */}
          <div className="lg:col-span-2">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Recent Pre-Orders</h2>
            {preorders.length === 0 ? (
              <div className="bg-white rounded-lg shadow-md border border-gray-200 p-8 text-center">
                <ClockIcon className="mx-auto h-12 w-12 text-gray-400 mb-4" />
                <h3 className="text-lg font-medium text-gray-900 mb-2">No pre-orders yet</h3>
                <p className="text-gray-600 mb-4">
                  Start browsing our upcoming releases to create your first pre-order.
                </p>
                <Link
                  to="/products?isPreorder=true"
                  className="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg font-medium hover:bg-primary-700 transition-colors"
                >
                  Browse Pre-Orders
                </Link>
              </div>
            ) : (
              <div className="space-y-4">
                {preorders.slice(0, 3).map(preorder => (
                  <PreOrderCard
                    key={preorder.id}
                    preorder={preorder}
                    onPayDeposit={onPayDeposit}
                    onCompletePayment={onCompletePayment}
                    onCancel={onCancel}
                  />
                ))}
                {preorders.length > 3 && (
                  <div className="text-center">
                    <button
                      onClick={() => setActiveTab('active')}
                      className="text-primary-600 hover:text-primary-700 font-medium"
                    >
                      View all {preorders.length} pre-orders →
                    </button>
                  </div>
                )}
              </div>
            )}
          </div>

          {/* Progress Tracker */}
          <div>
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Latest Progress</h2>
            {preorders.length > 0 ? (
              <PreOrderTracker 
                preorder={preorders[0]} 
                showDetails={false}
              />
            ) : (
              <div className="bg-white rounded-lg shadow-md border border-gray-200 p-6 text-center">
                <p className="text-gray-500">No pre-orders to track</p>
              </div>
            )}
          </div>
        </div>
      )}

      {(activeTab === 'active' || activeTab === 'completed') && (
        <div>
          {filteredPreOrders.length === 0 ? (
            <div className="bg-white rounded-lg shadow-md border border-gray-200 p-8 text-center">
              <ClockIcon className="mx-auto h-12 w-12 text-gray-400 mb-4" />
              <h3 className="text-lg font-medium text-gray-900 mb-2">
                No {activeTab} pre-orders
              </h3>
              <p className="text-gray-600">
                {activeTab === 'active' 
                  ? 'All your pre-orders have been completed or you haven\'t made any yet.'
                  : 'You don\'t have any completed pre-orders yet.'
                }
              </p>
            </div>
          ) : (
            <div className="space-y-4">
              {filteredPreOrders.map(preorder => (
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
        </div>
      )}

      {error && (
        <div className="bg-red-50 border border-red-200 rounded-lg p-4">
          <div className="flex">
            <ExclamationTriangleIcon className="h-5 w-5 text-red-400" />
            <div className="ml-3">
              <h3 className="text-sm font-medium text-red-800">Error loading dashboard</h3>
              <div className="mt-2 text-sm text-red-700">
                <p>{error}</p>
              </div>
              <div className="mt-4">
                <button
                  onClick={() => dispatch(fetchPreOrders({ sortBy: 'created_at', sortOrder: 'desc' }))}
                  className="bg-red-100 px-3 py-2 rounded-md text-sm font-medium text-red-800 hover:bg-red-200"
                >
                  Try Again
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default PreOrderDashboard;