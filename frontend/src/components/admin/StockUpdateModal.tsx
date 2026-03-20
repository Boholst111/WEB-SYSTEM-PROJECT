import React, { useState } from 'react';
import { Modal } from '../ui/Modal';
import { Button } from '../ui/Button';
import { Input } from '../ui/Input';
import { Select } from '../ui/Select';
import { Textarea } from '../ui/Textarea';
import { inventoryApi } from '../../services/inventoryApi';
import { Product } from '../../types/inventory';

interface StockUpdateModalProps {
  product: Product;
  onClose: () => void;
  onSuccess: () => void;
}

const StockUpdateModal: React.FC<StockUpdateModalProps> = ({
  product,
  onClose,
  onSuccess
}) => {
  const [formData, setFormData] = useState({
    quantity: '',
    type: 'restock',
    reason: ''
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!formData.quantity || !formData.reason.trim()) {
      setError('Please fill in all required fields');
      return;
    }

    const quantity = parseInt(formData.quantity);
    if (isNaN(quantity) || quantity <= 0) {
      setError('Please enter a valid quantity');
      return;
    }

    try {
      setLoading(true);
      setError(null);

      const response = await inventoryApi.updateStock(product.id, {
        quantity,
        type: formData.type,
        reason: formData.reason
      });

      if (response.success) {
        onSuccess();
      } else {
        setError(response.message || 'Failed to update stock');
      }
    } catch (err) {
      setError('Error updating stock');
      console.error('Stock update error:', err);
    } finally {
      setLoading(false);
    }
  };

  const getQuantityLabel = () => {
    switch (formData.type) {
      case 'restock':
        return 'Quantity to Add';
      case 'adjustment':
        return 'New Stock Quantity';
      case 'damage':
        return 'Quantity to Remove';
      case 'return':
        return 'Quantity Returned';
      default:
        return 'Quantity';
    }
  };

  const getQuantityHelp = () => {
    switch (formData.type) {
      case 'restock':
        return `Current stock: ${product.stock_quantity}. Enter amount to add.`;
      case 'adjustment':
        return `Current stock: ${product.stock_quantity}. Enter the new total quantity.`;
      case 'damage':
        return `Current stock: ${product.stock_quantity}. Enter amount to remove.`;
      case 'return':
        return `Current stock: ${product.stock_quantity}. Enter returned quantity.`;
      default:
        return '';
    }
  };

  return (
    <Modal isOpen onClose={onClose} title="Update Stock">
      <form onSubmit={handleSubmit} className="space-y-4">
        {/* Product Info */}
        <div className="bg-gray-50 p-4 rounded-lg">
          <div className="flex items-center space-x-3">
            {product.main_image && (
              <img
                src={product.main_image}
                alt={product.name}
                className="w-12 h-12 object-cover rounded"
              />
            )}
            <div>
              <h3 className="font-medium">{product.name}</h3>
              <p className="text-sm text-gray-500">SKU: {product.sku}</p>
              <p className="text-sm text-gray-500">Current Stock: {product.stock_quantity}</p>
            </div>
          </div>
        </div>

        {/* Update Type */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Update Type
          </label>
          <Select
            value={formData.type}
            onValueChange={(value) => setFormData(prev => ({ ...prev, type: value }))}
            required
          >
            <option value="restock">Restock (Add Inventory)</option>
            <option value="adjustment">Adjustment (Set Exact Amount)</option>
            <option value="damage">Damage (Remove from Stock)</option>
            <option value="return">Return (Add Back to Stock)</option>
          </Select>
        </div>

        {/* Quantity */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            {getQuantityLabel()} *
          </label>
          <Input
            type="number"
            min="1"
            value={formData.quantity}
            onChange={(e) => setFormData(prev => ({ ...prev, quantity: e.target.value }))}
            placeholder="Enter quantity"
            required
          />
          <p className="text-xs text-gray-500 mt-1">{getQuantityHelp()}</p>
        </div>

        {/* Reason */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Reason *
          </label>
          <Textarea
            value={formData.reason}
            onChange={(e) => setFormData(prev => ({ ...prev, reason: e.target.value }))}
            placeholder="Enter reason for stock update..."
            rows={3}
            required
          />
        </div>

        {/* Error Message */}
        {error && (
          <div className="bg-red-50 border border-red-200 rounded-md p-3">
            <p className="text-sm text-red-600">{error}</p>
          </div>
        )}

        {/* Actions */}
        <div className="flex justify-end space-x-3 pt-4">
          <Button
            type="button"
            variant="outline"
            onClick={onClose}
            disabled={loading}
          >
            Cancel
          </Button>
          <Button
            type="submit"
            disabled={loading}
          >
            {loading ? 'Updating...' : 'Update Stock'}
          </Button>
        </div>
      </form>
    </Modal>
  );
};

export default StockUpdateModal;