import React, { useEffect, useState } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { XCircleIcon } from '@heroicons/react/24/outline';
import Layout from '../components/Layout';
import PaymentRecovery from '../components/PaymentRecovery';
import { paymentApi, PaymentStatusResponse } from '../services/paymentApi';

const PaymentFailedPage: React.FC = () => {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  
  const [paymentData, setPaymentData] = useState<PaymentStatusResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Extract parameters from URL
  const paymentId = searchParams.get('paymentId');
  const type = searchParams.get('type') as 'order' | 'preorder' | null;
  const amount = searchParams.get('amount') ? parseFloat(searchParams.get('amount')!) : 0;
  const orderId = searchParams.get('orderId') ? parseInt(searchParams.get('orderId')!) : undefined;
  const preorderId = searchParams.get('preorderId') ? parseInt(searchParams.get('preorderId')!) : undefined;

  useEffect(() => {
    const fetchPaymentData = async () => {
      if (!paymentId) {
        // If no payment ID, create a mock failed payment data
        setPaymentData({
          id: 'unknown',
          status: 'failed',
          amount: amount || 0,
          currency: 'PHP',
          paymentMethod: 'unknown',
          message: 'Payment processing failed',
          createdAt: new Date().toISOString(),
          updatedAt: new Date().toISOString(),
        });
        setLoading(false);
        return;
      }

      try {
        const data = await paymentApi.getPaymentStatus(paymentId);
        setPaymentData(data);
      } catch (err: any) {
        console.error('Error fetching payment data:', err);
        // Create a mock failed payment data if we can't fetch the real data
        setPaymentData({
          id: paymentId,
          status: 'failed',
          amount: amount || 0,
          currency: 'PHP',
          paymentMethod: 'unknown',
          message: 'Payment processing failed',
          createdAt: new Date().toISOString(),
          updatedAt: new Date().toISOString(),
        });
      } finally {
        setLoading(false);
      }
    };

    fetchPaymentData();
  }, [paymentId, amount]);

  const handleRetryPayment = () => {
    // Navigate back to payment page with the same parameters
    const params = new URLSearchParams();
    if (orderId) params.set('orderId', orderId.toString());
    if (preorderId) params.set('preorderId', preorderId.toString());
    if (amount) params.set('amount', amount.toString());
    
    navigate(`/payment/${type}?${params.toString()}`);
  };

  const handleChangePaymentMethod = () => {
    // Navigate back to payment page with the same parameters
    handleRetryPayment();
  };

  const handleContactSupport = () => {
    // Navigate to support page or open support modal
    navigate('/support');
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
      <div className="max-w-4xl mx-auto px-4 py-8">
        <div className="bg-white rounded-lg shadow-md p-8">
          <div className="animate-pulse text-center">
            <div className="w-16 h-16 bg-gray-200 rounded-full mx-auto mb-4"></div>
            <div className="h-8 bg-gray-200 rounded w-64 mx-auto mb-4"></div>
            <div className="h-4 bg-gray-200 rounded w-96 mx-auto mb-8"></div>
            <div className="space-y-4">
              <div className="h-4 bg-gray-200 rounded w-full"></div>
              <div className="h-4 bg-gray-200 rounded w-3/4 mx-auto"></div>
              <div className="h-4 bg-gray-200 rounded w-1/2 mx-auto"></div>
            </div>
          </div>
        </div>
      </div>
    );
  }

  if (!paymentData) {
    return (
      <div className="max-w-4xl mx-auto px-4 py-8">
        <div className="bg-white rounded-lg shadow-md p-8 text-center">
          <div className="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mb-4">
            <XCircleIcon className="w-8 h-8 text-red-600" />
          </div>
          <h2 className="text-2xl font-bold text-gray-900 mb-2">Payment Information Unavailable</h2>
          <p className="text-gray-600 mb-6">
            We couldn't load the payment information. Please try again or contact support.
          </p>
          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            <button
              onClick={handleRetryPayment}
              className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
            >
              Try Again
            </button>
            <button
              onClick={() => navigate('/')}
              className="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
            >
              Return to Home
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-4xl mx-auto px-4 py-8">
        {/* Failed Payment Header */}
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-20 h-20 bg-red-100 rounded-full mb-6">
            <XCircleIcon className="w-12 h-12 text-red-600" />
          </div>
          
          <h1 className="text-3xl font-bold text-gray-900 mb-2">
            Payment Failed
          </h1>
          
          <p className="text-xl text-gray-600 mb-4">
            We couldn't process your payment of {formatCurrency(paymentData.amount)}
          </p>
          
          <p className="text-gray-500">
            Don't worry - no charges were made to your account.
          </p>
        </div>

        {/* Payment Recovery Component */}
        <PaymentRecovery
          paymentData={paymentData}
          onRetryPayment={handleRetryPayment}
          onContactSupport={handleContactSupport}
          onChangePaymentMethod={handleChangePaymentMethod}
          className="mb-8"
        />

        {/* Alternative Actions */}
        <div className="bg-gray-50 rounded-lg p-6">
          <h3 className="text-lg font-semibold text-gray-900 mb-4 text-center">
            Other Options
          </h3>
          
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <button
              onClick={() => navigate(type === 'order' ? '/orders' : '/preorders')}
              className="p-4 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors text-center"
            >
              <div className="text-gray-600 mb-2">
                <svg className="w-8 h-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
              </div>
              <h4 className="font-medium text-gray-900 mb-1">
                View My {type === 'order' ? 'Orders' : 'Pre-orders'}
              </h4>
              <p className="text-sm text-gray-600">
                Check your {type === 'order' ? 'order' : 'pre-order'} status
              </p>
            </button>

            <button
              onClick={() => navigate('/')}
              className="p-4 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors text-center"
            >
              <div className="text-gray-600 mb-2">
                <svg className="w-8 h-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z" />
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 5a2 2 0 012-2h4a2 2 0 012 2v2H8V5z" />
                </svg>
              </div>
              <h4 className="font-medium text-gray-900 mb-1">Continue Shopping</h4>
              <p className="text-sm text-gray-600">
                Browse our collection
              </p>
            </button>

            <button
              onClick={() => navigate('/support')}
              className="p-4 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors text-center"
            >
              <div className="text-gray-600 mb-2">
                <svg className="w-8 h-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <h4 className="font-medium text-gray-900 mb-1">Get Help</h4>
              <p className="text-sm text-gray-600">
                Contact customer support
              </p>
            </button>
          </div>
        </div>

        {/* Security Reassurance */}
        <div className="mt-8 text-center">
          <div className="inline-flex items-center text-sm text-gray-500">
            <svg className="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            Your payment information is always secure and encrypted
          </div>
        </div>
      </div>
    );
  };

  export default PaymentFailedPage;