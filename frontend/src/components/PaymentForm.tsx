import React, { useState } from 'react';
import { 
  DevicePhoneMobileIcon, 
  CreditCardIcon, 
  BanknotesIcon,
  ExclamationCircleIcon,
  InformationCircleIcon
} from '@heroicons/react/24/outline';
import { PaymentFormData } from '../services/paymentApi';

interface PaymentFormProps {
  paymentMethod: 'gcash' | 'maya' | 'bank_transfer';
  amount: number;
  onSubmit: (data: PaymentFormData) => void;
  onCancel: () => void;
  loading?: boolean;
  error?: string | null;
}

const PaymentForm: React.FC<PaymentFormProps> = ({
  paymentMethod,
  amount,
  onSubmit,
  onCancel,
  loading = false,
  error = null
}) => {
  const [formData, setFormData] = useState<Partial<PaymentFormData>>({
    paymentMethod,
    amount,
    phone: '',
    bank: 'bpi'
  });
  const [validationErrors, setValidationErrors] = useState<Record<string, string>>({});

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2,
    }).format(amount);
  };

  const validateForm = (): boolean => {
    const errors: Record<string, string> = {};

    if (paymentMethod === 'gcash' || paymentMethod === 'maya') {
      if (!formData.phone) {
        errors.phone = 'Phone number is required';
      } else if (!/^(09|\+639)\d{9}$/.test(formData.phone.replace(/\s/g, ''))) {
        errors.phone = 'Please enter a valid Philippine mobile number';
      }
    }

    if (paymentMethod === 'bank_transfer' && !formData.bank) {
      errors.bank = 'Please select a bank';
    }

    setValidationErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!validateForm()) {
      return;
    }

    onSubmit(formData as PaymentFormData);
  };

  const handleInputChange = (field: string, value: string) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    
    // Clear validation error when user starts typing
    if (validationErrors[field]) {
      setValidationErrors(prev => ({ ...prev, [field]: '' }));
    }
  };

  const getMethodInfo = () => {
    switch (paymentMethod) {
      case 'gcash':
        return {
          icon: DevicePhoneMobileIcon,
          name: 'GCash',
          color: 'blue',
          description: 'You will be redirected to GCash to complete your payment'
        };
      case 'maya':
        return {
          icon: CreditCardIcon,
          name: 'Maya',
          color: 'green',
          description: 'You will be redirected to Maya to complete your payment'
        };
      case 'bank_transfer':
        return {
          icon: BanknotesIcon,
          name: 'Bank Transfer',
          color: 'gray',
          description: 'You will receive bank details to complete the transfer'
        };
      default:
        return {
          icon: CreditCardIcon,
          name: 'Payment',
          color: 'blue',
          description: 'Complete your payment'
        };
    }
  };

  const methodInfo = getMethodInfo();
  const Icon = methodInfo.icon;

  return (
    <div className="bg-white rounded-lg shadow-md p-6">
      {/* Header */}
      <div className="text-center mb-6">
        <div className={`inline-flex items-center justify-center w-16 h-16 bg-${methodInfo.color}-100 rounded-full mb-4`}>
          <Icon className={`w-8 h-8 text-${methodInfo.color}-600`} />
        </div>
        <h2 className="text-2xl font-bold text-gray-900 mb-2">
          {methodInfo.name} Payment
        </h2>
        <p className="text-gray-600 mb-2">
          {methodInfo.description}
        </p>
        <div className="text-3xl font-bold text-gray-900">
          {formatCurrency(amount)}
        </div>
      </div>

      {/* Error Message */}
      {error && (
        <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start">
          <ExclamationCircleIcon className="w-5 h-5 text-red-600 mr-3 mt-0.5 flex-shrink-0" />
          <div>
            <h3 className="text-red-800 font-medium">Payment Error</h3>
            <p className="text-red-700 text-sm mt-1">{error}</p>
          </div>
        </div>
      )}

      {/* Form */}
      <form onSubmit={handleSubmit} className="space-y-6">
        {/* GCash/Maya Phone Input */}
        {(paymentMethod === 'gcash' || paymentMethod === 'maya') && (
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Mobile Number
            </label>
            <div className="relative">
              <input
                type="tel"
                value={formData.phone || ''}
                onChange={(e) => handleInputChange('phone', e.target.value)}
                placeholder="09XX XXX XXXX"
                className={`
                  w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-${methodInfo.color}-500 focus:border-${methodInfo.color}-500
                  ${validationErrors.phone ? 'border-red-300' : 'border-gray-300'}
                `}
                disabled={loading}
              />
              {validationErrors.phone && (
                <div className="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                  <ExclamationCircleIcon className="h-5 w-5 text-red-500" />
                </div>
              )}
            </div>
            {validationErrors.phone && (
              <p className="mt-1 text-sm text-red-600">{validationErrors.phone}</p>
            )}
            <p className="mt-2 text-sm text-gray-500">
              Enter the mobile number linked to your {methodInfo.name} account
            </p>
          </div>
        )}

        {/* Bank Transfer Bank Selection */}
        {paymentMethod === 'bank_transfer' && (
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Select Bank
            </label>
            <select
              value={formData.bank || 'bpi'}
              onChange={(e) => handleInputChange('bank', e.target.value)}
              className={`
                w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-${methodInfo.color}-500 focus:border-${methodInfo.color}-500
                ${validationErrors.bank ? 'border-red-300' : 'border-gray-300'}
              `}
              disabled={loading}
            >
              <option value="bpi">Bank of the Philippine Islands (BPI)</option>
              <option value="bdo">Banco de Oro (BDO)</option>
              <option value="metrobank">Metrobank</option>
            </select>
            {validationErrors.bank && (
              <p className="mt-1 text-sm text-red-600">{validationErrors.bank}</p>
            )}
            <div className="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
              <div className="flex items-start">
                <InformationCircleIcon className="w-5 h-5 text-blue-600 mr-2 mt-0.5 flex-shrink-0" />
                <div className="text-sm text-blue-800">
                  <p className="font-medium mb-1">Bank Transfer Instructions:</p>
                  <ul className="list-disc list-inside space-y-1 text-blue-700">
                    <li>You will receive bank account details after confirmation</li>
                    <li>Transfer must be completed within 24 hours</li>
                    <li>Upload proof of payment for verification</li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Payment Summary */}
        <div className="bg-gray-50 rounded-lg p-4">
          <h3 className="font-medium text-gray-900 mb-3">Payment Summary</h3>
          <div className="space-y-2">
            <div className="flex justify-between text-sm">
              <span className="text-gray-600">Amount</span>
              <span className="text-gray-900">{formatCurrency(amount)}</span>
            </div>
            <div className="flex justify-between text-sm">
              <span className="text-gray-600">Payment Method</span>
              <span className="text-gray-900">{methodInfo.name}</span>
            </div>
            <div className="border-t border-gray-200 pt-2">
              <div className="flex justify-between font-medium">
                <span className="text-gray-900">Total</span>
                <span className="text-gray-900">{formatCurrency(amount)}</span>
              </div>
            </div>
          </div>
        </div>

        {/* Action Buttons */}
        <div className="flex space-x-4">
          <button
            type="button"
            onClick={onCancel}
            disabled={loading}
            className="flex-1 px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={loading}
            className={`
              flex-1 px-6 py-3 bg-${methodInfo.color}-600 text-white rounded-lg hover:bg-${methodInfo.color}-700 
              disabled:opacity-50 disabled:cursor-not-allowed transition-colors
              flex items-center justify-center
            `}
          >
            {loading ? (
              <>
                <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Processing...
              </>
            ) : (
              `Pay ${formatCurrency(amount)}`
            )}
          </button>
        </div>
      </form>
    </div>
  );
};

export default PaymentForm;