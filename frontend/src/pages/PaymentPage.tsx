import React from 'react';
import { useParams, useSearchParams, useNavigate } from 'react-router-dom';
import PaymentFlow from '../components/PaymentFlow';
import Layout from '../components/Layout';

const PaymentPage: React.FC = () => {
  const { type } = useParams<{ type: 'order' | 'preorder' }>();
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();

  // Extract payment parameters from URL
  const orderId = searchParams.get('orderId') ? parseInt(searchParams.get('orderId')!) : undefined;
  const preorderId = searchParams.get('preorderId') ? parseInt(searchParams.get('preorderId')!) : undefined;
  const amount = searchParams.get('amount') ? parseFloat(searchParams.get('amount')!) : 0;

  // Validate required parameters
  if (!amount || amount <= 0) {
    return (
      <div className="max-w-2xl mx-auto px-4 py-8">
        <div className="bg-white rounded-lg shadow-md p-6 text-center">
          <div className="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mb-4">
            <svg className="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
          </div>
          <h2 className="text-2xl font-bold text-gray-900 mb-2">Invalid Payment Request</h2>
          <p className="text-gray-600 mb-6">
            The payment information is missing or invalid. Please try again.
          </p>
          <button
            onClick={() => navigate(-1)}
            className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
          >
            Go Back
          </button>
        </div>
      </div>
    );
  }

  if (type === 'order' && !orderId) {
    return (
      <div className="max-w-2xl mx-auto px-4 py-8">
        <div className="bg-white rounded-lg shadow-md p-6 text-center">
          <h2 className="text-2xl font-bold text-gray-900 mb-2">Order Not Found</h2>
          <p className="text-gray-600 mb-6">
            The order ID is missing or invalid.
          </p>
          <button
            onClick={() => navigate('/orders')}
            className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
          >
            View My Orders
          </button>
        </div>
      </div>
    );
  }

  if (type === 'preorder' && !preorderId) {
    return (
      <div className="max-w-2xl mx-auto px-4 py-8">
        <div className="bg-white rounded-lg shadow-md p-6 text-center">
          <h2 className="text-2xl font-bold text-gray-900 mb-2">Pre-order Not Found</h2>
          <p className="text-gray-600 mb-6">
            The pre-order ID is missing or invalid.
          </p>
          <button
            onClick={() => navigate('/preorders')}
            className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
          >
            View My Pre-orders
          </button>
        </div>
      </div>
    );
  }

  const handlePaymentSuccess = (paymentId: string) => {
    // Navigate to success page with payment details
    navigate(`/payment/success?paymentId=${paymentId}&type=${type}&amount=${amount}`);
  };

  const handlePaymentCancel = () => {
    // Navigate back to appropriate page
    if (type === 'order') {
      navigate('/orders');
    } else if (type === 'preorder') {
      navigate('/preorders');
    } else {
      navigate('/');
    }
  };

  const getPageTitle = () => {
    if (type === 'order') {
      return 'Complete Order Payment';
    } else if (type === 'preorder') {
      return 'Complete Pre-order Payment';
    }
    return 'Complete Payment';
  };

  const getPageDescription = () => {
    if (type === 'order') {
      return 'Complete your order payment to proceed with shipping.';
    } else if (type === 'preorder') {
      return 'Complete your pre-order payment to secure your item.';
    }
    return 'Complete your payment to continue.';
  };

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Page Header */}
        <div className="text-center mb-8">
          <h1 className="text-3xl font-bold text-gray-900 mb-2">
            {getPageTitle()}
          </h1>
          <p className="text-gray-600">
            {getPageDescription()}
          </p>
        </div>

        {/* Payment Flow */}
        <PaymentFlow
          amount={amount}
          orderId={orderId}
          preorderId={preorderId}
          onSuccess={handlePaymentSuccess}
          onCancel={handlePaymentCancel}
        />

        {/* Security Notice */}
        <div className="mt-8 text-center">
          <div className="inline-flex items-center text-sm text-gray-500">
            <svg className="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            Your payment is protected by 256-bit SSL encryption
          </div>
        </div>
      </div>
    );
  };

  export default PaymentPage;