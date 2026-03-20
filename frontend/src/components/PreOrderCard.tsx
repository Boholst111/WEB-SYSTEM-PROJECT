import React from 'react';
import { Link } from 'react-router-dom';
import { PreOrder } from '../types';
import { 
  CalendarIcon, 
  CurrencyDollarIcon, 
  ClockIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon
} from '@heroicons/react/24/outline';

interface PreOrderCardProps {
  preorder: PreOrder;
  onPayDeposit?: (preorderId: number) => void;
  onCompletePayment?: (preorderId: number) => void;
  onCancel?: (preorderId: number) => void;
}

const PreOrderCard: React.FC<PreOrderCardProps> = ({
  preorder,
  onPayDeposit,
  onCompletePayment,
  onCancel
}) => {
  const getStatusConfig = () => {
    switch (preorder.status) {
      case 'deposit_pending':
        return {
          label: 'Deposit Pending',
          className: 'bg-yellow-100 text-yellow-800',
          icon: ClockIcon,
          description: 'Waiting for deposit payment'
        };
      case 'deposit_paid':
        return {
          label: 'Deposit Paid',
          className: 'bg-blue-100 text-blue-800',
          icon: CheckCircleIcon,
          description: 'Waiting for product arrival'
        };
      case 'ready_for_payment':
        return {
          label: 'Ready for Payment',
          className: 'bg-green-100 text-green-800',
          icon: CurrencyDollarIcon,
          description: 'Product arrived, complete payment'
        };
      case 'completed':
        return {
          label: 'Completed',
          className: 'bg-gray-100 text-gray-800',
          icon: CheckCircleIcon,
          description: 'Payment completed'
        };
      case 'cancelled':
        return {
          label: 'Cancelled',
          className: 'bg-red-100 text-red-800',
          icon: ExclamationTriangleIcon,
          description: 'Pre-order cancelled'
        };
      default:
        return {
          label: preorder.status,
          className: 'bg-gray-100 text-gray-800',
          icon: ClockIcon,
          description: ''
        };
    }
  };

  const statusConfig = getStatusConfig();
  const StatusIcon = statusConfig.icon;

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP'
    }).format(amount);
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-PH', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  };

  const getDaysUntilDue = () => {
    if (!preorder.fullPaymentDueDate) return null;
    const dueDate = new Date(preorder.fullPaymentDueDate);
    const today = new Date();
    const diffTime = dueDate.getTime() - today.getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return diffDays;
  };

  const daysUntilDue = getDaysUntilDue();
  const isOverdue = daysUntilDue !== null && daysUntilDue < 0;
  const isDueSoon = daysUntilDue !== null && daysUntilDue <= 7 && daysUntilDue >= 0;

  return (
    <div className="bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow duration-200">
      {/* Product Image and Basic Info */}
      <div className="flex">
        <div className="w-32 h-32 flex-shrink-0">
          <img
            src={preorder.product.images[0] || '/placeholder-product.jpg'}
            alt={preorder.product.name}
            className="w-full h-full object-cover"
          />
        </div>
        
        <div className="flex-1 p-4">
          <div className="flex justify-between items-start mb-2">
            <Link
              to={`/products/${preorder.product.id}`}
              className="text-lg font-semibold text-gray-900 hover:text-primary-600 transition-colors"
            >
              {preorder.product.name}
            </Link>
            
            <div className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusConfig.className}`}>
              <StatusIcon className="w-3 h-3 mr-1" />
              {statusConfig.label}
            </div>
          </div>
          
          <p className="text-sm text-gray-600 mb-2">{statusConfig.description}</p>
          
          <div className="flex items-center text-sm text-gray-500 mb-2">
            <span className="font-medium">Quantity:</span>
            <span className="ml-1">{preorder.quantity}</span>
            <span className="mx-2">•</span>
            <span className="font-medium">Scale:</span>
            <span className="ml-1">{preorder.product.scale}</span>
          </div>
        </div>
      </div>

      {/* Payment Information */}
      <div className="px-4 py-3 bg-gray-50 border-t border-gray-200">
        <div className="grid grid-cols-2 gap-4 mb-3">
          <div>
            <span className="text-xs text-gray-500 block">Deposit Amount</span>
            <span className={`text-sm font-semibold ${preorder.depositPaidAt ? 'text-green-600' : 'text-gray-900'}`}>
              {formatCurrency(preorder.depositAmount)}
              {preorder.depositPaidAt && (
                <CheckCircleIcon className="w-4 h-4 inline ml-1 text-green-600" />
              )}
            </span>
          </div>
          
          <div>
            <span className="text-xs text-gray-500 block">Remaining Amount</span>
            <span className="text-sm font-semibold text-gray-900">
              {formatCurrency(preorder.remainingAmount)}
            </span>
          </div>
        </div>

        {/* Dates */}
        <div className="grid grid-cols-2 gap-4 mb-3 text-xs">
          <div className="flex items-center text-gray-600">
            <CalendarIcon className="w-4 h-4 mr-1" />
            <span>Est. Arrival: {formatDate(preorder.estimatedArrivalDate)}</span>
          </div>
          
          {preorder.fullPaymentDueDate && (
            <div className={`flex items-center ${isOverdue ? 'text-red-600' : isDueSoon ? 'text-yellow-600' : 'text-gray-600'}`}>
              <ClockIcon className="w-4 h-4 mr-1" />
              <span>
                Due: {formatDate(preorder.fullPaymentDueDate)}
                {daysUntilDue !== null && (
                  <span className="ml-1">
                    ({isOverdue ? `${Math.abs(daysUntilDue)} days overdue` : 
                      isDueSoon ? `${daysUntilDue} days left` : 
                      `${daysUntilDue} days`})
                  </span>
                )}
              </span>
            </div>
          )}
        </div>

        {/* Action Buttons */}
        <div className="flex gap-2">
          {preorder.status === 'deposit_pending' && onPayDeposit && (
            <button
              onClick={() => onPayDeposit(preorder.id)}
              className="flex-1 bg-primary-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-primary-700 transition-colors"
            >
              Pay Deposit
            </button>
          )}
          
          {preorder.status === 'ready_for_payment' && onCompletePayment && (
            <button
              onClick={() => onCompletePayment(preorder.id)}
              className={`flex-1 px-4 py-2 rounded-md text-sm font-medium transition-colors ${
                isOverdue 
                  ? 'bg-red-600 text-white hover:bg-red-700' 
                  : isDueSoon 
                    ? 'bg-yellow-600 text-white hover:bg-yellow-700'
                    : 'bg-green-600 text-white hover:bg-green-700'
              }`}
            >
              {isOverdue ? 'Pay Now (Overdue)' : 'Complete Payment'}
            </button>
          )}
          
          <Link
            to={`/preorders/${preorder.id}`}
            className="px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50 transition-colors"
          >
            View Details
          </Link>
          
          {(preorder.status === 'deposit_pending' || preorder.status === 'deposit_paid') && onCancel && (
            <button
              onClick={() => onCancel(preorder.id)}
              className="px-4 py-2 border border-red-300 text-red-700 rounded-md text-sm font-medium hover:bg-red-50 transition-colors"
            >
              Cancel
            </button>
          )}
        </div>
      </div>
    </div>
  );
};

export default PreOrderCard;