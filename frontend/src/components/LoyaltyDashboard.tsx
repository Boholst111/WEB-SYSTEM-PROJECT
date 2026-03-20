import React, { useState, useEffect } from 'react';
import { loyaltyApi, LoyaltyBalance } from '../services/loyaltyApi';
import { LoyaltyTransaction } from '../types';
import { useAppSelector } from '../store';

interface LoyaltyDashboardProps {
  className?: string;
}

const LoyaltyDashboard: React.FC<LoyaltyDashboardProps> = ({ className = '' }) => {
  const [balance, setBalance] = useState<LoyaltyBalance | null>(null);
  const [transactions, setTransactions] = useState<LoyaltyTransaction[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<'overview' | 'transactions'>('overview');
  const [transactionFilter, setTransactionFilter] = useState<'all' | 'earned' | 'redeemed'>('all');

  const { isAuthenticated } = useAppSelector(state => state.auth);

  useEffect(() => {
    if (isAuthenticated) {
      loadLoyaltyData();
    }
  }, [isAuthenticated]);

  const loadLoyaltyData = async () => {
    try {
      setLoading(true);
      setError(null);

      const [balanceResponse, transactionsResponse] = await Promise.all([
        loyaltyApi.getBalance(),
        loyaltyApi.getTransactions({ per_page: 10 })
      ]);

      if (balanceResponse.success) {
        setBalance(balanceResponse.data);
      }

      if (transactionsResponse.data) {
        setTransactions(transactionsResponse.data);
      }
    } catch (err) {
      setError('Failed to load loyalty data');
      console.error('Error loading loyalty data:', err);
    } finally {
      setLoading(false);
    }
  };

  const loadTransactions = async (type?: 'earned' | 'redeemed') => {
    try {
      const response = await loyaltyApi.getTransactions({ 
        per_page: 20,
        type: type 
      });
      
      if (response.data) {
        setTransactions(response.data);
      }
    } catch (err) {
      console.error('Error loading transactions:', err);
    }
  };

  const handleFilterChange = (filter: 'all' | 'earned' | 'redeemed') => {
    setTransactionFilter(filter);
    if (filter === 'all') {
      loadTransactions();
    } else {
      loadTransactions(filter);
    }
  };

  const formatAmount = (amount: number, isCredit: boolean = true) => {
    const sign = isCredit ? '+' : '-';
    return `${sign}${Math.abs(amount).toFixed(2)}`;
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  const getTransactionTypeColor = (type: string) => {
    switch (type) {
      case 'earned':
        return 'text-green-600';
      case 'redeemed':
        return 'text-red-600';
      case 'expired':
        return 'text-gray-500';
      case 'bonus':
        return 'text-blue-600';
      default:
        return 'text-gray-700';
    }
  };

  if (!isAuthenticated) {
    return (
      <div className={`bg-white rounded-lg shadow-md p-6 ${className}`}>
        <p className="text-gray-600 text-center">Please log in to view your loyalty dashboard.</p>
      </div>
    );
  }

  if (loading) {
    return (
      <div className={`bg-white rounded-lg shadow-md p-6 ${className}`}>
        <div className="animate-pulse">
          <div className="h-4 bg-gray-200 rounded w-1/4 mb-4"></div>
          <div className="h-8 bg-gray-200 rounded w-1/2 mb-6"></div>
          <div className="space-y-3">
            {[...Array(5)].map((_, i) => (
              <div key={i} className="h-4 bg-gray-200 rounded"></div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className={`bg-white rounded-lg shadow-md p-6 ${className}`}>
        <div className="text-center">
          <p className="text-red-600 mb-4">{error}</p>
          <button
            onClick={loadLoyaltyData}
            className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
          >
            Retry
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className={`bg-white rounded-lg shadow-md ${className}`}>
      {/* Header */}
      <div className="border-b border-gray-200 px-6 py-4">
        <h2 className="text-2xl font-bold text-gray-900">Diecast Credits Dashboard</h2>
        <p className="text-gray-600 mt-1">Manage your loyalty rewards and track your progress</p>
      </div>

      {/* Tab Navigation */}
      <div className="border-b border-gray-200">
        <nav className="flex space-x-8 px-6">
          <button
            onClick={() => setActiveTab('overview')}
            className={`py-4 px-1 border-b-2 font-medium text-sm ${
              activeTab === 'overview'
                ? 'border-blue-500 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            Overview
          </button>
          <button
            onClick={() => setActiveTab('transactions')}
            className={`py-4 px-1 border-b-2 font-medium text-sm ${
              activeTab === 'transactions'
                ? 'border-blue-500 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            Transaction History
          </button>
        </nav>
      </div>

      {/* Content */}
      <div className="p-6">
        {activeTab === 'overview' && balance && (
          <div className="space-y-6">
            {/* Balance Cards */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div className="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg p-6 text-white">
                <h3 className="text-lg font-semibold mb-2">Available Credits</h3>
                <p className="text-3xl font-bold">{balance.available_credits.toFixed(2)}</p>
                <p className="text-blue-100 text-sm mt-1">Ready to use</p>
              </div>
              
              <div className="bg-gradient-to-r from-green-500 to-green-600 rounded-lg p-6 text-white">
                <h3 className="text-lg font-semibold mb-2">Total Earned</h3>
                <p className="text-3xl font-bold">{balance.total_earned.toFixed(2)}</p>
                <p className="text-green-100 text-sm mt-1">Lifetime earnings</p>
              </div>
              
              <div className="bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg p-6 text-white">
                <h3 className="text-lg font-semibold mb-2">Total Spent</h3>
                <p className="text-3xl font-bold">₱{balance.total_spent.toFixed(2)}</p>
                <p className="text-purple-100 text-sm mt-1">Lifetime purchases</p>
              </div>
            </div>

            {/* Expiring Credits Warning */}
            {balance.expiring_soon > 0 && (
              <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div className="flex items-center">
                  <div className="flex-shrink-0">
                    <svg className="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                      <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                    </svg>
                  </div>
                  <div className="ml-3">
                    <h3 className="text-sm font-medium text-yellow-800">
                      Credits Expiring Soon
                    </h3>
                    <p className="text-sm text-yellow-700 mt-1">
                      {balance.expiring_soon.toFixed(2)} credits will expire in the next {balance.expiring_days} days. 
                      Use them before they're gone!
                    </p>
                  </div>
                </div>
              </div>
            )}

            {/* Quick Stats */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div className="bg-gray-50 rounded-lg p-4">
                <h4 className="font-semibold text-gray-900 mb-3">Credits Summary</h4>
                <div className="space-y-2 text-sm">
                  <div className="flex justify-between">
                    <span className="text-gray-600">Total Earned:</span>
                    <span className="font-medium text-green-600">+{balance.total_earned.toFixed(2)}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-600">Total Redeemed:</span>
                    <span className="font-medium text-red-600">-{balance.total_redeemed.toFixed(2)}</span>
                  </div>
                  <div className="flex justify-between border-t pt-2">
                    <span className="text-gray-900 font-semibold">Available Balance:</span>
                    <span className="font-bold text-blue-600">{balance.available_credits.toFixed(2)}</span>
                  </div>
                </div>
              </div>

              <div className="bg-gray-50 rounded-lg p-4">
                <h4 className="font-semibold text-gray-900 mb-3">Tier Benefits</h4>
                <div className="space-y-2 text-sm">
                  <div className="flex justify-between">
                    <span className="text-gray-600">Credits Multiplier:</span>
                    <span className="font-medium">{balance.tier_benefits.credits_multiplier}x</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-600">Bonus Rate:</span>
                    <span className="font-medium">{(balance.tier_benefits.bonus_rate * 100).toFixed(1)}%</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-600">Free Shipping:</span>
                    <span className="font-medium">₱{balance.tier_benefits.free_shipping_threshold}+</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}

        {activeTab === 'transactions' && (
          <div className="space-y-4">
            {/* Transaction Filters */}
            <div className="flex space-x-4">
              <button
                onClick={() => handleFilterChange('all')}
                className={`px-4 py-2 rounded-md text-sm font-medium ${
                  transactionFilter === 'all'
                    ? 'bg-blue-100 text-blue-700'
                    : 'text-gray-500 hover:text-gray-700'
                }`}
              >
                All Transactions
              </button>
              <button
                onClick={() => handleFilterChange('earned')}
                className={`px-4 py-2 rounded-md text-sm font-medium ${
                  transactionFilter === 'earned'
                    ? 'bg-green-100 text-green-700'
                    : 'text-gray-500 hover:text-gray-700'
                }`}
              >
                Earned
              </button>
              <button
                onClick={() => handleFilterChange('redeemed')}
                className={`px-4 py-2 rounded-md text-sm font-medium ${
                  transactionFilter === 'redeemed'
                    ? 'bg-red-100 text-red-700'
                    : 'text-gray-500 hover:text-gray-700'
                }`}
              >
                Redeemed
              </button>
            </div>

            {/* Transaction List */}
            <div className="space-y-3">
              {transactions.length === 0 ? (
                <div className="text-center py-8">
                  <p className="text-gray-500">No transactions found.</p>
                </div>
              ) : (
                transactions.map((transaction) => (
                  <div key={transaction.id} className="border border-gray-200 rounded-lg p-4">
                    <div className="flex justify-between items-start">
                      <div className="flex-1">
                        <div className="flex items-center space-x-2">
                          <span className={`font-semibold ${getTransactionTypeColor(transaction.transactionType)}`}>
                            {formatAmount(transaction.amount, transaction.transactionType === 'earned')}
                          </span>
                          <span className="text-sm text-gray-500 capitalize">
                            {transaction.transactionType}
                          </span>
                        </div>
                        <p className="text-gray-700 mt-1">{transaction.description}</p>
                        <p className="text-sm text-gray-500 mt-1">
                          Balance after: {transaction.balanceAfter.toFixed(2)}
                        </p>
                      </div>
                      <div className="text-right">
                        <p className="text-sm text-gray-500">
                          {formatDate(transaction.createdAt)}
                        </p>
                        {transaction.expiresAt && (
                          <p className="text-xs text-yellow-600 mt-1">
                            Expires: {formatDate(transaction.expiresAt)}
                          </p>
                        )}
                      </div>
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default LoyaltyDashboard;