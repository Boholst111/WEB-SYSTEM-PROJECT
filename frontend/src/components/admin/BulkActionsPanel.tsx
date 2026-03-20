import React, { useState } from 'react';
import { 
  CheckIcon, 
  TruckIcon, 
  XMarkIcon, 
  DocumentArrowDownIcon,
  ExclamationTriangleIcon
} from '@heroicons/react/24/outline';

interface BulkActionsPanelProps {
  selectedCount: number;
  onAction: (action: string, data: any) => void;
  onClear: () => void;
}

const BulkActionsPanel: React.FC<BulkActionsPanelProps> = ({
  selectedCount,
  onAction,
  onClear
}) => {
  const [showStatusUpdate, setShowStatusUpdate] = useState(false);
  const [showTrackingUpdate, setShowTrackingUpdate] = useState(false);
  const [showConfirmCancel, setShowConfirmCancel] = useState(false);
  
  const [statusData, setStatusData] = useState({
    status: '',
    admin_notes: '',
    notify_customers: true
  });

  const [trackingData, setTrackingData] = useState({
    courier_service: '',
    tracking_numbers: [''],
    admin_notes: '',
    notify_customers: true
  });

  const handleStatusUpdate = () => {
    if (!statusData.status) return;
    
    onAction('update_status', statusData);
    setShowStatusUpdate(false);
    setStatusData({ status: '', admin_notes: '', notify_customers: true });
  };

  const handleTrackingUpdate = () => {
    if (!trackingData.courier_service) return;
    
    onAction('add_tracking', {
      ...trackingData,
      tracking_numbers: trackingData.tracking_numbers.filter(t => t.trim())
    });
    setShowTrackingUpdate(false);
    setTrackingData({ courier_service: '', tracking_numbers: [''], admin_notes: '', notify_customers: true });
  };

  const handleCancelOrders = () => {
    onAction('cancel', { admin_notes: 'Bulk cancellation', notify_customers: true });
    setShowConfirmCancel(false);
  };

  const addTrackingNumberField = () => {
    setTrackingData(prev => ({
      ...prev,
      tracking_numbers: [...prev.tracking_numbers, '']
    }));
  };

  const updateTrackingNumber = (index: number, value: string) => {
    setTrackingData(prev => ({
      ...prev,
      tracking_numbers: prev.tracking_numbers.map((num, i) => i === index ? value : num)
    }));
  };

  const removeTrackingNumber = (index: number) => {
    setTrackingData(prev => ({
      ...prev,
      tracking_numbers: prev.tracking_numbers.filter((_, i) => i !== index)
    }));
  };

  return (
    <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
      <div className="flex items-center justify-between">
        <div className="flex items-center space-x-4">
          <div className="flex items-center">
            <CheckIcon className="h-5 w-5 text-blue-600 mr-2" />
            <span className="text-sm font-medium text-blue-900">
              {selectedCount} order{selectedCount !== 1 ? 's' : ''} selected
            </span>
          </div>
          
          <div className="flex space-x-2">
            <button
              onClick={() => setShowStatusUpdate(true)}
              className="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              Update Status
            </button>
            
            <button
              onClick={() => setShowTrackingUpdate(true)}
              className="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              <TruckIcon className="h-4 w-4 mr-1" />
              Add Tracking
            </button>
            
            <button
              onClick={() => setShowConfirmCancel(true)}
              className="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
            >
              Cancel Orders
            </button>
            
            <button
              onClick={() => onAction('export', {})}
              className="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-green-700 bg-green-100 hover:bg-green-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
            >
              <DocumentArrowDownIcon className="h-4 w-4 mr-1" />
              Export Selected
            </button>
          </div>
        </div>
        
        <button
          onClick={onClear}
          className="text-blue-400 hover:text-blue-500"
        >
          <XMarkIcon className="h-5 w-5" />
        </button>
      </div>

      {/* Status Update Modal */}
      {showStatusUpdate && (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
          <div className="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div className="mt-3">
              <h3 className="text-lg font-medium text-gray-900 mb-4">Update Order Status</h3>
              
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    New Status
                  </label>
                  <select
                    value={statusData.status}
                    onChange={(e) => setStatusData(prev => ({ ...prev, status: e.target.value }))}
                    className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                  >
                    <option value="">Select Status</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="processing">Processing</option>
                    <option value="shipped">Shipped</option>
                    <option value="delivered">Delivered</option>
                  </select>
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Admin Notes (Optional)
                  </label>
                  <textarea
                    value={statusData.admin_notes}
                    onChange={(e) => setStatusData(prev => ({ ...prev, admin_notes: e.target.value }))}
                    rows={3}
                    className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Add notes about this status update..."
                  />
                </div>
                
                <div className="flex items-center">
                  <input
                    type="checkbox"
                    checked={statusData.notify_customers}
                    onChange={(e) => setStatusData(prev => ({ ...prev, notify_customers: e.target.checked }))}
                    className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                  />
                  <label className="ml-2 block text-sm text-gray-900">
                    Notify customers via email
                  </label>
                </div>
              </div>
              
              <div className="flex justify-end space-x-3 mt-6">
                <button
                  onClick={() => setShowStatusUpdate(false)}
                  className="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                >
                  Cancel
                </button>
                <button
                  onClick={handleStatusUpdate}
                  disabled={!statusData.status}
                  className="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Update Status
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Tracking Update Modal */}
      {showTrackingUpdate && (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
          <div className="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div className="mt-3">
              <h3 className="text-lg font-medium text-gray-900 mb-4">Add Tracking Information</h3>
              
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Courier Service
                  </label>
                  <select
                    value={trackingData.courier_service}
                    onChange={(e) => setTrackingData(prev => ({ ...prev, courier_service: e.target.value }))}
                    className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                  >
                    <option value="">Select Courier</option>
                    <option value="lbc">LBC</option>
                    <option value="jnt">J&T Express</option>
                    <option value="ninjavan">Ninja Van</option>
                    <option value="2go">2GO Express</option>
                  </select>
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Tracking Numbers
                  </label>
                  {trackingData.tracking_numbers.map((number, index) => (
                    <div key={index} className="flex space-x-2 mb-2">
                      <input
                        type="text"
                        value={number}
                        onChange={(e) => updateTrackingNumber(index, e.target.value)}
                        placeholder="Enter tracking number"
                        className="flex-1 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                      />
                      {trackingData.tracking_numbers.length > 1 && (
                        <button
                          onClick={() => removeTrackingNumber(index)}
                          className="px-2 py-2 text-red-600 hover:text-red-800"
                        >
                          <XMarkIcon className="h-4 w-4" />
                        </button>
                      )}
                    </div>
                  ))}
                  <button
                    onClick={addTrackingNumberField}
                    className="text-sm text-blue-600 hover:text-blue-800"
                  >
                    + Add another tracking number
                  </button>
                </div>
                
                <div className="flex items-center">
                  <input
                    type="checkbox"
                    checked={trackingData.notify_customers}
                    onChange={(e) => setTrackingData(prev => ({ ...prev, notify_customers: e.target.checked }))}
                    className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                  />
                  <label className="ml-2 block text-sm text-gray-900">
                    Notify customers via email
                  </label>
                </div>
              </div>
              
              <div className="flex justify-end space-x-3 mt-6">
                <button
                  onClick={() => setShowTrackingUpdate(false)}
                  className="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                >
                  Cancel
                </button>
                <button
                  onClick={handleTrackingUpdate}
                  disabled={!trackingData.courier_service}
                  className="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Add Tracking
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Cancel Confirmation Modal */}
      {showConfirmCancel && (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
          <div className="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div className="mt-3 text-center">
              <ExclamationTriangleIcon className="h-12 w-12 text-red-600 mx-auto mb-4" />
              <h3 className="text-lg font-medium text-gray-900 mb-2">Cancel Orders</h3>
              <p className="text-sm text-gray-500 mb-6">
                Are you sure you want to cancel {selectedCount} order{selectedCount !== 1 ? 's' : ''}? 
                This action cannot be undone.
              </p>
              
              <div className="flex justify-center space-x-3">
                <button
                  onClick={() => setShowConfirmCancel(false)}
                  className="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                >
                  Keep Orders
                </button>
                <button
                  onClick={handleCancelOrders}
                  className="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700"
                >
                  Cancel Orders
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default BulkActionsPanel;