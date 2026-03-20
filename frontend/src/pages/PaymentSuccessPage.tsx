import React, { useEffect, useState } from 'react';
import { useSearchParams, useNavigate, Link } from 'react-router-dom';
import { CheckCircleIcon, ReceiptPercentIcon, TruckIcon, EnvelopeIcon } from '@heroicons/react/24/outline';
import Layout from '../components/Layout';
import { paymentApi, PaymentStatusResponse } from '../services/paymentApi';

const PaymentSuccessPage: React.FC = () => {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  
  const [paymentData, setPaymentData] = useState<PaymentStatusResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Extract parameters from URL
  const paymentId = searchParams.get('paymentId');
  const type = searchParams.get('type') as 'order' | 'preorder' | null;
  const amount = searchParams.get('amount') ? parseFloat(searchParams.get('amount')!) : 0;

  useEffect(() => {
    const fetchPaymentData = async () => {
      if (!paymentId) {
        setError('Payment ID is missing');
        setLoading(false);
        return;
      }

      try {
        const data = await paymentApi.getPaymentStatus(paymentId);
        setPaymentData(data);
      } catch (err: any) {
        console.error('Error fetching payment data:', err);
        setError('Failed to load payment information');
      } finally {
        setLoading(false);
      }
    };

    fetchPaymentData();
  }, [paymentId]);

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
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(new Date(dateString));
  };

  const getNextSteps = () => {
    if (type === 'order') {
      return [
        {
          icon: ReceiptPercentIcon,
          title: 'Order Confirmed',
          description: 'Your order has been confirmed and is being prepared for shipping.',
          completed: true
        },
        {
          icon: TruckIcon,
          title: 'Shipping',
          description: 'You will receive tracking information once your order ships.',
          completed: false
        },
        {
          icon: EnvelopeIcon,
          title: 'Email Updates',
          description: 'We\'ll send you email updates about your order status.',
          completed: false
        }
      ];
    } else if (type === 'preorder') {
      return [
        {
          icon: ReceiptPercentIcon,
          title: 'Pre-order Secured',
          description: 'Your pre-order has been secured with your payment.',
          completed: true
        },
        {
          icon: TruckIcon,
          title: 'Arrival Notification',
          description: 'We\'ll notify you when your item arrives and is ready to ship.',
          completed: false
        },
        {
          icon: EnvelopeIcon,
          title: 'Status Updates',
          description: 'You\'ll receive updates about your pre-order status.',
          completed: false
        }
      ];
    }
    
    return [];
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

  if (error || !paymentData) {
    return (
      <div className="max-w-4xl mx-auto px-4 py-8">
        <div className="bg-white rounded-lg shadow-md p-8 text-center">
          <div className="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mb-4">
            <svg className="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
          </div>
          <h2 className="text-2xl font-bold text-gray-900 mb-2">Unable to Load Payment Information</h2>
          <p className="text-gray-600 mb-6">{error}</p>
          <button
            onClick={() => navigate('/')}
            className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
          >
            Return to Home
          </button>
        </div>
      </div>
    );
  }

  const nextSteps = getNextSteps();

  return (
    <div className="max-w-4xl mx-auto px-4 py-8">
        {/* Success Header */}
        <div className="bg-white rounded-lg shadow-md p-8 mb-8">
          <div className="text-center">
            <div className="inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-6">
              <CheckCircleIcon className="w-12 h-12 text-green-600" />
            </div>
            
            <h1 className="text-3xl font-bold text-gray-900 mb-2">
              Payment Successful!
            </h1>
            
            <p className="text-xl text-gray-600 mb-6">
              Thank you for your {type === 'preorder' ? 'pre-order' : 'order'}. Your payment has been processed successfully.
            </p>

            <div className="text-4xl font-bold text-green-600 mb-6">
              {formatCurrency(paymentData.amount)}
            </div>

            {/* Payment Details */}
            <div className="bg-gray-50 rounded-lg p-6 mb-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-4">Payment Details</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div className="text-left">
                  <span className="text-gray-600">Payment ID:</span>
                  <p className="font-mono text-gray-900 break-all">{paymentData.id}</p>
                </div>
                <div className="text-left">
                  <span className="text-gray-600">Payment Method:</span>
                  <p className="text-gray-900 capitalize">{paymentData.paymentMethod.replace('_', ' ')}</p>
                </div>
                <div className="text-left">
                  <span className="text-gray-600">Transaction Date:</span>
                  <p className="text-gray-900">{formatDate(paymentData.createdAt)}</p>
                </div>
                {paymentData.referenceNumber && (
                  <div className="text-left">
                    <span className="text-gray-600">Reference Number:</span>
                    <p className="font-mono text-gray-900 break-all">{paymentData.referenceNumber}</p>
                  </div>
                )}
              </div>
            </div>

            {/* Action Buttons */}
            <div className="flex flex-col sm:flex-row gap-4 justify-center">
              <Link
                to={type === 'order' ? '/orders' : '/preorders'}
                className="px-8 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-center"
              >
                View My {type === 'order' ? 'Orders' : 'Pre-orders'}
              </Link>
              <Link
                to="/"
                className="px-8 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors text-center"
              >
                Continue Shopping
              </Link>
            </div>
          </div>
        </div>

        {/* Next Steps */}
        {nextSteps.length > 0 && (
          <div className="bg-white rounded-lg shadow-md p-8">
            <h2 className="text-2xl font-bold text-gray-900 mb-6 text-center">What Happens Next?</h2>
            
            <div className="space-y-6">
              {nextSteps.map((step, index) => {
                const Icon = step.icon;
                return (
                  <div key={index} className="flex items-start">
                    <div className={`
                      flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center mr-4
                      ${step.completed ? 'bg-green-100' : 'bg-gray-100'}
                    `}>
                      <Icon className={`
                        w-6 h-6 
                        ${step.completed ? 'text-green-600' : 'text-gray-600'}
                      `} />
                    </div>
                    <div className="flex-1">
                      <h3 className={`
                        text-lg font-semibold mb-1
                        ${step.completed ? 'text-green-900' : 'text-gray-900'}
                      `}>
                        {step.title}
                        {step.completed && (
                          <CheckCircleIcon className="inline w-5 h-5 text-green-600 ml-2" />
                        )}
                      </h3>
                      <p className={`
                        ${step.completed ? 'text-green-700' : 'text-gray-600'}
                      `}>
                        {step.description}
                      </p>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        )}

        {/* Support Information */}
        <div className="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
          <div className="text-center">
            <h3 className="text-lg font-semibold text-blue-900 mb-2">Need Help?</h3>
            <p className="text-blue-700 mb-4">
              If you have any questions about your {type === 'order' ? 'order' : 'pre-order'} or payment, our support team is here to help.
            </p>
            <div className="flex flex-col sm:flex-row gap-4 justify-center text-sm">
              <div className="text-blue-800">
                <strong>Email:</strong> support@diecastempire.com
              </div>
              <div className="text-blue-800">
                <strong>Phone:</strong> +63 2 8123 4567
              </div>
              <div className="text-blue-800">
                <strong>Hours:</strong> Mon-Fri 9AM-6PM (PHT)
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  };

  export default PaymentSuccessPage;