import React, { useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import PaymentMethodSelector from './PaymentMethodSelector';
import PaymentForm from './PaymentForm';
import PaymentStatusTracker from './PaymentStatusTracker';
import PaymentRecovery from './PaymentRecovery';
import { paymentApi, PaymentFormData, PaymentStatusResponse } from '../services/paymentApi';

interface PaymentFlowProps {
  amount: number;
  orderId?: number;
  preorderId?: number;
  onSuccess?: (paymentId: string) => void;
  onCancel?: () => void;
  className?: string;
}

type PaymentStep = 'method_selection' | 'payment_form' | 'processing' | 'status_tracking' | 'recovery' | 'success';

const PaymentFlow: React.FC<PaymentFlowProps> = ({
  amount,
  orderId,
  preorderId,
  onSuccess,
  onCancel,
  className = ''
}) => {
  const navigate = useNavigate();
  
  // State management
  const [currentStep, setCurrentStep] = useState<PaymentStep>('method_selection');
  const [selectedPaymentMethod, setSelectedPaymentMethod] = useState<string>('');
  const [paymentId, setPaymentId] = useState<string>('');
  const [paymentStatus, setPaymentStatus] = useState<PaymentStatusResponse | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  // Reset flow state
  const resetFlow = useCallback(() => {
    setCurrentStep('method_selection');
    setSelectedPaymentMethod('');
    setPaymentId('');
    setPaymentStatus(null);
    setError(null);
    setLoading(false);
  }, []);

  // Handle payment method selection
  const handleMethodSelect = useCallback((method: string) => {
    setSelectedPaymentMethod(method);
    setCurrentStep('payment_form');
    setError(null);
  }, []);

  // Handle payment form submission
  const handlePaymentSubmit = useCallback(async (formData: PaymentFormData) => {
    setLoading(true);
    setError(null);
    setCurrentStep('processing');

    try {
      let response;
      
      // Add order/preorder IDs to form data
      const paymentData = {
        ...formData,
        orderId,
        preorderId,
      };

      // Process payment based on method
      switch (formData.paymentMethod) {
        case 'gcash':
          response = await paymentApi.processGCashPayment(paymentData);
          break;
        case 'maya':
          response = await paymentApi.processMayaPayment(paymentData);
          break;
        case 'bank_transfer':
          response = await paymentApi.processBankTransferPayment(paymentData);
          break;
        default:
          throw new Error('Invalid payment method');
      }

      if (response.id) {
        setPaymentId(response.id);
        
        // For redirect-based payments (GCash, Maya), redirect to payment URL
        if (response.paymentUrl && (formData.paymentMethod === 'gcash' || formData.paymentMethod === 'maya')) {
          window.location.href = response.paymentUrl;
          return;
        }
        
        // For bank transfer, go to status tracking
        setCurrentStep('status_tracking');
      } else {
        throw new Error(response.message || 'Payment processing failed');
      }
    } catch (err: any) {
      console.error('Payment processing error:', err);
      setError(err.response?.data?.message || err.message || 'Payment processing failed');
      setCurrentStep('payment_form');
    } finally {
      setLoading(false);
    }
  }, [orderId, preorderId]);

  // Handle payment status changes
  const handleStatusChange = useCallback((status: string) => {
    if (status === 'completed') {
      setCurrentStep('success');
      if (onSuccess && paymentId) {
        onSuccess(paymentId);
      }
    } else if (status === 'failed') {
      setCurrentStep('recovery');
    }
  }, [paymentId, onSuccess]);

  // Handle retry payment
  const handleRetryPayment = useCallback(() => {
    setCurrentStep('payment_form');
    setError(null);
  }, []);

  // Handle change payment method
  const handleChangePaymentMethod = useCallback(() => {
    setCurrentStep('method_selection');
    setSelectedPaymentMethod('');
    setError(null);
  }, []);

  // Handle contact support
  const handleContactSupport = useCallback(() => {
    // Navigate to support page or open support modal
    navigate('/support');
  }, [navigate]);

  // Handle cancel
  const handleCancel = useCallback(() => {
    if (onCancel) {
      onCancel();
    } else {
      navigate(-1);
    }
  }, [onCancel, navigate]);

  // Handle back navigation
  const handleBack = useCallback(() => {
    switch (currentStep) {
      case 'payment_form':
        setCurrentStep('method_selection');
        break;
      case 'status_tracking':
      case 'recovery':
        setCurrentStep('payment_form');
        break;
      default:
        handleCancel();
    }
    setError(null);
  }, [currentStep, handleCancel]);

  // Render current step
  const renderCurrentStep = () => {
    switch (currentStep) {
      case 'method_selection':
        return (
          <PaymentMethodSelector
            selectedMethod={selectedPaymentMethod}
            onMethodSelect={handleMethodSelect}
            amount={amount}
            disabled={loading}
          />
        );

      case 'payment_form':
      case 'processing':
        return (
          <PaymentForm
            paymentMethod={selectedPaymentMethod as 'gcash' | 'maya' | 'bank_transfer'}
            amount={amount}
            onSubmit={handlePaymentSubmit}
            onCancel={handleBack}
            loading={loading || currentStep === 'processing'}
            error={error}
          />
        );

      case 'status_tracking':
        return paymentId ? (
          <PaymentStatusTracker
            paymentId={paymentId}
            onStatusChange={handleStatusChange}
            autoRefresh={true}
            refreshInterval={5000}
          />
        ) : null;

      case 'recovery':
        return paymentStatus ? (
          <PaymentRecovery
            paymentData={paymentStatus}
            onRetryPayment={handleRetryPayment}
            onContactSupport={handleContactSupport}
            onChangePaymentMethod={handleChangePaymentMethod}
          />
        ) : null;

      case 'success':
        return (
          <div className="bg-white rounded-lg shadow-md p-6 text-center">
            <div className="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
              <svg className="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <h2 className="text-2xl font-bold text-gray-900 mb-2">Payment Successful!</h2>
            <p className="text-gray-600 mb-6">
              Your payment of {new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP',
                minimumFractionDigits: 2,
              }).format(amount)} has been processed successfully.
            </p>
            <div className="space-y-3">
              <button
                onClick={() => navigate('/orders')}
                className="w-full px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
              >
                View My Orders
              </button>
              <button
                onClick={() => navigate('/')}
                className="w-full px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
              >
                Continue Shopping
              </button>
            </div>
          </div>
        );

      default:
        return null;
    }
  };

  return (
    <div className={`max-w-2xl mx-auto ${className}`}>
      {/* Progress Indicator */}
      {currentStep !== 'success' && (
        <div className="mb-6">
          <div className="flex items-center justify-between text-sm text-gray-600 mb-2">
            <span className={currentStep === 'method_selection' ? 'text-blue-600 font-medium' : ''}>
              1. Payment Method
            </span>
            <span className={['payment_form', 'processing'].includes(currentStep) ? 'text-blue-600 font-medium' : ''}>
              2. Payment Details
            </span>
            <span className={['status_tracking', 'recovery'].includes(currentStep) ? 'text-blue-600 font-medium' : ''}>
              3. Confirmation
            </span>
          </div>
          <div className="w-full bg-gray-200 rounded-full h-2">
            <div 
              className="bg-blue-600 h-2 rounded-full transition-all duration-300"
              style={{
                width: currentStep === 'method_selection' ? '33%' : 
                       ['payment_form', 'processing'].includes(currentStep) ? '66%' : '100%'
              }}
            />
          </div>
        </div>
      )}

      {/* Current Step Content */}
      {renderCurrentStep()}

      {/* Navigation Buttons */}
      {currentStep !== 'success' && currentStep !== 'processing' && (
        <div className="mt-6 flex justify-between">
          <button
            onClick={handleBack}
            className="px-6 py-2 text-gray-600 hover:text-gray-800 transition-colors"
            disabled={loading}
          >
            ← Back
          </button>
          
          {currentStep === 'method_selection' && (
            <button
              onClick={handleCancel}
              className="px-6 py-2 text-gray-600 hover:text-gray-800 transition-colors"
              disabled={loading}
            >
              Cancel
            </button>
          )}
        </div>
      )}
    </div>
  );
};

export default PaymentFlow;