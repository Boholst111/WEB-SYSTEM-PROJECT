import React, { useState, useEffect } from 'react';
import { 
  CheckCircleIcon, 
  ClockIcon, 
  ExclamationCircleIcon,
  XCircleIcon,
  ArrowPathIcon,
  InformationCircleIcon
} from '@heroicons/react/24/outline';
import { paymentApi, PaymentStatusResponse } from '../services/paymentApi';

interface PaymentStatusTrackerProps {
  paymentId: string;
  onStatusChange?: (status: string) => void;
  autoRefresh?: boolean;
  refreshInterval?: number;
  className?: string;
}

const PaymentStatusTracker: React.FC<PaymentStatusTrackerProps> = ({
  paymentId,
  onStatusChange,
  autoRefresh = true,
  refreshInterval = 5000,
  className = ''
}) => {
  const [paymentStatus, setPaymentStatus] = useState<PaymentStatusResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [lastUpdated, setLastUpdated] = useState<Date>(new Date());

  const fetchPaymentStatus = async () => {
    try {
      setError(null);
      const status = await paymentApi.getPaymentStatus(paymentId);
      setPaymentStatus(status);
      setLastUpdated(new Date());
      
      if (onStatusChange) {
        onStatusChange(status.status);
      }
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to fetch payment status');
      console.error('Error fetching payment status:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchPaymentStatus();
  }, [paymentId]);

  useEffect(() => {
    if (!autoRefresh || !paymentStatus) return;

    // Don't auto-refresh if payment is in final state
    if (['completed', 'failed', 'cancelled'].includes(paymentStatus.status)) {
      return;
    }

    const interval = setInterval(fetchPaymentStatus, refreshInterval);
    return () => clearInterval(interval);
  }, [autoRefresh, refreshInterval, paymentStatus?.status]);

  const getStatusInfo = (status: string) => {
    switch (status) {
      case 'pending':
        return {
          icon: ClockIcon,
          color: 'yellow',
          title: 'Payment Pending',
          description: 'Waiting for payment confirmation',
          bgColor: 'bg-yellow-50',
          borderColor: 'border-yellow-200',
          textColor: 'text-yellow-800',
          iconColor: 'text-yellow-600'
        };
      case 'processing':
        return {
          icon: ArrowPathIcon,
          color: 'blue',
          title: 'Processing Payment',
          description: 'Your payment is being processed',
          bgColor: 'bg-blue-50',
          borderColor: 'border-blue-200',
          textColor: 'text-blue-800',
          iconColor: 'text-blue-600'
        };
      case 'completed':
        return {
          icon: CheckCircleIcon,
          color: 'green',
          title: 'Payment Successful',
          description: 'Your payment has been completed successfully',
          bgColor: 'bg-green-50',
          borderColor: 'border-green-200',
          textColor: 'text-green-800',
          iconColor: 'text-green-600'
        };
      case 'failed':
        return {
          icon: XCircleIcon,
          color: 'red',
          title: 'Payment Failed',
          description: 'Your payment could not be processed',
          bgColor: 'bg-red-50',
          borderColor: 'border-red-200',
          textColor: 'text-red-800',
          iconColor: 'text-red-600'
        };
      case 'cancelled':
        return {
          icon: XCircleIcon,
          color: 'gray',
          title: 'Payment Cancelled',
          description: 'The payment was cancelled',
          bgColor: 'bg-gray-50',
          borderColor: 'border-gray-200',
          textColor: 'text-gray-800',
          iconColor: 'text-gray-600'
        };
      default:
        return {
          icon: InformationCircleIcon,
          color: 'gray',
          title: 'Unknown Status',
          description: 'Payment status is unknown',
          bgColor: 'bg-gray-50',
          borderColor: 'border-gray-200',
          textColor: 'text-gray-800',
          iconColor: 'text-gray-600'
        };
    }
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2,
    }).format(amount);
  };

  const formatDate = (dateString: string) => {
    return new Intl.DateTimeFormat('en-PH', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(new Date(dateString));
  };

  const handleManualRefresh = () => {
    setLoading(true);
    fetchPaymentStatus();
  };

  if (loading && !paymentStatus) {
    return (
      <div className={`bg-white rounded-lg shadow-md p-6 ${className}`}>
        <div className="animate-pulse">
          <div className="flex items-center mb-4">
            <div className="w-12 h-12 bg-gray-200 rounded-full mr-4"></div>
            <div>
              <div className="h-4 bg-gray-200 rounded w-32 mb-2"></div>
              <div className="h-3 bg-gray-200 rounded w-48"></div>
            </div>
          </div>
          <div className="space-y-2">
            <div className="h-3 bg-gray-200 rounded w-full"></div>
            <div className="h-3 bg-gray-200 rounded w-3/4"></div>
          </div>
        </div>
      </div>
    );
  }

  if (error && !paymentStatus) {
    return (
      <div className={`bg-white rounded-lg shadow-md p-6 ${className}`}>
        <div className="flex items-center p-4 bg-red-50 border border-red-200 rounded-lg">
          <ExclamationCircleIcon className="w-6 h-6 text-red-600 mr-3 flex-shrink-0" />
          <div className="flex-1">
            <h3 className="text-red-800 font-medium">Unable to load payment status</h3>
            <p className="text-red-600 text-sm mt-1">{error}</p>
          </div>
          <button
            onClick={handleManualRefresh}
            className="ml-3 px-3 py-1 text-sm bg-red-100 text-red-700 rounded hover:bg-red-200 transition-colors"
          >
            Retry
          </button>
        </div>
      </div>
    );
  }

  if (!paymentStatus) return null;

  const statusInfo = getStatusInfo(paymentStatus.status);
  const Icon = statusInfo.icon;

  return (
    <div className={`bg-white rounded-lg shadow-md p-6 ${className}`}>
      <div className="text-center mb-6">
        <h2 className="text-xl font-semibold text-gray-900 mb-4">Payment Status</h2>
        
        {/* Status Icon and Info */}
        <div className={`inline-flex items-center justify-center w-16 h-16 ${statusInfo.bgColor} rounded-full mb-4`}>
          <Icon className={`w-8 h-8 ${statusInfo.iconColor} ${paymentStatus.status === 'processing' ? 'animate-spin' : ''}`} />
        </div>
        
        <h3 className="text-lg font-medium text-gray-900 mb-2">
          {statusInfo.title}
        </h3>
        <p className="text-gray-600 mb-4">
          {statusInfo.description}
        </p>
      </div>

      {/* Payment Details */}
      <div className={`p-4 ${statusInfo.bgColor} ${statusInfo.borderColor} border rounded-lg mb-6`}>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div>
            <span className="text-gray-600">Payment ID:</span>
            <p className="font-mono text-gray-900 break-all">{paymentStatus.id}</p>
          </div>
          <div>
            <span className="text-gray-600">Amount:</span>
            <p className="font-semibold text-gray-900">{formatCurrency(paymentStatus.amount)}</p>
          </div>
          <div>
            <span className="text-gray-600">Payment Method:</span>
            <p className="text-gray-900 capitalize">{paymentStatus.paymentMethod.replace('_', ' ')}</p>
          </div>
          <div>
            <span className="text-gray-600">Created:</span>
            <p className="text-gray-900">{formatDate(paymentStatus.createdAt)}</p>
          </div>
          {paymentStatus.referenceNumber && (
            <div className="md:col-span-2">
              <span className="text-gray-600">Reference Number:</span>
              <p className="font-mono text-gray-900 break-all">{paymentStatus.referenceNumber}</p>
            </div>
          )}
          {paymentStatus.message && (
            <div className="md:col-span-2">
              <span className="text-gray-600">Message:</span>
              <p className="text-gray-900">{paymentStatus.message}</p>
            </div>
          )}
        </div>
      </div>

      {/* Action Buttons */}
      <div className="flex justify-between items-center">
        <div className="text-xs text-gray-500">
          Last updated: {lastUpdated.toLocaleTimeString()}
        </div>
        
        <div className="flex space-x-3">
          {!['completed', 'failed', 'cancelled'].includes(paymentStatus.status) && (
            <button
              onClick={handleManualRefresh}
              disabled={loading}
              className="px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center"
            >
              <ArrowPathIcon className={`w-4 h-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
              Refresh
            </button>
          )}
          
          {paymentStatus.paymentUrl && paymentStatus.status === 'pending' && (
            <a
              href={paymentStatus.paymentUrl}
              target="_blank"
              rel="noopener noreferrer"
              className="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
            >
              Complete Payment
            </a>
          )}
        </div>
      </div>

      {/* Auto-refresh indicator */}
      {autoRefresh && !['completed', 'failed', 'cancelled'].includes(paymentStatus.status) && (
        <div className="mt-4 flex items-center justify-center text-xs text-gray-500">
          <div className="w-2 h-2 bg-green-500 rounded-full animate-pulse mr-2"></div>
          Auto-refreshing every {refreshInterval / 1000} seconds
        </div>
      )}
    </div>
  );
};

export default PaymentStatusTracker;