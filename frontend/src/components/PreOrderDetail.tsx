import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { PreOrder } from '../types';
import { preorderApi, PreOrderNotification } from '../services/preorderApi';
import { 
  CalendarIcon, 
  CurrencyDollarIcon, 
  ClockIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  BellIcon,
  ArrowLeftIcon,
  InformationCircleIcon
} from '@heroicons/react/24/outline';

interface PreOrderDetailProps {
  preorder: PreOrder;
  onPayDeposit?: (preorderId: number) => void;
  onCompletePayment?: (preorderId: number) => void;
  onCancel?: (preorderId: number) => void;
}

const PreOrderDetail: React.FC<PreOrderDetailProps> = ({
  preorder,
  onPayDeposit,
  onCompletePayment,
  onCancel
}) => {
  const [notifications, setNotifications] = useState<PreOrderNotification[]>([]);
  const [loadingNotifications, setLoadingNotifications] = useState(false);
  const [showNotifications, setShowNotifications] = useState(false);

  useEffect(() => {
    if (showNotifications) {
      loadNotifications();
    }
  }, [showNotifications, preorder.id]);

  const loadNotifications = async () => {
    setLoadingNotifications(true);
    try {
      const response = await preorderApi.getPreOrderNotifications(preorder.id);
      setNotifications(response.data);
    } catch (error) {
      console.error('Failed to load notifications:', error);
    } finally {
      setLoadingNotifications(false);
    }
  };

  const getStatusConfig = () => {
    switch (preorder.status) {
      case 'deposit_pending':
        return {
          label: 'Deposit Pending',
          className: 'bg-yellow-100 text-yellow-800 border-yellow-200',
          icon: ClockIcon,
          description: 'Your pre-order is waiting for deposit payment to be confirmed.',
          nextStep: 'Pay the deposit to secure your pre-order.'
        };
      case 'deposit_paid':
        return {
          label: 'Deposit Paid',
          className: 'bg-blue-100 text-blue-800 border-blue-200',
          icon: CheckCircleIcon,
          description: 'Your deposit has been paid. We\'re waiting for the product to arrive.',
          nextStep: 'We\'ll notify you when the product arrives and is ready for final payment.'
        };
      case 'ready_for_payment':
        return {
          label: 'Ready for Payment',
          className: 'bg-green-100 text-green-800 border-green-200',
          icon: CurrencyDollarIcon,
          description: 'Great news! Your product has arrived and is ready for final payment.',
          nextStep: 'Complete your payment to have the item shipped to you.'
        };
      case 'completed':
        return {
          label: 'Payment Completed',
          className: 'bg-gray-100 text-gray-800 border-gray-200',
          icon: CheckCircleIcon,
          description: 'Payment completed! Your order is being prepared for shipment.',
          nextStep: 'You\'ll receive tracking information once your order ships.'
        };
      case 'cancelled':
        return {
          label: 'Cancelled',
          className: 'bg-red-100 text-red-800 border-red-200',
          icon: ExclamationTriangleIcon,
          description: 'This pre-order has been cancelled.',
          nextStep: 'If you paid a deposit, it will be refunded according to our policy.'
        };
      default:
        return {
          label: preorder.status,
          className: 'bg-gray-100 text-gray-800 border-gray-200',
          icon: ClockIcon,
          description: '',
          nextStep: ''
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
      month: 'long',
      day: 'numeric'
    });
  };

  const formatDateTime = (dateString: string) => {
    return new Date(dateString).toLocaleString('en-PH', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
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

  const totalAmount = preorder.depositAmount + preorder.remainingAmount;

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center space-x-4">
          <Link
            to="/preorders"
            className="inline-flex items-center text-sm text-gray-500 hover:text-gray-700"
          >
            <ArrowLeftIcon className="w-4 h-4 mr-1" />
            Back to Pre-Orders
          </Link>
        </div>
        
        <button
          onClick={() => setShowNotifications(!showNotifications)}
          className="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
        >
          <BellIcon className="w-4 h-4 mr-2" />
          Notifications
          {notifications.filter(n => !n.isRead).length > 0 && (
            <span className="ml-2 bg-red-500 text-white text-xs rounded-full px-2 py-0.5">
              {notifications.filter(n => !n.isRead).length}
            </span>
          )}
        </button>
      </div>

      {/* Status Banner */}
      <div className={`rounded-lg border-2 p-6 ${statusConfig.className}`}>
        <div className="flex items-start">
          <StatusIcon className="w-8 h-8 mr-4 mt-1" />
          <div className="flex-1">
            <h2 className="text-xl font-semibold mb-2">{statusConfig.label}</h2>
            <p className="mb-2">{statusConfig.description}</p>
            {statusConfig.nextStep && (
              <p className="text-sm font-medium">{statusConfig.nextStep}</p>
            )}
          </div>
        </div>
      </div>

      {/* Payment Due Warning */}
      {(isOverdue || isDueSoon) && preorder.status === 'ready_for_payment' && (
        <div className={`rounded-lg border-2 p-4 ${isOverdue ? 'bg-red-50 border-red-200' : 'bg-yellow-50 border-yellow-200'}`}>
          <div className="flex items-center">
            <ExclamationTriangleIcon className={`w-6 h-6 mr-3 ${isOverdue ? 'text-red-600' : 'text-yellow-600'}`} />
            <div>
              <h3 className={`font-semibold ${isOverdue ? 'text-red-800' : 'text-yellow-800'}`}>
                {isOverdue ? 'Payment Overdue' : 'Payment Due Soon'}
              </h3>
              <p className={`text-sm ${isOverdue ? 'text-red-700' : 'text-yellow-700'}`}>
                {isOverdue 
                  ? `Payment was due ${Math.abs(daysUntilDue!)} days ago. Please complete payment to avoid cancellation.`
                  : `Payment is due in ${daysUntilDue} days. Complete payment to secure your order.`
                }
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Notifications Panel */}
      {showNotifications && (
        <div className="bg-white border border-gray-200 rounded-lg">
          <div className="px-6 py-4 border-b border-gray-200">
            <h3 className="text-lg font-medium text-gray-900">Notifications</h3>
          </div>
          <div className="max-h-96 overflow-y-auto">
            {loadingNotifications ? (
              <div className="p-6 text-center">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mx-auto"></div>
                <p className="mt-2 text-sm text-gray-500">Loading notifications...</p>
              </div>
            ) : notifications.length === 0 ? (
              <div className="p-6 text-center">
                <BellIcon className="mx-auto h-12 w-12 text-gray-400" />
                <p className="mt-2 text-sm text-gray-500">No notifications yet</p>
              </div>
            ) : (
              <div className="divide-y divide-gray-200">
                {notifications.map((notification) => (
                  <div key={notification.id} className={`p-4 ${!notification.isRead ? 'bg-blue-50' : ''}`}>
                    <div className="flex items-start">
                      <div className={`w-2 h-2 rounded-full mt-2 mr-3 ${!notification.isRead ? 'bg-blue-600' : 'bg-gray-300'}`} />
                      <div className="flex-1">
                        <h4 className="text-sm font-medium text-gray-900">{notification.title}</h4>
                        <p className="text-sm text-gray-600 mt-1">{notification.message}</p>
                        <p className="text-xs text-gray-500 mt-2">{formatDateTime(notification.createdAt)}</p>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Product Information */}
        <div className="lg:col-span-2 space-y-6">
          <div className="bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden">
            <div className="p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-4">Product Details</h3>
              
              <div className="flex">
                <div className="w-48 h-48 flex-shrink-0 mr-6">
                  <img
                    src={preorder.product.images[0] || '/placeholder-product.jpg'}
                    alt={preorder.product.name}
                    className="w-full h-full object-cover rounded-lg"
                  />
                </div>
                
                <div className="flex-1">
                  <Link
                    to={`/products/${preorder.product.id}`}
                    className="text-xl font-semibold text-gray-900 hover:text-primary-600 transition-colors"
                  >
                    {preorder.product.name}
                  </Link>
                  
                  <p className="text-gray-600 mt-2">{preorder.product.description}</p>
                  
                  <div className="grid grid-cols-2 gap-4 mt-4">
                    <div>
                      <span className="text-sm text-gray-500">SKU</span>
                      <p className="font-medium">{preorder.product.sku}</p>
                    </div>
                    <div>
                      <span className="text-sm text-gray-500">Scale</span>
                      <p className="font-medium">{preorder.product.scale}</p>
                    </div>
                    <div>
                      <span className="text-sm text-gray-500">Material</span>
                      <p className="font-medium">{preorder.product.material}</p>
                    </div>
                    <div>
                      <span className="text-sm text-gray-500">Quantity</span>
                      <p className="font-medium">{preorder.quantity}</p>
                    </div>
                  </div>
                  
                  {preorder.product.features && preorder.product.features.length > 0 && (
                    <div className="mt-4">
                      <span className="text-sm text-gray-500">Features</span>
                      <div className="flex flex-wrap gap-2 mt-1">
                        {preorder.product.features.map((feature, index) => (
                          <span
                            key={index}
                            className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"
                          >
                            {feature}
                          </span>
                        ))}
                      </div>
                    </div>
                  )}
                </div>
              </div>
            </div>
          </div>

          {/* Timeline */}
          <div className="bg-white rounded-lg shadow-md border border-gray-200 p-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">Pre-Order Timeline</h3>
            
            <div className="space-y-4">
              <div className="flex items-center">
                <div className="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center mr-4">
                  <CheckCircleIcon className="w-5 h-5 text-green-600" />
                </div>
                <div>
                  <p className="font-medium text-gray-900">Pre-order Created</p>
                  <p className="text-sm text-gray-500">{formatDateTime(preorder.createdAt)}</p>
                </div>
              </div>
              
              <div className="flex items-center">
                <div className={`w-8 h-8 rounded-full flex items-center justify-center mr-4 ${
                  preorder.depositPaidAt ? 'bg-green-100' : 'bg-gray-100'
                }`}>
                  <CheckCircleIcon className={`w-5 h-5 ${
                    preorder.depositPaidAt ? 'text-green-600' : 'text-gray-400'
                  }`} />
                </div>
                <div>
                  <p className="font-medium text-gray-900">Deposit Payment</p>
                  <p className="text-sm text-gray-500">
                    {preorder.depositPaidAt 
                      ? `Paid on ${formatDateTime(preorder.depositPaidAt)}`
                      : 'Pending payment'
                    }
                  </p>
                </div>
              </div>
              
              <div className="flex items-center">
                <div className={`w-8 h-8 rounded-full flex items-center justify-center mr-4 ${
                  preorder.actualArrivalDate ? 'bg-green-100' : 'bg-gray-100'
                }`}>
                  <CheckCircleIcon className={`w-5 h-5 ${
                    preorder.actualArrivalDate ? 'text-green-600' : 'text-gray-400'
                  }`} />
                </div>
                <div>
                  <p className="font-medium text-gray-900">Product Arrival</p>
                  <p className="text-sm text-gray-500">
                    {preorder.actualArrivalDate 
                      ? `Arrived on ${formatDate(preorder.actualArrivalDate)}`
                      : `Expected ${formatDate(preorder.estimatedArrivalDate)}`
                    }
                  </p>
                </div>
              </div>
              
              <div className="flex items-center">
                <div className={`w-8 h-8 rounded-full flex items-center justify-center mr-4 ${
                  preorder.status === 'completed' ? 'bg-green-100' : 'bg-gray-100'
                }`}>
                  <CheckCircleIcon className={`w-5 h-5 ${
                    preorder.status === 'completed' ? 'text-green-600' : 'text-gray-400'
                  }`} />
                </div>
                <div>
                  <p className="font-medium text-gray-900">Final Payment</p>
                  <p className="text-sm text-gray-500">
                    {preorder.status === 'completed' 
                      ? 'Payment completed'
                      : preorder.fullPaymentDueDate 
                        ? `Due ${formatDate(preorder.fullPaymentDueDate)}`
                        : 'Pending product arrival'
                    }
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Payment Information & Actions */}
        <div className="space-y-6">
          {/* Payment Summary */}
          <div className="bg-white rounded-lg shadow-md border border-gray-200 p-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">Payment Summary</h3>
            
            <div className="space-y-3">
              <div className="flex justify-between">
                <span className="text-gray-600">Total Amount</span>
                <span className="font-semibold">{formatCurrency(totalAmount)}</span>
              </div>
              
              <div className="flex justify-between">
                <span className="text-gray-600">Deposit Amount</span>
                <div className="text-right">
                  <span className={`font-semibold ${preorder.depositPaidAt ? 'text-green-600' : 'text-gray-900'}`}>
                    {formatCurrency(preorder.depositAmount)}
                  </span>
                  {preorder.depositPaidAt && (
                    <CheckCircleIcon className="w-4 h-4 inline ml-1 text-green-600" />
                  )}
                </div>
              </div>
              
              <div className="flex justify-between">
                <span className="text-gray-600">Remaining Amount</span>
                <span className="font-semibold">{formatCurrency(preorder.remainingAmount)}</span>
              </div>
              
              <div className="border-t border-gray-200 pt-3">
                <div className="flex justify-between">
                  <span className="font-semibold text-gray-900">Amount Due</span>
                  <span className="font-bold text-lg">
                    {preorder.status === 'deposit_pending' 
                      ? formatCurrency(preorder.depositAmount)
                      : preorder.status === 'ready_for_payment'
                        ? formatCurrency(preorder.remainingAmount)
                        : formatCurrency(0)
                    }
                  </span>
                </div>
              </div>
            </div>
          </div>

          {/* Action Buttons */}
          <div className="space-y-3">
            {preorder.status === 'deposit_pending' && onPayDeposit && (
              <button
                onClick={() => onPayDeposit(preorder.id)}
                className="w-full bg-primary-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-primary-700 transition-colors"
              >
                Pay Deposit ({formatCurrency(preorder.depositAmount)})
              </button>
            )}
            
            {preorder.status === 'ready_for_payment' && onCompletePayment && (
              <button
                onClick={() => onCompletePayment(preorder.id)}
                className={`w-full px-6 py-3 rounded-lg font-medium transition-colors ${
                  isOverdue 
                    ? 'bg-red-600 text-white hover:bg-red-700' 
                    : isDueSoon 
                      ? 'bg-yellow-600 text-white hover:bg-yellow-700'
                      : 'bg-green-600 text-white hover:bg-green-700'
                }`}
              >
                {isOverdue ? 'Pay Now (Overdue)' : 'Complete Payment'} ({formatCurrency(preorder.remainingAmount)})
              </button>
            )}
            
            {(preorder.status === 'deposit_pending' || preorder.status === 'deposit_paid') && onCancel && (
              <button
                onClick={() => onCancel(preorder.id)}
                className="w-full border border-red-300 text-red-700 px-6 py-3 rounded-lg font-medium hover:bg-red-50 transition-colors"
              >
                Cancel Pre-Order
              </button>
            )}
          </div>

          {/* Important Information */}
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div className="flex items-start">
              <InformationCircleIcon className="w-5 h-5 text-blue-600 mr-2 mt-0.5" />
              <div className="text-sm text-blue-800">
                <p className="font-medium mb-1">Important Information</p>
                <ul className="space-y-1 text-xs">
                  <li>• Deposits are non-refundable once the product arrives</li>
                  <li>• You'll be notified when your product arrives</li>
                  <li>• Complete payment within 30 days of arrival notification</li>
                  <li>• Failure to pay may result in pre-order cancellation</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default PreOrderDetail;