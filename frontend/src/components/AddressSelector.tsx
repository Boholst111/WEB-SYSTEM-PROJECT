import React, { useState } from 'react';
import { UserAddress } from '../services/checkoutApi';

interface AddressSelectorProps {
  addresses: UserAddress[];
  selectedAddressId: number | null;
  onSelectAddress: (addressId: number) => void;
  onAddNew: () => void;
  onEdit: (address: UserAddress) => void;
  onDelete: (addressId: number) => void;
}

const AddressSelector: React.FC<AddressSelectorProps> = ({
  addresses,
  selectedAddressId,
  onSelectAddress,
  onAddNew,
  onEdit,
  onDelete,
}) => {
  const [expandedAddressId, setExpandedAddressId] = useState<number | null>(null);

  const formatAddress = (address: UserAddress): string => {
    const parts = [
      address.address_line_1,
      address.address_line_2,
      address.city,
      address.province,
      address.postal_code,
      address.country,
    ].filter(Boolean);
    return parts.join(', ');
  };

  return (
    <div className="space-y-4">
      <div className="flex justify-between items-center">
        <h3 className="text-lg font-semibold text-gray-900">Shipping Address</h3>
        <button
          onClick={onAddNew}
          className="text-blue-600 hover:text-blue-800 font-medium text-sm"
        >
          + Add New Address
        </button>
      </div>

      {addresses.length === 0 ? (
        <div className="text-center py-8 bg-gray-50 rounded-lg">
          <p className="text-gray-600 mb-4">No saved addresses</p>
          <button
            onClick={onAddNew}
            className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700"
          >
            Add Address
          </button>
        </div>
      ) : (
        <div className="space-y-3">
          {addresses.map((address) => (
            <div
              key={address.id}
              className={`border rounded-lg p-4 cursor-pointer transition-colors ${
                selectedAddressId === address.id
                  ? 'border-blue-600 bg-blue-50'
                  : 'border-gray-300 hover:border-gray-400'
              }`}
              onClick={() => onSelectAddress(address.id)}
            >
              <div className="flex items-start justify-between">
                <div className="flex items-start gap-3 flex-1">
                  <input
                    type="radio"
                    checked={selectedAddressId === address.id}
                    onChange={() => onSelectAddress(address.id)}
                    className="mt-1"
                  />
                  <div className="flex-1">
                    <div className="flex items-center gap-2">
                      <h4 className="font-semibold text-gray-900">
                        {address.first_name} {address.last_name}
                      </h4>
                      {address.is_default && (
                        <span className="text-xs bg-green-100 text-green-800 px-2 py-0.5 rounded">
                          Default
                        </span>
                      )}
                    </div>
                    <p className="text-sm text-gray-600 mt-1">
                      {formatAddress(address)}
                    </p>
                    <p className="text-sm text-gray-600 mt-1">
                      Phone: {address.phone}
                    </p>
                  </div>
                </div>

                <div className="flex gap-2">
                  <button
                    onClick={(e) => {
                      e.stopPropagation();
                      onEdit(address);
                    }}
                    className="text-blue-600 hover:text-blue-800 text-sm"
                  >
                    Edit
                  </button>
                  {!address.is_default && (
                    <button
                      onClick={(e) => {
                        e.stopPropagation();
                        if (confirm('Delete this address?')) {
                          onDelete(address.id);
                        }
                      }}
                      className="text-red-600 hover:text-red-800 text-sm"
                    >
                      Delete
                    </button>
                  )}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default AddressSelector;
