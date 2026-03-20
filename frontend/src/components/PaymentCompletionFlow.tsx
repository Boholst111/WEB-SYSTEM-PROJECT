import React, { useState } from 'react';
import { PreOrder } from '../types';
import { useAppDispatch, useAppSelector } from '../store';
import { completePayment } from '../store/slices/preorderSlice';
import { 
  CreditCardIcon,
  DevicePhoneMobileIcon,
  BanknotesIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  XMarkIcon,
  InformationCircleIcon,
  ClockIcon
} from '@heroicons/react/24/outline';

interface PaymentCompletionFlowProps {
  preorder: PreOrder;
  onSuccess?: () => void;
  onCancel?: () => void;
}

const PaymentCompletionFlow: React.FC<PaymentCompletionFlowProps> = ({
  preorder,
  onSuccess,
  onCancel
}) => {
  const dispatch = useAppDispatch();
  const { isProcessingPayment, error } = useAppSelector(state => state.preorders);
  
  const [step, setStep] = useState<'method' | 'confirmation' | 'processing' | 'success' | 'error'>('method');
  const [selectedMethod, setSelectedMethod] = useState<'gcash' | 'maya' | 'bank_transfer'>('gcash');
  const [paymentResponse, setPaymentResponse] = useState<any>(null);

  const paymentMethods = [
    {
      id: 'gcash' as const,
      name: 'GCash',
      description: 'Quick payment using your GCash mobile wallet',
      icon: DevicePhoneMobileIcon,
      color: 'blue',
      processingTime: 'Instant',
      popular: true
    },
    {
      id: 'maya' as const,
      name: 'Maya (PayMaya)',
      description: 'Secure payment using your Maya digital wallet',
      icon: CreditCardIcon,
      color: 'green',
      processingTime: 'Instant',
      popular: true
    },
    {
      id: 'bank_transfer' as const,
      name: 'Bank Transfer',
      description: 'Direct transfer from your bank account',
      icon: BanknotesIcon,
      color: 'gray',
      processingTime: '1-2 business days',
      popular: false
    }
  ];

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

  const handleMethodSelect = (method: 'gcash' | 'maya' | 'bank_transfer') => {
    setSelectedMethod(method);
    setStep('confirmation');
  };

  const handleConfirmPayment = async () => {
    setStep('processing');
    
    try {
      const result = await dispatch(completePayment({
        id: preorder.id,
        paymentMethod: selectedMethod
      })).unwrap();

      setPaymentResponse(result.paymentResponse);
      setStep('success');
      
      if (onSuccess) {
        setTimeout(onSuccess, 2000);
      }
    } catch (error: any) {
      console.error('Payment failed:', error);
      setStep('error');
    }
  };

  const renderMethodSelection = () => (
    <div className="space-y-6">
      <div className="text-center mb-6">
        <h2 className="text-2xl font-bold text-gray-900 mb-2">Complete Your Payment</h2>
        <p className="text-gray-600">
          Pay the remaining {formatCurrency(preorder.remainingAmount)} to complete your pre-order
        </p>
        
        {/* Payment Due Status */}
        {daysUntilDue !== null && (
          <div className={`mt-4 inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${
            isOverdue 
              ? 'bg-red-100 text-red-800' 
              : isDueSoon 
                ? 'bg-yellow-100 text-yellow-800'
                : 'bg-blue-100 text-blue-800'
          }`}>
            <ClockIcon className="w-4 h-4 mr-1" />
            {isOverdue 
              ? `Payment overdue by ${Math.abs(daysUntilDue)} days`
              : isDueSoon 
                ? `Payment due in ${daysUntilDue} days`
                : `Payment due in ${daysUntilDue} days`
            }
          </div>
        )}
      </div>

      {/* Product Summary */}
      <div className="bg-gray-50 rounded-lg p-4 mb-6">
        <div className="flex items-center">
          <img
            src={preorder.product.images[0] || '/placeholder-product.jpg'}
            alt={preorder.product.name}
            className="w-16 h-16 object-cover rounded-lg mr-4"
          />
          <div className="flex-1">
            <h3 className="font-semibold text-gray-900">{preorder.product.name}</h3>
            <p className="text-sm text-gray-600">Quantity: {preorder.quantity}</p>
            <p className="text-sm text-gray-600">
              Deposit Paid: {formatCurrency(preorder.depositAmount)} ✓
            </p>
          </div>
          <div className="text-right">
            <p className="text-sm text-gray-600">Amount Due</p>
            <p className="text-xl font-bold text-gray-900">{formatCurrency(preorder.remainingAmount)}</p>
          </div>
        </div>
      </div>

      {/* Payment Methods */}
      <div className="space-y-3">
        <h3 className="font-semibold text-gray-900 mb-3">Choose Payment Method</h3>
        {paymentMethods.map((method) => {
          const Icon = method.icon;
          return (
            <button
              key={method.id}
              onClick={() => handleMethodSelect(method.id)}
              className={`w-full p-4 border-2 rounded-lg text-left transition-all hover:border-${method.color}-300 hover:bg-${method.color}-50 focus:outline-none focus:ring-2 focus:ring-${method.color}-500 focus:border-${method.color}-500`}
            >
              <div className="flex items-center">
                <div className={`w-12 h-12 rounded-lg bg-${method.color}-100 flex items-center justify-center mr-4`}>
                  <Icon className={`w-6 h-6 text-${method.color}-600`} />
                </div>
                <div className="flex-1">
                  <div className="flex items-center">
                    <h4 className="font-semibold text-gray-900">{method.name}</h4>
                    {method.popular && (
                      <span className="ml-2 bg-primary-100 text-primary-800 text-xs font-medium px-2 py-1 rounded-full">
                        Popular
                      </span>
                    )}
                  </div>
                  <p className="text-sm text-gray-600 mt-1">{method.description}</p>
                  <p className="text-xs text-gray-500 mt-1">Processing: {method.processingTime}</p>
                </div>
                <div className="text-gray-400">
                  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                  </svg>
                </div>
              </div>
            </button>
          );
        })}
      </div>

      {/* Important Notice */}
      {isOverdue && (
        <div className="bg-red-50 border border-red-200 rounded-lg p-4">
          <div className="flex items-start">
            <ExclamationTriangleIcon className="w-5 h-5 text-red-600 mr-2 mt-0.5" />
            <div className="text-sm text-red-800">
              <p className="font-medium mb-1">Payment Overdue</p>
              <p>Your payment is overdue. Please complete payment immediately to avoid pre-order cancellation.</p>
            </div>
          </div>
        </div>
      )}
    </div>
  );

  const renderConfirmation = () => {
    const method = paymentMethods.find(m => m.id === selectedMethod);
    const Icon = method?.icon || CreditCardIcon;
    const totalAmount = preorder.depositAmount + preorder.remainingAmount;

    return (
      <div className="space-y-6">
        <div className="text-center">
          <div className={`w-16 h-16 rounded-full bg-${method?.color}-100 flex items-center justify-center mx-auto mb-4`}>
            <Icon className={`w-8 h-8 text-${method?.color}-600`} />
          </div>
          <h2 className="text-2xl font-bold text-gray-900 mb-2">Confirm Final Payment</h2>
          <p className="text-gray-600">
            Review your payment details before completing your pre-order
          </p>
        </div>

        {/* Payment Summary */}
        <div className="bg-gray-50 rounded-lg p-6">
          <h3 className="font-semibold text-gray-900 mb-4">Payment Summary</h3>
          <div className="space-y-3">
            <div className="flex items-center">
              <img
                src={preorder.product.images[0] || '/placeholder-product.jpg'}
                alt={preorder.product.name}
                className="w-12 h-12 object-cover rounded-lg mr-3"
              />
              <div className="flex-1">
                <p className="font-medium text-gray-900">{preorder.product.name}</p>
                <p className="text-sm text-gray-600">Quantity: {preorder.quantity}</p>
              </div>
            </div>
            
            <div className="border-t border-gray-200 pt-3 space-y-2">
              <div className="flex justify-between">
                <span className="text-gray-600">Total Product Price</span>
                <span className="font-medium">{formatCurrency(totalAmount)}</span>
              </div>
              <div className="flex justify-between text-green-600">
                <span>Deposit Paid</span>
                <span>-{formatCurrency(preorder.depositAmount)}</span>
              </div>
              <div className="border-t border-gray-200 pt-2">
                <div className="flex justify-between">
                  <span className="font-semibold text-gray-900">Amount Due</span>
                  <span className="font-bold text-xl">{formatCurrency(preorder.remainingAmount)}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Payment Method */}
        <div className="bg-gray-50 rounded-lg p-6">
          <h3 className="font-semibold text-gray-900 mb-4">Payment Method</h3>
          <div className="flex items-center">
            <div className={`w-10 h-10 rounded-lg bg-${method?.color}-100 flex items-center justify-center mr-3`}>
              <Icon className={`w-5 h-5 text-${method?.color}-600`} />
            </div>
            <div>
              <p className="font-medium">{method?.name}</p>
              <p className="text-sm text-gray-600">Processing: {method?.processingTime}</p>
            </div>
          </div>
        </div>

        {/* Delivery Information */}
        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <div className="flex items-start">
            <InformationCircleIcon className="w-5 h-5 text-blue-600 mr-2 mt-0.5" />
            <div className="text-sm text-blue-800">
              <p className="font-medium mb-1">What happens next?</p>
              <ul className="space-y-1 text-xs">
                <li>• Payment will be processed immediately</li>
                <li>• Your order will be prepared for shipment</li>
                <li>• You'll receive tracking information via email</li>
                <li>• Estimated delivery: 3-5 business days</li>
              </ul>
            </div>
          </div>
        </div>

        <div className="flex gap-3">
          <button
            onClick={() => setStep('method')}
            className="flex-1 px-6 py-3 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition-colors"
          >
            Back
          </button>
          <button
            onClick={handleConfirmPayment}
            disabled={isProcessingPayment}
            className={`flex-1 px-6 py-3 rounded-lg font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed ${
              isOverdue 
                ? 'bg-red-600 text-white hover:bg-red-700' 
                : 'bg-primary-600 text-white hover:bg-primary-700'
            }`}
          >
            {isProcessingPayment ? 'Processing...' : `Pay ${formatCurrency(preorder.remainingAmount)}`}
          </button>
        </div>
      </div>
    );
  };

  const renderProcessing = () => (
    <div className="text-center py-12">
      <div className="animate-spin rounded-full h-16 w-16 border-b-2 border-primary-600 mx-auto mb-4"></div>
      <h2 className="text-2xl font-bold text-gray-900 mb-2">Processing Payment</h2>
      <p className="text-gray-600">Please wait while we process your final payment...</p>
      <p className="text-sm text-gray-500 mt-4">This may take a few moments. Please do not close this window.</p>
    </div>
  );

  const renderSuccess = () => (
    <div className="text-center py-12">
      <div className="w-20 h-20 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-6">
        <CheckCircleIcon className="w-12 h-12 text-green-600" />
      </div>
      <h2 className="text-3xl font-bold text-gray-900 mb-2">Payment Complete!</h2>
      <p className="text-gray-600 mb-6">
        Congratulations! Your pre-order for {preorder.product.name} is now complete.
      </p>
      
      {paymentResponse && (
        <div className="bg-gray-50 rounded-lg p-6 mb-6 text-left max-w-md mx-auto">
          <h3 className="font-semibold text-gray-900 mb-3">Payment Details</h3>
          <div className="space-y-2 text-sm">
            <div className="flex justify-between">
              <span className="text-gray-600">Reference Number</span>
              <span className="font-medium">{paymentResponse.referenceNumber}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-gray-600">Amount Paid</span>
              <span className="font-medium">{formatCurrency(preorder.remainingAmount)}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-gray-600">Status</span>
              <span className="font-medium text-green-600">Completed</span>
            </div>
          </div>
        </div>
      )}
      
      <div className="space-y-3 text-gray-600 mb-8">
        <div className="flex items-center justify-center">
          <CheckCircleIcon className="w-5 h-5 text-green-600 mr-2" />
          <span>Payment processed successfully</span>
        </div>
        <div className="flex items-center justify-center">
          <CheckCircleIcon className="w-5 h-5 text-green-600 mr-2" />
          <span>Order is being prepared for shipment</span>
        </div>
        <div className="flex items-center justify-center">
          <CheckCircleIcon className="w-5 h-5 text-green-600 mr-2" />
          <span>Tracking information will be sent via email</span>
        </div>
      </div>
      
      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-left max-w-md mx-auto">
        <h4 className="font-semibold text-blue-900 mb-2">What's Next?</h4>
        <div className="text-sm text-blue-800 space-y-1">
          <p>• Your order will be shipped within 1-2 business days</p>
          <p>• You'll receive tracking information via email</p>
          <p>• Estimated delivery: 3-5 business days</p>
          <p>• You can track your order in your account</p>
        </div>
      </div>
      
      <button
        onClick={onSuccess}
        className="bg-primary-600 text-white px-8 py-3 rounded-lg font-medium hover:bg-primary-700 transition-colors"
      >
        View My Orders
      </button>
    </div>
  );

  const renderError = () => (
    <div className="text-center py-12">
      <div className="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
        <ExclamationTriangleIcon className="w-10 h-10 text-red-600" />
      </div>
      <h2 className="text-2xl font-bold text-gray-900 mb-2">Payment Failed</h2>
      <p className="text-gray-600 mb-6">
        We couldn't process your payment. Please try again or contact support if the problem persists.
      </p>
      
      {error && (
        <div className="bg-red-50 border border-red-200 rounded-lg p-4 mb-6 text-left max-w-md mx-auto">
          <p className="text-sm text-red-800">{error}</p>
        </div>
      )}
      
      <div className="flex gap-3 justify-center">
        <button
          onClick={() => setStep('method')}
          className="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition-colors"
        >
          Try Different Method
        </button>
        <button
          onClick={() => setStep('confirmation')}
          className="px-6 py-3 bg-primary-600 text-white rounded-lg font-medium hover:bg-primary-700 transition-colors"
        >
          Try Again
        </button>
      </div>
    </div>
  );

  return (
    <div className="max-w-2xl mx-auto">
      {/* Header */}
      <div className="flex items-center justify-between mb-8">
        <h1 className="text-3xl font-bold text-gray-900">Complete Payment</h1>
        {onCancel && (
          <button
            onClick={onCancel}
            className="p-2 text-gray-400 hover:text-gray-600 transition-colors"
          >
            <XMarkIcon className="w-6 h-6" />
          </button>
        )}
      </div>

      {/* Progress Indicator */}
      <div className="mb-8">
        <div className="flex items-center">
          {['method', 'confirmation'].map((stepName, index) => (
            <React.Fragment key={stepName}>
              <div className={`flex items-center justify-center w-8 h-8 rounded-full text-sm font-medium ${
                step === stepName 
                  ? 'bg-primary-600 text-white' 
                  : ['method', 'confirmation'].indexOf(step) > index
                    ? 'bg-green-600 text-white'
                    : 'bg-gray-200 text-gray-600'
              }`}>
                {['method', 'confirmation'].indexOf(step) > index ? (
                  <CheckCircleIcon className="w-5 h-5" />
                ) : (
                  index + 1
                )}
              </div>
              {index < 1 && (
                <div className={`flex-1 h-1 mx-4 ${
                  ['method', 'confirmation'].indexOf(step) > index 
                    ? 'bg-green-600' 
                    : 'bg-gray-200'
                }`} />
              )}
            </React.Fragment>
          ))}
        </div>
      </div>

      {/* Content */}
      <div className="bg-white rounded-lg shadow-lg border border-gray-200 p-8">
        {step === 'method' && renderMethodSelection()}
        {step === 'confirmation' && renderConfirmation()}
        {step === 'processing' && renderProcessing()}
        {step === 'success' && renderSuccess()}
        {step === 'error' && renderError()}
      </div>
    </div>
  );
};

export default PaymentCompletionFlow;