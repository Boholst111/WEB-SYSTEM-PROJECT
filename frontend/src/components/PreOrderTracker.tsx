import React from 'react';
import { PreOrder } from '../types';
import { 
  CheckCircleIcon,
  ClockIcon,
  TruckIcon,
  CurrencyDollarIcon,
  CalendarIcon,
  ExclamationTriangleIcon
} from '@heroicons/react/24/outline';

interface PreOrderTrackerProps {
  preorder: PreOrder;
  showDetails?: boolean;
}

interface TrackingStep {
  id: string;
  title: string;
  description: string;
  icon: React.ComponentType<any>;
  status: 'completed' | 'current' | 'pending' | 'overdue';
  date?: string;
  estimatedDate?: string;
}

const PreOrderTracker: React.FC<PreOrderTrackerProps> = ({
  preorder,
  showDetails = true
}) => {
  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-PH', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  };

  const getDaysUntilDue = () => {
    if (!preorder.fullPaymentDueDate) return null;
    const dueDate = new Date(preorder.fullPaymentDueDate);
    const today = new Date();
    const diffTime = dueDate.getTime() - today.getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return diffDays;
  };

  const daysUntilDue = getDaysUntilDue();
  const isOverdue = daysUntilDue !== null && daysUntilDue < 0;

  const getTrackingSteps = (): TrackingStep[] => {
    const steps: TrackingStep[] = [
      {
        id: 'created',
        title: 'Pre-order Created',
        description: 'Your pre-order has been successfully created',
        icon: CheckCircleIcon,
        status: 'completed',
        date: preorder.createdAt
      },
      {
        id: 'deposit',
        title: 'Deposit Payment',
        description: preorder.depositPaidAt 
          ? 'Deposit payment received and confirmed'
          : 'Waiting for deposit payment to secure your pre-order',
        icon: CurrencyDollarIcon,
        status: preorder.depositPaidAt ? 'completed' : 
                preorder.status === 'deposit_pending' ? 'current' : 'pending',
        date: preorder.depositPaidAt
      },
      {
        id: 'arrival',
        title: 'Product Arrival',
        description: preorder.actualArrivalDate 
          ? 'Product has arrived at our warehouse'
          : 'Waiting for product to arrive from manufacturer',
        icon: TruckIcon,
        status: preorder.actualArrivalDate ? 'completed' :
                preorder.status === 'deposit_paid' ? 'current' : 'pending',
        date: preorder.actualArrivalDate,
        estimatedDate: preorder.estimatedArrivalDate
      },
      {
        id: 'final_payment',
        title: 'Final Payment',
        description: preorder.status === 'completed' 
          ? 'Final payment completed successfully'
          : preorder.status === 'ready_for_payment'
            ? isOverdue 
              ? 'Payment is overdue - please complete immediately'
              : 'Ready for final payment completion'
            : 'Waiting for product arrival before final payment',
        icon: CurrencyDollarIcon,
        status: preorder.status === 'completed' ? 'completed' :
                preorder.status === 'ready_for_payment' ? 
                  (isOverdue ? 'overdue' : 'current') : 'pending',
        estimatedDate: preorder.fullPaymentDueDate
      },
      {
        id: 'shipping',
        title: 'Order Shipped',
        description: preorder.status === 'completed' 
          ? 'Your order is being prepared for shipment'
          : 'Order will be shipped after payment completion',
        icon: TruckIcon,
        status: preorder.status === 'completed' ? 'current' : 'pending'
      }
    ];

    return steps;
  };

  const getStatusIcon = (status: TrackingStep['status']) => {
    switch (status) {
      case 'completed':
        return <CheckCircleIcon className="w-6 h-6 text-green-600" />;
      case 'current':
        return <ClockIcon className="w-6 h-6 text-blue-600" />;
      case 'overdue':
        return <ExclamationTriangleIcon className="w-6 h-6 text-red-600" />;
      default:
        return <ClockIcon className="w-6 h-6 text-gray-400" />;
    }
  };

  const getStatusColor = (status: TrackingStep['status']) => {
    switch (status) {
      case 'completed':
        return 'border-green-600 bg-green-50';
      case 'current':
        return 'border-blue-600 bg-blue-50';
      case 'overdue':
        return 'border-red-600 bg-red-50';
      default:
        return 'border-gray-300 bg-gray-50';
    }
  };

  const getConnectorColor = (currentStatus: TrackingStep['status'], nextStatus?: TrackingStep['status']) => {
    if (currentStatus === 'completed') {
      return 'bg-green-600';
    }
    return 'bg-gray-300';
  };

  const trackingSteps = getTrackingSteps();
  const currentStepIndex = trackingSteps.findIndex(step => step.status === 'current' || step.status === 'overdue');
  const completedSteps = trackingSteps.filter(step => step.status === 'completed').length;
  const totalSteps = trackingSteps.length;
  const progressPercentage = (completedSteps / totalSteps) * 100;

  return (
    <div className="bg-white rounded-lg shadow-md border border-gray-200 p-6">
      <div className="mb-6">
        <div className="flex items-center justify-between mb-2">
          <h3 className="text-lg font-semibold text-gray-900">Pre-Order Progress</h3>
          <span className="text-sm text-gray-600">
            {completedSteps} of {totalSteps} steps completed
          </span>
        </div>
        
        {/* Progress Bar */}
        <div className="w-full bg-gray-200 rounded-full h-2">
          <div 
            className="bg-primary-600 h-2 rounded-full transition-all duration-300"
            style={{ width: `${progressPercentage}%` }}
          />
        </div>
      </div>

      {/* Timeline */}
      <div className="space-y-6">
        {trackingSteps.map((step, index) => {
          const isLast = index === trackingSteps.length - 1;
          const StepIcon = step.icon;
          
          return (
            <div key={step.id} className="relative">
              {/* Connector Line */}
              {!isLast && (
                <div className="absolute left-6 top-12 w-0.5 h-6 bg-gray-300" />
              )}
              
              <div className="flex items-start">
                {/* Icon */}
                <div className={`flex-shrink-0 w-12 h-12 rounded-full border-2 flex items-center justify-center ${getStatusColor(step.status)}`}>
                  {getStatusIcon(step.status)}
                </div>
                
                {/* Content */}
                <div className="ml-4 flex-1">
                  <div className="flex items-center justify-between">
                    <h4 className={`font-semibold ${
                      step.status === 'completed' ? 'text-green-900' :
                      step.status === 'current' ? 'text-blue-900' :
                      step.status === 'overdue' ? 'text-red-900' :
                      'text-gray-600'
                    }`}>
                      {step.title}
                    </h4>
                    
                    {/* Date/Status */}
                    <div className="text-right">
                      {step.date && (
                        <p className="text-sm font-medium text-gray-900">
                          {formatDate(step.date)}
                        </p>
                      )}
                      {!step.date && step.estimatedDate && (
                        <p className={`text-sm ${
                          step.status === 'overdue' ? 'text-red-600 font-medium' :
                          step.status === 'current' ? 'text-blue-600' :
                          'text-gray-500'
                        }`}>
                          {step.status === 'overdue' ? 'Overdue' : 'Est.'} {formatDate(step.estimatedDate)}
                        </p>
                      )}
                    </div>
                  </div>
                  
                  <p className={`text-sm mt-1 ${
                    step.status === 'overdue' ? 'text-red-700' :
                    step.status === 'current' ? 'text-blue-700' :
                    step.status === 'completed' ? 'text-green-700' :
                    'text-gray-600'
                  }`}>
                    {step.description}
                  </p>
                  
                  {/* Additional Details */}
                  {showDetails && step.status === 'current' && (
                    <div className="mt-2">
                      {step.id === 'deposit' && (
                        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                          <p className="text-sm text-yellow-800">
                            <strong>Action Required:</strong> Pay your deposit of{' '}
                            <span className="font-semibold">
                              ₱{preorder.depositAmount.toLocaleString()}
                            </span>{' '}
                            to secure your pre-order.
                          </p>
                        </div>
                      )}
                      
                      {step.id === 'final_payment' && (
                        <div className={`border rounded-lg p-3 ${
                          isOverdue 
                            ? 'bg-red-50 border-red-200' 
                            : 'bg-blue-50 border-blue-200'
                        }`}>
                          <p className={`text-sm ${isOverdue ? 'text-red-800' : 'text-blue-800'}`}>
                            <strong>{isOverdue ? 'Urgent:' : 'Action Required:'}</strong> Complete your final payment of{' '}
                            <span className="font-semibold">
                              ₱{preorder.remainingAmount.toLocaleString()}
                            </span>
                            {daysUntilDue !== null && (
                              <span>
                                {isOverdue 
                                  ? ` (${Math.abs(daysUntilDue)} days overdue)`
                                  : ` within ${daysUntilDue} days`
                                }
                              </span>
                            )}
                          </p>
                        </div>
                      )}
                      
                      {step.id === 'arrival' && (
                        <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
                          <p className="text-sm text-blue-800">
                            We're waiting for your product to arrive from the manufacturer. 
                            You'll be notified as soon as it's ready for final payment.
                          </p>
                        </div>
                      )}
                    </div>
                  )}
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {/* Summary Card */}
      {showDetails && (
        <div className="mt-6 pt-6 border-t border-gray-200">
          <div className="grid grid-cols-2 gap-4">
            <div className="bg-gray-50 rounded-lg p-3">
              <p className="text-xs text-gray-500 mb-1">Deposit Status</p>
              <p className={`text-sm font-semibold ${
                preorder.depositPaidAt ? 'text-green-600' : 'text-yellow-600'
              }`}>
                {preorder.depositPaidAt ? 'Paid' : 'Pending'}
              </p>
              <p className="text-xs text-gray-600">
                ₱{preorder.depositAmount.toLocaleString()}
              </p>
            </div>
            
            <div className="bg-gray-50 rounded-lg p-3">
              <p className="text-xs text-gray-500 mb-1">Remaining Payment</p>
              <p className={`text-sm font-semibold ${
                preorder.status === 'completed' ? 'text-green-600' :
                preorder.status === 'ready_for_payment' ? 
                  (isOverdue ? 'text-red-600' : 'text-blue-600') :
                'text-gray-600'
              }`}>
                {preorder.status === 'completed' ? 'Completed' :
                 preorder.status === 'ready_for_payment' ? 
                   (isOverdue ? 'Overdue' : 'Due') :
                 'Pending'}
              </p>
              <p className="text-xs text-gray-600">
                ₱{preorder.remainingAmount.toLocaleString()}
              </p>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default PreOrderTracker;