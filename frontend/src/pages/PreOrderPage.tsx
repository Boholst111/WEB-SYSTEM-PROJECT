import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAppDispatch } from '../store';
import { payDeposit, completePayment, cancelPreOrder } from '../store/slices/preorderSlice';
import PreOrderDashboard from '../components/PreOrderDashboard';
import DepositPaymentFlow from '../components/DepositPaymentFlow';
import PaymentCompletionFlow from '../components/PaymentCompletionFlow';
import { PreOrder } from '../types';

interface PaymentFlowState {
  type: 'deposit' | 'completion' | null;
  preorder: PreOrder | null;
}

const PreOrderPage: React.FC = () => {
  const navigate = useNavigate();
  const dispatch = useAppDispatch();
  const [paymentFlow, setPaymentFlow] = useState<PaymentFlowState>({ type: null, preorder: null });
  const [showCancelConfirm, setShowCancelConfirm] = useState<number | null>(null);

  const handlePayDeposit = async (preorderId: number) => {
    // Find the preorder to pass to the payment flow
    // In a real app, you might want to fetch the specific preorder
    // For now, we'll trigger the payment flow and let the component handle it
    setPaymentFlow({ type: 'deposit', preorder: { id: preorderId } as PreOrder });
  };

  const handleCompletePayment = async (preorderId: number) => {
    setPaymentFlow({ type: 'completion', preorder: { id: preorderId } as PreOrder });
  };

  const handleCancelPreOrder = async (preorderId: number) => {
    setShowCancelConfirm(preorderId);
  };

  const confirmCancelPreOrder = async (preorderId: number, reason?: string) => {
    try {
      await dispatch(cancelPreOrder({ id: preorderId, reason })).unwrap();
      setShowCancelConfirm(null);
      // Show success message or notification
    } catch (error) {
      console.error('Failed to cancel pre-order:', error);
      // Show error message
    }
  };

  const handlePaymentSuccess = () => {
    setPaymentFlow({ type: null, preorder: null });
    // Optionally show success notification
    // You might want to refresh the dashboard data here
  };

  const handlePaymentCancel = () => {
    setPaymentFlow({ type: null, preorder: null });
  };

  // If payment flow is active, show the payment component
  if (paymentFlow.type && paymentFlow.preorder) {
    if (paymentFlow.type === 'deposit') {
      return (
        <div className="min-h-screen bg-gray-50 py-8">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <DepositPaymentFlow
              preorder={paymentFlow.preorder}
              onSuccess={handlePaymentSuccess}
              onCancel={handlePaymentCancel}
            />
          </div>
        </div>
      );
    }

    if (paymentFlow.type === 'completion') {
      return (
        <div className="min-h-screen bg-gray-50 py-8">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <PaymentCompletionFlow
              preorder={paymentFlow.preorder}
              onSuccess={handlePaymentSuccess}
              onCancel={handlePaymentCancel}
            />
          </div>
        </div>
      );
    }
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <PreOrderDashboard
          onPayDeposit={handlePayDeposit}
          onCompletePayment={handleCompletePayment}
          onCancel={handleCancelPreOrder}
        />

        {/* Cancel Confirmation Modal */}
        {showCancelConfirm && (
          <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div className="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
              <div className="mt-3 text-center">
                <div className="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                  <svg
                    className="h-6 w-6 text-red-600"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth="2"
                      d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"
                    />
                  </svg>
                </div>
                <h3 className="text-lg leading-6 font-medium text-gray-900 mt-4">
                  Cancel Pre-Order
                </h3>
                <div className="mt-2 px-7 py-3">
                  <p className="text-sm text-gray-500">
                    Are you sure you want to cancel this pre-order? This action cannot be undone.
                  </p>
                  <p className="text-xs text-gray-400 mt-2">
                    Note: Deposits may be non-refundable depending on the pre-order status.
                  </p>
                </div>
                <div className="flex gap-3 px-4 py-3">
                  <button
                    onClick={() => setShowCancelConfirm(null)}
                    className="flex-1 px-4 py-2 bg-gray-300 text-gray-800 text-base font-medium rounded-md shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-300"
                  >
                    Keep Pre-Order
                  </button>
                  <button
                    onClick={() => confirmCancelPreOrder(showCancelConfirm)}
                    className="flex-1 px-4 py-2 bg-red-600 text-white text-base font-medium rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                  >
                    Cancel Pre-Order
                  </button>
                </div>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default PreOrderPage;