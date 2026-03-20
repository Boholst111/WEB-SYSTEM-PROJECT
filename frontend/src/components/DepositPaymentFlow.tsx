import React, { useState } from 'react';
import { PreOrder, PaymentMethod } from '../types';
import { useAppDispatch, useAppSelector } from '../store';
import { payDeposit } from '../store/slices/preorderSlice';
import { 
  CreditCardIcon,
  DevicePhoneMobileIcon,
  BanknotesIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  XMarkIcon,
  InformationCircleIcon
} from '@heroicons/react/24/outline';

interface DepositPaymentFlowProps {
  preorder: PreOrder;
  onSuccess?: () => void;
  onCancel?: () => void;
}

interface PaymentFormData {
  paymentMethod: 'gcash' | 'maya' | 'bank_transfer';
  phone?: string;
  accountNumber?: string;
  accountName?: string;
  bankName?: string;
}

const DepositPaymentFlow: React.FC<DepositPaymentFlowProps> = ({
  preorder,
  onSuccess,
  onCancel
}) => {
  const dispatch = useAppDispatch();
  const { isProcessingPayment, error } = useAppSelector(state => state.preorders);
  
  const [step, setStep] = useState<'method' | 'details' | 'confirmation' | 'processing' | 'success' | 'error'>('method');
  const [formData, setFormData] = useState<PaymentFormData>({
    paymentMethod: 'gcash'
  });
  const [paymentResponse, setPaymentResponse] = useState<any>(null);

  const paymentMethods = [
    {
      id: 'gcash' as const,
      name: 'GCash',
      description: 'Pay using your GCash mobile wallet',
      icon: DevicePhoneMobileIcon,
      color: 'blue',
      popular: true
    },
    {
      id: 'maya' as const,
      name: 'Maya (PayMaya)',
      description: 'Pay using your Maya digital wallet',
      icon: CreditCardIcon,
      color: 'green',
      popular: true
    },
    {
      id: 'bank_transfer' as const,
      name: 'Bank Transfer',
      description: 'Direct bank transfer payment',
      icon: BanknotesIcon,
      color: 'gray',
      popular: false
    }
  ];

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP'
    }).format(amount);
  };

  const handleMethodSelect = (method: 'gcash' | 'maya' | 'bank_transfer') => {
    setFormData({ ...formData, paymentMethod: method });
    setStep('details');
  };

  const handleFormSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setStep('confirmation');
  };

  const handleConfirmPayment = async () => {
    setStep('processing');
    
    try {
      const gatewayData: any = {};
      
      if (formData.paymentMethod === 'gcash' || formData.paymentMethod === 'maya') {
        gatewayData.phone = formData.phone;
      } else if (formData.paymentMethod === 'bank_transfer') {
        gatewayData.accountNumber = formData.accountNumber;
        gatewayData.accountName = formData.accountName;
        gatewayData.bankName = formData.bankName;
      }

      const result = await dispatch(payDeposit({
        id: preorder.id,
        request: {
          paymentMethod: formData.paymentMethod,
          gatewayData
        }
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
    <div className="space-y-4">
      <div className="text-center mb-6">
        <h2 className="text-2xl font-bold text-gray-900 mb-2">Choose Payment Method</h2>
        <p className="text-gray-600">
          Pay your deposit of {formatCurrency(preorder.depositAmount)} to secure your pre-order
        </p>
      </div>

      <div className="space-y-3">
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
                    <h3 className="font-semibold text-gray-900">{method.name}</h3>
                    {method.popular && (
                      <span className="ml-2 bg-primary-100 text-primary-800 text-xs font-medium px-2 py-1 rounded-full">
                        Popular
                      </span>
                    )}
                  </div>
                  <p className="text-sm text-gray-600 mt-1">{method.description}</p>
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
    </div>
  );

  const renderPaymentDetails = () => {
    const selectedMethod = paymentMethods.find(m => m.id === formData.paymentMethod);
    const Icon = selectedMethod?.icon || CreditCardIcon;

    return (
      <div className="space-y-6">
        <div className="text-center">
          <div className={`w-16 h-16 rounded-full bg-${selectedMethod?.color}-100 flex items-center justify-center mx-auto mb-4`}>
            <Icon className={`w-8 h-8 text-${selectedMethod?.color}-600`} />
          </div>
          <h2 className="text-2xl font-bold text-gray-900 mb-2">{selectedMethod?.name} Payment</h2>
          <p className="text-gray-600">
            Enter your payment details to pay {formatCurrency(preorder.depositAmount)}
          </p>
        </div>

        <form onSubmit={handleFormSubmit} className="space-y-4">
          {(formData.paymentMethod === 'gcash' || formData.paymentMethod === 'maya') && (
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Mobile Number
              </label>
              <input
                type="tel"
                value={formData.phone || ''}
                onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                placeholder="09123456789"
                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                required
              />
              <p className="text-xs text-gray-500 mt-1">
                Enter the mobile number linked to your {selectedMethod?.name} account
              </p>
            </div>
          )}

          {formData.paymentMethod === 'bank_transfer' && (
            <>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Bank Name
                </label>
                <select
                  value={formData.bankName || ''}
                  onChange={(e) => setFormData({ ...formData, bankName: e.target.value })}
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  required
                >
                  <option value="">Select your bank</option>
                  <option value="BPI">Bank of the Philippine Islands (BPI)</option>
                  <option value="BDO">Banco de Oro (BDO)</option>
                  <option value="Metrobank">Metrobank</option>
                  <option value="PNB">Philippine National Bank (PNB)</option>
                  <option value="UnionBank">UnionBank</option>
                  <option value="Security Bank">Security Bank</option>
                  <option value="Other">Other</option>
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Account Number
                </label>
                <input
                  type="text"
                  value={formData.accountNumber || ''}
                  onChange={(e) => setFormData({ ...formData, accountNumber: e.target.value })}
                  placeholder="Enter your account number"
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Account Name
                </label>
                <input
                  type="text"
                  value={formData.accountName || ''}
                  onChange={(e) => setFormData({ ...formData, accountName: e.target.value })}
                  placeholder="Enter account holder name"
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  required
                />
              </div>
            </>
          )}

          <div className="flex gap-3 pt-4">
            <button
              type="button"
              onClick={() => setStep('method')}
              className="flex-1 px-6 py-3 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition-colors"
            >
              Back
            </button>
            <button
              type="submit"
              className="flex-1 bg-primary-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-primary-700 transition-colors"
            >
              Continue
            </button>
          </div>
        </form>
      </div>
    );
  };

  const renderConfirmation = () => {
    const selectedMethod = paymentMethods.find(m => m.id === formData.paymentMethod);
    const totalAmount = preorder.depositAmount + preorder.remainingAmount;

    return (
      <div className="space-y-6">
        <div className="text-center">
          <h2 className="text-2xl font-bold text-gray-900 mb-2">Confirm Payment</h2>
          <p className="text-gray-600">Please review your payment details before proceeding</p>
        </div>

        {/* Order Summary */}
        <div className="bg-gray-50 rounded-lg p-6">
          <h3 className="font-semibold text-gray-900 mb-4">Order Summary</h3>
          <div className="space-y-2">
            <div className="flex justify-between">
              <span className="text-gray-600">Product</span>
              <span className="font-medium">{preorder.product.name}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-gray-600">Quantity</span>
              <span className="font-medium">{preorder.quantity}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-gray-600">Total Amount</span>
              <span className="font-medium">{formatCurrency(totalAmount)}</span>
            </div>
            <div className="border-t border-gray-200 pt-2 mt-2">
              <div className="flex justify-between">
                <span className="text-gray-600">Deposit Amount</span>
                <span className="font-bold text-lg">{formatCurrency(preorder.depositAmount)}</span>
              </div>
              <div className="flex justify-between text-sm text-gray-500">
                <span>Remaining Amount</span>
                <span>{formatCurrency(preorder.remainingAmount)}</span>
              </div>
            </div>
          </div>
        </div>

        {/* Payment Method */}
        <div className="bg-gray-50 rounded-lg p-6">
          <h3 className="font-semibold text-gray-900 mb-4">Payment Method</h3>
          <div className="flex items-center">
            <div className={`w-10 h-10 rounded-lg bg-${selectedMethod?.color}-100 flex items-center justify-center mr-3`}>
              {selectedMethod && <selectedMethod.icon className={`w-5 h-5 text-${selectedMethod.color}-600`} />}
            </div>
            <div>
              <p className="font-medium">{selectedMethod?.name}</p>
              {formData.phone && (
                <p className="text-sm text-gray-600">{formData.phone}</p>
              )}
              {formData.accountNumber && (
                <p className="text-sm text-gray-600">
                  {formData.bankName} - ****{formData.accountNumber.slice(-4)}
                </p>
              )}
            </div>
          </div>
        </div>

        {/* Important Notice */}
        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <div className="flex items-start">
            <InformationCircleIcon className="w-5 h-5 text-blue-600 mr-2 mt-0.5" />
            <div className="text-sm text-blue-800">
              <p className="font-medium mb-1">Important Notice</p>
              <ul className="space-y-1 text-xs">
                <li>• Your deposit secures your pre-order and is non-refundable once the product arrives</li>
                <li>• You'll be notified when the product arrives and final payment is due</li>
                <li>• Final payment must be completed within 30 days of arrival notification</li>
              </ul>
            </div>
          </div>
        </div>

        <div className="flex gap-3">
          <button
            onClick={() => setStep('details')}
            className="flex-1 px-6 py-3 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition-colors"
          >
            Back
          </button>
          <button
            onClick={handleConfirmPayment}
            disabled={isProcessingPayment}
            className="flex-1 bg-primary-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-primary-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isProcessingPayment ? 'Processing...' : `Pay ${formatCurrency(preorder.depositAmount)}`}
          </button>
        </div>
      </div>
    );
  };

  const renderProcessing = () => (
    <div className="text-center py-12">
      <div className="animate-spin rounded-full h-16 w-16 border-b-2 border-primary-600 mx-auto mb-4"></div>
      <h2 className="text-2xl font-bold text-gray-900 mb-2">Processing Payment</h2>
      <p className="text-gray-600">Please wait while we process your deposit payment...</p>
      <p className="text-sm text-gray-500 mt-4">This may take a few moments. Please do not close this window.</p>
    </div>
  );

  const renderSuccess = () => (
    <div className="text-center py-12">
      <div className="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-4">
        <CheckCircleIcon className="w-10 h-10 text-green-600" />
      </div>
      <h2 className="text-2xl font-bold text-gray-900 mb-2">Payment Successful!</h2>
      <p className="text-gray-600 mb-6">
        Your deposit of {formatCurrency(preorder.depositAmount)} has been processed successfully.
      </p>
      
      {paymentResponse && (
        <div className="bg-gray-50 rounded-lg p-4 mb-6 text-left max-w-md mx-auto">
          <h3 className="font-semibold text-gray-900 mb-2">Payment Details</h3>
          <div className="space-y-1 text-sm">
            <div className="flex justify-between">
              <span className="text-gray-600">Reference Number</span>
              <span className="font-medium">{paymentResponse.referenceNumber}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-gray-600">Status</span>
              <span className="font-medium text-green-600">Completed</span>
            </div>
          </div>
        </div>
      )}
      
      <div className="space-y-2 text-sm text-gray-600">
        <p>✓ Your pre-order is now secured</p>
        <p>✓ You'll receive email confirmation shortly</p>
        <p>✓ We'll notify you when the product arrives</p>
      </div>
      
      <button
        onClick={onSuccess}
        className="mt-6 bg-primary-600 text-white px-8 py-3 rounded-lg font-medium hover:bg-primary-700 transition-colors"
      >
        Continue
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
        We couldn't process your payment. Please try again or use a different payment method.
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
        <h1 className="text-3xl font-bold text-gray-900">Deposit Payment</h1>
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
          {['method', 'details', 'confirmation'].map((stepName, index) => (
            <React.Fragment key={stepName}>
              <div className={`flex items-center justify-center w-8 h-8 rounded-full text-sm font-medium ${
                step === stepName 
                  ? 'bg-primary-600 text-white' 
                  : ['method', 'details', 'confirmation'].indexOf(step) > index
                    ? 'bg-green-600 text-white'
                    : 'bg-gray-200 text-gray-600'
              }`}>
                {['method', 'details', 'confirmation'].indexOf(step) > index ? (
                  <CheckCircleIcon className="w-5 h-5" />
                ) : (
                  index + 1
                )}
              </div>
              {index < 2 && (
                <div className={`flex-1 h-1 mx-4 ${
                  ['method', 'details', 'confirmation'].indexOf(step) > index 
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
        {step === 'details' && renderPaymentDetails()}
        {step === 'confirmation' && renderConfirmation()}
        {step === 'processing' && renderProcessing()}
        {step === 'success' && renderSuccess()}
        {step === 'error' && renderError()}
      </div>
    </div>
  );
};

export default DepositPaymentFlow;