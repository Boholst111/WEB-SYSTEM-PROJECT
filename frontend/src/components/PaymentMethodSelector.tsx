import React, { useState, useEffect } from 'react';
import { 
  CreditCardIcon, 
  DevicePhoneMobileIcon, 
  BanknotesIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon
} from '@heroicons/react/24/outline';
import { PaymentMethod } from '../types';
import { paymentApi } from '../services/paymentApi';

interface PaymentMethodSelectorProps {
  selectedMethod?: string;
  onMethodSelect: (method: string) => void;
  amount: number;
  disabled?: boolean;
  className?: string;
}

const PaymentMethodSelector: React.FC<PaymentMethodSelectorProps> = ({
  selectedMethod,
  onMethodSelect,
  amount,
  disabled = false,
  className = ''
}) => {
  const [paymentMethods, setPaymentMethods] = useState<PaymentMethod[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchPaymentMethods = async () => {
      try {
        setLoading(true);
        const response = await paymentApi.getPaymentMethods();
        if (response.success) {
          setPaymentMethods(response.payment_methods.filter(method => method.isActive));
        } else {
          setError('Failed to load payment methods');
        }
      } catch (err) {
        setError('Failed to load payment methods');
        console.error('Error fetching payment methods:', err);
      } finally {
        setLoading(false);
      }
    };

    fetchPaymentMethods();
  }, []);

  const getMethodIcon = (type: string) => {
    switch (type) {
      case 'gcash':
        return DevicePhoneMobileIcon;
      case 'maya':
        return CreditCardIcon;
      case 'bank_transfer':
        return BanknotesIcon;
      default:
        return CreditCardIcon;
    }
  };

  const getMethodColor = (type: string) => {
    switch (type) {
      case 'gcash':
        return 'blue';
      case 'maya':
        return 'green';
      case 'bank_transfer':
        return 'gray';
      default:
        return 'blue';
    }
  };

  const getMethodDescription = (type: string) => {
    switch (type) {
      case 'gcash':
        return 'Pay instantly using your GCash wallet';
      case 'maya':
        return 'Secure payment with Maya (PayMaya)';
      case 'bank_transfer':
        return 'Direct bank transfer payment';
      default:
        return 'Secure payment method';
    }
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2,
    }).format(amount);
  };

  if (loading) {
    return (
      <div className={`bg-white rounded-lg shadow-md p-6 ${className}`}>
        <h2 className="text-lg font-semibold text-gray-900 mb-4">Payment Method</h2>
        <div className="space-y-3">
          {[1, 2, 3].map((i) => (
            <div key={i} className="animate-pulse">
              <div className="flex items-center p-4 border border-gray-200 rounded-lg">
                <div className="w-8 h-8 bg-gray-200 rounded"></div>
                <div className="ml-4 flex-1">
                  <div className="h-4 bg-gray-200 rounded w-1/3 mb-2"></div>
                  <div className="h-3 bg-gray-200 rounded w-2/3"></div>
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className={`bg-white rounded-lg shadow-md p-6 ${className}`}>
        <h2 className="text-lg font-semibold text-gray-900 mb-4">Payment Method</h2>
        <div className="flex items-center p-4 bg-red-50 border border-red-200 rounded-lg">
          <ExclamationTriangleIcon className="w-6 h-6 text-red-600 mr-3" />
          <div>
            <p className="text-red-800 font-medium">Unable to load payment methods</p>
            <p className="text-red-600 text-sm">{error}</p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className={`bg-white rounded-lg shadow-md p-6 ${className}`}>
      <h2 className="text-lg font-semibold text-gray-900 mb-4">
        Payment Method
        <span className="text-sm font-normal text-gray-600 ml-2">
          ({formatCurrency(amount)})
        </span>
      </h2>
      
      <div className="space-y-3">
        {paymentMethods.map((method) => {
          const Icon = getMethodIcon(method.type);
          const color = getMethodColor(method.type);
          const isSelected = selectedMethod === method.id;
          
          return (
            <label
              key={method.id}
              className={`
                flex items-center p-4 border rounded-lg cursor-pointer transition-all duration-200
                ${disabled ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50'}
                ${isSelected 
                  ? `border-${color}-500 bg-${color}-50 ring-2 ring-${color}-200` 
                  : 'border-gray-200'
                }
              `}
            >
              <input
                type="radio"
                name="paymentMethod"
                value={method.id}
                checked={isSelected}
                onChange={() => !disabled && onMethodSelect(method.id)}
                disabled={disabled}
                className="sr-only"
              />
              
              <div className={`
                flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center
                ${isSelected ? `bg-${color}-100` : 'bg-gray-100'}
              `}>
                <Icon className={`
                  w-6 h-6 
                  ${isSelected ? `text-${color}-600` : 'text-gray-600'}
                `} />
              </div>
              
              <div className="ml-4 flex-1">
                <div className="flex items-center justify-between">
                  <h3 className={`
                    font-medium 
                    ${isSelected ? `text-${color}-900` : 'text-gray-900'}
                  `}>
                    {method.name}
                  </h3>
                  {isSelected && (
                    <CheckCircleIcon className={`w-5 h-5 text-${color}-600`} />
                  )}
                </div>
                <p className={`
                  text-sm mt-1
                  ${isSelected ? `text-${color}-700` : 'text-gray-600'}
                `}>
                  {getMethodDescription(method.type)}
                </p>
              </div>
            </label>
          );
        })}
      </div>

      {paymentMethods.length === 0 && (
        <div className="text-center py-8">
          <ExclamationTriangleIcon className="w-12 h-12 text-gray-400 mx-auto mb-4" />
          <p className="text-gray-600">No payment methods available</p>
        </div>
      )}

      {/* Security Notice */}
      <div className="mt-6 flex items-center justify-center text-xs text-gray-500">
        <svg className="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
        All payments are secured with SSL encryption
      </div>
    </div>
  );
};

export default PaymentMethodSelector;