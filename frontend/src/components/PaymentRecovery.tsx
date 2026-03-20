import React, { useState } from 'react';
import { 
  ExclamationTriangleIcon,
  ArrowPathIcon,
  CreditCardIcon,
  PhoneIcon,
  ChatBubbleLeftRightIcon,
  InformationCircleIcon
} from '@heroicons/react/24/outline';
import { PaymentStatusResponse } from '../services/paymentApi';

interface PaymentRecoveryProps {
  paymentData: PaymentStatusResponse;
  onRetryPayment: () => void;
  onContactSupport: () => void;
  onChangePaymentMethod: () => void;
  className?: string;
}

interface RecoveryOption {
  id: string;
  title: string;
  description: string;
  icon: React.ComponentType<any>;
  action: () => void;
  primary?: boolean;
  disabled?: boolean;
}

const PaymentRecovery: React.FC<PaymentRecoveryProps> = ({
  paymentData,
  onRetryPayment,
  onContactSupport,
  onChangePaymentMethod,
  className = ''
}) => {
  const [selectedOption, setSelectedOption] = useState<string | null>(null);
  const [showDetails, setShowDetails] = useState(false);

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2,
    }).format(amount);
  };

  const getFailureReason = (message?: string) => {
    if (!message) return 'Payment processing failed';
    
    const lowerMessage = message.toLowerCase();
    
    if (lowerMessage.includes('insufficient')) {
      return 'Insufficient funds in your account';
    } else if (lowerMessage.includes('expired')) {
      return 'Payment session has expired';
    } else if (lowerMessage.includes('cancelled')) {
      return 'Payment was cancelled by user';
    } else if (lowerMessage.includes('network') || lowerMessage.includes('timeout')) {
      return 'Network connection issue';
    } else if (lowerMessage.includes('invalid')) {
      return 'Invalid payment information';
    } else if (lowerMessage.includes('declined')) {
      return 'Payment was declined by your bank';
    }
    
    return message;
  };

  const getRecoveryOptions = (): RecoveryOption[] => {
    const failureReason = getFailureReason(paymentData.message);
    const options: RecoveryOption[] = [];

    // Retry with same method (for network issues, timeouts)
    if (failureReason.toLowerCase().includes('network') || 
        failureReason.toLowerCase().includes('timeout') ||
        failureReason.toLowerCase().includes('expired')) {
      options.push({
        id: 'retry',
        title: 'Retry Payment',
        description: 'Try the same payment method again',
        icon: ArrowPathIcon,
        action: onRetryPayment,
        primary: true
      });
    }

    // Change payment method (for declined, insufficient funds)
    options.push({
      id: 'change_method',
      title: 'Try Different Payment Method',
      description: 'Use a different payment method to complete your purchase',
      icon: CreditCardIcon,
      action: onChangePaymentMethod,
      primary: !options.some(opt => opt.primary)
    });

    // Contact support (always available)
    options.push({
      id: 'support',
      title: 'Contact Support',
      description: 'Get help from our customer support team',
      icon: ChatBubbleLeftRightIcon,
      action: onContactSupport
    });

    return options;
  };

  const recoveryOptions = getRecoveryOptions();
  const failureReason = getFailureReason(paymentData.message);

  const getRecommendedAction = () => {
    const lowerReason = failureReason.toLowerCase();
    
    if (lowerReason.includes('insufficient')) {
      return 'Check your account balance or try a different payment method.';
    } else if (lowerReason.includes('expired')) {
      return 'Your payment session has expired. Please try again.';
    } else if (lowerReason.includes('network') || lowerReason.includes('timeout')) {
      return 'This appears to be a temporary network issue. Please try again.';
    } else if (lowerReason.includes('declined')) {
      return 'Contact your bank or try a different payment method.';
    } else if (lowerReason.includes('invalid')) {
      return 'Please check your payment information and try again.';
    }
    
    return 'Please try a different payment method or contact support for assistance.';
  };

  return (
    <div className={`bg-white rounded-lg shadow-md p-6 ${className}`}>
      {/* Header */}
      <div className="text-center mb-6">
        <div className="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mb-4">
          <ExclamationTriangleIcon className="w-8 h-8 text-red-600" />
        </div>
        <h2 className="text-2xl font-bold text-gray-900 mb-2">
          Payment Failed
        </h2>
        <p className="text-gray-600 mb-4">
          We couldn't process your payment of {formatCurrency(paymentData.amount)}
        </p>
      </div>

      {/* Failure Details */}
      <div className="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
        <div className="flex items-start">
          <ExclamationTriangleIcon className="w-5 h-5 text-red-600 mr-3 mt-0.5 flex-shrink-0" />
          <div className="flex-1">
            <h3 className="text-red-800 font-medium mb-1">What went wrong?</h3>
            <p className="text-red-700 text-sm mb-2">{failureReason}</p>
            <p className="text-red-600 text-sm">{getRecommendedAction()}</p>
          </div>
        </div>
        
        {/* Show/Hide Details Toggle */}
        <button
          onClick={() => setShowDetails(!showDetails)}
          className="mt-3 text-sm text-red-600 hover:text-red-800 flex items-center"
        >
          <InformationCircleIcon className="w-4 h-4 mr-1" />
          {showDetails ? 'Hide' : 'Show'} technical details
        </button>
        
        {showDetails && (
          <div className="mt-3 p-3 bg-red-100 rounded border text-xs">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
              <div>
                <span className="font-medium">Payment ID:</span>
                <p className="font-mono break-all">{paymentData.id}</p>
              </div>
              <div>
                <span className="font-medium">Reference:</span>
                <p className="font-mono break-all">{paymentData.referenceNumber || 'N/A'}</p>
              </div>
              <div>
                <span className="font-medium">Method:</span>
                <p className="capitalize">{paymentData.paymentMethod.replace('_', ' ')}</p>
              </div>
              <div>
                <span className="font-medium">Status:</span>
                <p className="capitalize">{paymentData.status}</p>
              </div>
              {paymentData.message && (
                <div className="md:col-span-2">
                  <span className="font-medium">Error Message:</span>
                  <p className="break-words">{paymentData.message}</p>
                </div>
              )}
            </div>
          </div>
        )}
      </div>

      {/* Recovery Options */}
      <div className="space-y-4 mb-6">
        <h3 className="text-lg font-medium text-gray-900">What would you like to do?</h3>
        
        {recoveryOptions.map((option) => {
          const Icon = option.icon;
          const isSelected = selectedOption === option.id;
          
          return (
            <button
              key={option.id}
              onClick={() => {
                setSelectedOption(option.id);
                option.action();
              }}
              disabled={option.disabled}
              className={`
                w-full p-4 border rounded-lg text-left transition-all duration-200
                ${option.disabled 
                  ? 'opacity-50 cursor-not-allowed border-gray-200' 
                  : 'hover:bg-gray-50 border-gray-200 hover:border-gray-300'
                }
                ${option.primary 
                  ? 'ring-2 ring-blue-200 border-blue-300 bg-blue-50' 
                  : ''
                }
                ${isSelected 
                  ? 'ring-2 ring-blue-500 border-blue-500' 
                  : ''
                }
              `}
            >
              <div className="flex items-start">
                <div className={`
                  flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center mr-4
                  ${option.primary ? 'bg-blue-100' : 'bg-gray-100'}
                `}>
                  <Icon className={`
                    w-5 h-5 
                    ${option.primary ? 'text-blue-600' : 'text-gray-600'}
                  `} />
                </div>
                <div className="flex-1">
                  <h4 className={`
                    font-medium mb-1
                    ${option.primary ? 'text-blue-900' : 'text-gray-900'}
                  `}>
                    {option.title}
                    {option.primary && (
                      <span className="ml-2 px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">
                        Recommended
                      </span>
                    )}
                  </h4>
                  <p className={`
                    text-sm
                    ${option.primary ? 'text-blue-700' : 'text-gray-600'}
                  `}>
                    {option.description}
                  </p>
                </div>
              </div>
            </button>
          );
        })}
      </div>

      {/* Support Information */}
      <div className="bg-gray-50 border border-gray-200 rounded-lg p-4">
        <div className="flex items-start">
          <PhoneIcon className="w-5 h-5 text-gray-600 mr-3 mt-0.5 flex-shrink-0" />
          <div>
            <h4 className="font-medium text-gray-900 mb-1">Need immediate help?</h4>
            <p className="text-gray-600 text-sm mb-2">
              Our customer support team is available to assist you with payment issues.
            </p>
            <div className="text-sm text-gray-700">
              <p>Email: support@diecastempire.com</p>
              <p>Phone: +63 2 8123 4567</p>
              <p>Hours: Mon-Fri 9AM-6PM (PHT)</p>
            </div>
          </div>
        </div>
      </div>

      {/* Security Notice */}
      <div className="mt-4 flex items-center justify-center text-xs text-gray-500">
        <svg className="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
        Your payment information is always secure and encrypted
      </div>
    </div>
  );
};

export default PaymentRecovery;