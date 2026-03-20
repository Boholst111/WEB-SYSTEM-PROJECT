import React from 'react';
import { ShippingOption } from '../services/cartApi';

interface ShippingSelectorProps {
  options: ShippingOption[];
  selectedOption: string | null;
  onSelectOption: (optionId: string) => void;
}

const ShippingSelector: React.FC<ShippingSelectorProps> = ({
  options,
  selectedOption,
  onSelectOption,
}) => {
  return (
    <div className="space-y-4">
      <h3 className="text-lg font-semibold text-gray-900">Shipping Method</h3>

      {options.length === 0 ? (
        <div className="text-center py-8 bg-gray-50 rounded-lg">
          <p className="text-gray-600">No shipping options available</p>
        </div>
      ) : (
        <div className="space-y-3">
          {options.map((option) => (
            <div
              key={option.id}
              className={`border rounded-lg p-4 cursor-pointer transition-colors ${
                selectedOption === option.id
                  ? 'border-blue-600 bg-blue-50'
                  : 'border-gray-300 hover:border-gray-400'
              }`}
              onClick={() => onSelectOption(option.id)}
            >
              <div className="flex items-start gap-3">
                <input
                  type="radio"
                  checked={selectedOption === option.id}
                  onChange={() => onSelectOption(option.id)}
                  className="mt-1"
                />
                <div className="flex-1">
                  <div className="flex justify-between items-start">
                    <div>
                      <h4 className="font-semibold text-gray-900">{option.name}</h4>
                      <p className="text-sm text-gray-600 mt-1">
                        {option.description}
                      </p>
                      <p className="text-sm text-gray-500 mt-1">
                        Estimated delivery: {option.estimated_days}
                      </p>
                    </div>
                    <div className="text-right">
                      <p className="font-semibold text-gray-900">
                        {option.formatted_cost}
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default ShippingSelector;
