import React from 'react';
import { Order } from '../types';

interface OrderTrackingProps {
  order: Order;
}

const OrderTracking: React.FC<OrderTrackingProps> = ({ order }) => {
  const trackingSteps = [
    { status: 'pending', label: 'Order Placed', icon: '📦' },
    { status: 'confirmed', label: 'Order Confirmed', icon: '✓' },
    { status: 'processing', label: 'Processing', icon: '⚙️' },
    { status: 'shipped', label: 'Shipped', icon: '🚚' },
    { status: 'delivered', label: 'Delivered', icon: '🎉' },
  ];

  const statusOrder = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
  const currentStepIndex = statusOrder.indexOf(order.status);

  const isStepCompleted = (stepIndex: number) => {
    return stepIndex <= currentStepIndex;
  };

  const isStepActive = (stepIndex: number) => {
    return stepIndex === currentStepIndex;
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-PH', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  if (order.status === 'cancelled') {
    return (
      <div className="bg-red-50 border border-red-200 rounded-lg p-6">
        <div className="flex items-center gap-3">
          <div className="flex-shrink-0">
            <svg
              className="h-8 w-8 text-red-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M6 18L18 6M6 6l12 12"
              />
            </svg>
          </div>
          <div>
            <h3 className="text-lg font-semibold text-red-900">Order Cancelled</h3>
            <p className="text-sm text-red-700 mt-1">
              This order has been cancelled. If you have any questions, please contact support.
            </p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="bg-white rounded-lg shadow-md p-6">
      <h3 className="text-lg font-semibold text-gray-900 mb-6">Order Tracking</h3>

      {/* Progress Bar */}
      <div className="relative">
        {/* Connecting Line */}
        <div className="absolute top-5 left-0 right-0 h-0.5 bg-gray-200">
          <div
            className="h-full bg-blue-600 transition-all duration-500"
            style={{
              width: `${(currentStepIndex / (trackingSteps.length - 1)) * 100}%`,
            }}
          />
        </div>

        {/* Steps */}
        <div className="relative flex justify-between">
          {trackingSteps.map((step, index) => (
            <div key={step.status} className="flex flex-col items-center">
              {/* Step Circle */}
              <div
                className={`w-10 h-10 rounded-full flex items-center justify-center text-lg font-semibold transition-all duration-300 ${
                  isStepCompleted(index)
                    ? 'bg-blue-600 text-white'
                    : 'bg-gray-200 text-gray-600'
                } ${isStepActive(index) ? 'ring-4 ring-blue-200' : ''}`}
              >
                {isStepCompleted(index) ? (
                  index < currentStepIndex ? (
                    <svg
                      className="w-6 h-6"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M5 13l4 4L19 7"
                      />
                    </svg>
                  ) : (
                    step.icon
                  )
                ) : (
                  step.icon
                )}
              </div>

              {/* Step Label */}
              <div className="mt-2 text-center">
                <p
                  className={`text-xs font-medium ${
                    isStepCompleted(index) ? 'text-blue-600' : 'text-gray-600'
                  }`}
                >
                  {step.label}
                </p>
                {isStepActive(index) && (
                  <p className="text-xs text-gray-500 mt-1">
                    {formatDate(order.updatedAt)}
                  </p>
                )}
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Current Status Message */}
      <div className="mt-8 p-4 bg-blue-50 rounded-lg">
        <div className="flex items-start gap-3">
          <div className="flex-shrink-0">
            <svg
              className="h-5 w-5 text-blue-600 mt-0.5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          </div>
          <div>
            <h4 className="text-sm font-semibold text-blue-900">
              {trackingSteps[currentStepIndex]?.label}
            </h4>
            <p className="text-sm text-blue-700 mt-1">
              {getStatusMessage(order.status)}
            </p>
          </div>
        </div>
      </div>

      {/* Estimated Delivery */}
      {order.status !== 'delivered' && (
        <div className="mt-4 text-center">
          <p className="text-sm text-gray-600">
            Estimated delivery:{' '}
            <span className="font-medium text-gray-900">3-5 business days</span>
          </p>
        </div>
      )}
    </div>
  );
};

const getStatusMessage = (status: string): string => {
  switch (status) {
    case 'pending':
      return 'Your order has been received and is awaiting confirmation.';
    case 'confirmed':
      return 'Your order has been confirmed and will be processed soon.';
    case 'processing':
      return 'Your order is being prepared for shipment.';
    case 'shipped':
      return 'Your order has been shipped and is on its way to you.';
    case 'delivered':
      return 'Your order has been delivered. Thank you for shopping with us!';
    default:
      return 'Order status unknown.';
  }
};

export default OrderTracking;
