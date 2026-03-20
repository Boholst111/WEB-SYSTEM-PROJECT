import React from 'react';
import InventoryDashboard from '../../components/admin/InventoryDashboard';

const InventoryPage: React.FC = () => {
  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900">Inventory Management</h1>
          <p className="mt-2 text-gray-600">
            Manage product inventory, track stock levels, and handle pre-order arrivals
          </p>
        </div>
        
        <InventoryDashboard />
      </div>
    </div>
  );
};

export default InventoryPage;