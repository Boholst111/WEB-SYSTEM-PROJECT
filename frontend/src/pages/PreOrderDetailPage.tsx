import React, { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useAppDispatch, useAppSelector } from '../store';
import { fetchPreOrder, clearCurrentPreOrder } from '../store/slices/preorderSlice';
import PreOrderDetail from '../components/PreOrderDetail';
import PreOrderTracker from '../components/PreOrderTracker';
import PreOrderNotifications from '../components/PreOrderNotifications';
import DepositPaymentFlow from '../components/DepositPaymentFlow';
import PaymentCompletionFlow from '../components/PaymentCompletionFlow';
import { 
  ExclamationTriangleIcon,
  ArrowLeftIcon 
} from '@heroicons/react/24/outline';

interface PaymentFlowState {
  type: 'deposit' | 'completion' | null;
}

const PreOrderDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const dispatch = useAppDispatch();
  const { currentPreOrder, isLoading, error } = useAppSelector(state => state.preorders);
  
  const [paymentFlow, setPaymentFlow] = useState<PaymentFlowState>({ type: null });
  const [showCancelConfirm, setShowCancelConfirm] = useState(false);

  useEffect(() => {
    if (id) {
      dispatch(fetchPreOrder(parseInt(id)));
    }

    return () => {
      dispatch(clearCurrentPreOrder());
    };
  }, [dispatch, id]);

  const handlePayDeposit = (preorderId: number) => {
    setPaymentFlow({ type: 'deposit' });
  };

  const handleCompletePayment = (preorderId: number) => {
    setPaymentFlow({ type: 'completion' });
  };

  const handleCancelPreOrder = (preorderId: number) => {
    setShowCancelConfirm(true);
  };

  const confirmCancelPreOrder = async () => {
    if (!currentPreOrder) return;
    
    try {
      // Dispatch cancel action
      // await dispatch(cancelPreOrder({ id: currentPreOrder.id })).unwrap();
      setShowCancelConfirm(false);
      navigate('/preorders');
    } catch (error) {
      console.error('Failed to cancel pre-order:', error);
    }
  };

  const handlePaymentSuccess = () => {
    setPaymentFlow({ type: null });
    // Refresh the pre-order data
    if (id) {
      dispatch(fetchPreOrder(parseInt(id)));
    }
  };

  const handlePaymentCancel = () => {
    setPaymentFlow({ type: null });
  };

  // Show payment flow if active
  if (paymentFlow.type && currentPreOrder) {
    if (paymentFlow.type === 'deposit') {
      return (
        <div className="min-h-screen bg-gray-50 py-8">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <DepositPaymentFlow
              preorder={currentPreOrder}
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
              preorder={currentPreOrder}
              onSuccess={handlePaymentSuccess}
              onCancel={handlePaymentCancel}
            />
          </div>
        </div>
      );
    }
  }

  // Loading state
  if (isLoading && !currentPreOrder) {
    return (
      <div className="min-h-screen bg-gray-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="animate-pulse">
            <div className="h-8 bg-gray-300 rounded w-1/4 mb-6"></div>
            <div className="bg-white rounded-lg shadow-md border border-gray-200 p-6 mb-6">
              <div className="h-6 bg-gray-300 rounded w-1/3 mb-4"></div>
              <div className="flex">
                <div className="w-48 h-48 bg-gray-300 rounded-lg mr-6"></div>
                <div className="flex-1 space-y-4">
                  <div className="h-4 bg-gray-300 rounded w-3/4"></div>
                  <div className="h-4 bg-gray-300 rounded w-1/2"></div>
                  <div className="h-4 bg-gray-300 rounded w-2/3"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  }

  // Error state
  if (error) {
    return (
      <div className="min-h-screen bg-gray-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="bg-white rounded-lg shadow-md border border-gray-200 p-8 text-center">
            <ExclamationTriangleIcon className="mx-auto h-12 w-12 text-red-400 mb-4" />
            <h2 className="text-xl font-semibold text-gray-900 mb-2">Error Loading Pre-Order</h2>
            <p className="text-gray-600 mb-6">{error}</p>
            <div className="flex gap-3 justify-center">
              <button
                onClick={() => navigate('/preorders')}
                className="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition-colors"
              >
                <ArrowLeftIcon className="w-4 h-4 inline mr-2" />
                Back to Pre-Orders
              </button>
              <button
                onClick={() => id && dispatch(fetchPreOrder(parseInt(id)))}
                className="px-4 py-2 bg-primary-600 text-white rounded-lg font-medium hover:bg-primary-700 transition-colors"
              >
                Try Again
              </button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  // Pre-order not found
  if (!currentPreOrder) {
    return (
      <div className="min-h-screen bg-gray-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="bg-white rounded-lg shadow-md border border-gray-200 p-8 text-center">
            <h2 className="text-xl font-semibold text-gray-900 mb-2">Pre-Order Not Found</h2>
            <p className="text-gray-600 mb-6">
              The pre-order you're looking for doesn't exist or you don't have permission to view it.
            </p>
            <button
              onClick={() => navigate('/preorders')}
              className="px-4 py-2 bg-primary-600 text-white rounded-lg font-medium hover:bg-primary-700 transition-colors"
            >
              <ArrowLeftIcon className="w-4 h-4 inline mr-2" />
              Back to Pre-Orders
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
          {/* Main Content */}
          <div className="lg:col-span-3">
            <PreOrderDetail
              preorder={currentPreOrder}
              onPayDeposit={handlePayDeposit}
              onCompletePayment={handleCompletePayment}
              onCancel={handleCancelPreOrder}
            />
          </div>

          {/* Sidebar */}
          <div className="space-y-6">
            {/* Progress Tracker */}
            <PreOrderTracker 
              preorder={currentPreOrder}
              showDetails={true}
            />

            {/* Notifications */}
            <PreOrderNotifications
              preorderId={currentPreOrder.id}
              maxItems={5}
              onNotificationClick={(notification) => {
                // Handle notification click - maybe scroll to relevant section
                console.log('Notification clicked:', notification);
              }}
            />
          </div>
        </div>

        {/* Cancel Confirmation Modal */}
        {showCancelConfirm && (
          <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div className="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
              <div className="mt-3 text-center">
                <div className="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                  <ExclamationTriangleIcon className="h-6 w-6 text-red-600" />
                </div>
                <h3 className="text-lg leading-6 font-medium text-gray-900 mt-4">
                  Cancel Pre-Order
                </h3>
                <div className="mt-2 px-7 py-3">
                  <p className="text-sm text-gray-500">
                    Are you sure you want to cancel this pre-order for{' '}
                    <strong>{currentPreOrder.product.name}</strong>?
                  </p>
                  <p className="text-xs text-gray-400 mt-2">
                    This action cannot be undone. Deposits may be non-refundable depending on the pre-order status.
                  </p>
                </div>
                <div className="flex gap-3 px-4 py-3">
                  <button
                    onClick={() => setShowCancelConfirm(false)}
                    className="flex-1 px-4 py-2 bg-gray-300 text-gray-800 text-base font-medium rounded-md shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-300"
                  >
                    Keep Pre-Order
                  </button>
                  <button
                    onClick={confirmCancelPreOrder}
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

export default PreOrderDetailPage;