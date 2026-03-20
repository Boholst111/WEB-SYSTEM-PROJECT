import React, { useState, useEffect } from 'react';
import { UserAddress } from '../services/checkoutApi';

interface AddressFormProps {
  address?: UserAddress | null;
  onSubmit: (address: Partial<UserAddress>) => Promise<void>;
  onCancel: () => void;
}

const AddressForm: React.FC<AddressFormProps> = ({ address, onSubmit, onCancel }) => {
  const [formData, setFormData] = useState<Partial<UserAddress>>({
    type: 'shipping',
    first_name: '',
    last_name: '',
    company: '',
    address_line_1: '',
    address_line_2: '',
    city: '',
    province: '',
    postal_code: '',
    country: 'Philippines',
    phone: '',
    is_default: false,
  });
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (address) {
      setFormData(address);
    }
  }, [address]);

  const handleChange = (field: keyof UserAddress, value: any) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
    // Clear error for this field
    if (errors[field]) {
      setErrors((prev) => {
        const next = { ...prev };
        delete next[field];
        return next;
      });
    }
  };

  const validate = (): boolean => {
    const newErrors: Record<string, string> = {};

    if (!formData.first_name?.trim()) {
      newErrors.first_name = 'First name is required';
    }
    if (!formData.last_name?.trim()) {
      newErrors.last_name = 'Last name is required';
    }
    if (!formData.address_line_1?.trim()) {
      newErrors.address_line_1 = 'Address is required';
    }
    if (!formData.city?.trim()) {
      newErrors.city = 'City is required';
    }
    if (!formData.province?.trim()) {
      newErrors.province = 'Province is required';
    }
    if (!formData.postal_code?.trim()) {
      newErrors.postal_code = 'Postal code is required';
    }
    if (!formData.phone?.trim()) {
      newErrors.phone = 'Phone number is required';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!validate()) {
      return;
    }

    setIsSubmitting(true);
    try {
      await onSubmit(formData);
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to save address');
    } finally {
      setIsSubmitting(false);
    }
  };

  const provinces = [
    'Metro Manila',
    'Cebu',
    'Davao',
    'Cavite',
    'Laguna',
    'Rizal',
    'Bulacan',
    'Pampanga',
    'Batangas',
    'Quezon',
  ];

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            First Name *
          </label>
          <input
            type="text"
            value={formData.first_name || ''}
            onChange={(e) => handleChange('first_name', e.target.value)}
            className={`w-full rounded-md border ${
              errors.first_name ? 'border-red-500' : 'border-gray-300'
            } shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2`}
          />
          {errors.first_name && (
            <p className="mt-1 text-sm text-red-600">{errors.first_name}</p>
          )}
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Last Name *
          </label>
          <input
            type="text"
            value={formData.last_name || ''}
            onChange={(e) => handleChange('last_name', e.target.value)}
            className={`w-full rounded-md border ${
              errors.last_name ? 'border-red-500' : 'border-gray-300'
            } shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2`}
          />
          {errors.last_name && (
            <p className="mt-1 text-sm text-red-600">{errors.last_name}</p>
          )}
        </div>
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">
          Company (Optional)
        </label>
        <input
          type="text"
          value={formData.company || ''}
          onChange={(e) => handleChange('company', e.target.value)}
          className="w-full rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2"
        />
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">
          Address Line 1 *
        </label>
        <input
          type="text"
          value={formData.address_line_1 || ''}
          onChange={(e) => handleChange('address_line_1', e.target.value)}
          placeholder="Street address, P.O. box"
          className={`w-full rounded-md border ${
            errors.address_line_1 ? 'border-red-500' : 'border-gray-300'
          } shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2`}
        />
        {errors.address_line_1 && (
          <p className="mt-1 text-sm text-red-600">{errors.address_line_1}</p>
        )}
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">
          Address Line 2 (Optional)
        </label>
        <input
          type="text"
          value={formData.address_line_2 || ''}
          onChange={(e) => handleChange('address_line_2', e.target.value)}
          placeholder="Apartment, suite, unit, building, floor, etc."
          className="w-full rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2"
        />
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            City *
          </label>
          <input
            type="text"
            value={formData.city || ''}
            onChange={(e) => handleChange('city', e.target.value)}
            className={`w-full rounded-md border ${
              errors.city ? 'border-red-500' : 'border-gray-300'
            } shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2`}
          />
          {errors.city && (
            <p className="mt-1 text-sm text-red-600">{errors.city}</p>
          )}
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Province *
          </label>
          <select
            value={formData.province || ''}
            onChange={(e) => handleChange('province', e.target.value)}
            className={`w-full rounded-md border ${
              errors.province ? 'border-red-500' : 'border-gray-300'
            } shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2`}
          >
            <option value="">Select Province</option>
            {provinces.map((province) => (
              <option key={province} value={province}>
                {province}
              </option>
            ))}
          </select>
          {errors.province && (
            <p className="mt-1 text-sm text-red-600">{errors.province}</p>
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Postal Code *
          </label>
          <input
            type="text"
            value={formData.postal_code || ''}
            onChange={(e) => handleChange('postal_code', e.target.value)}
            className={`w-full rounded-md border ${
              errors.postal_code ? 'border-red-500' : 'border-gray-300'
            } shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2`}
          />
          {errors.postal_code && (
            <p className="mt-1 text-sm text-red-600">{errors.postal_code}</p>
          )}
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Phone *
          </label>
          <input
            type="tel"
            value={formData.phone || ''}
            onChange={(e) => handleChange('phone', e.target.value)}
            placeholder="+63 XXX XXX XXXX"
            className={`w-full rounded-md border ${
              errors.phone ? 'border-red-500' : 'border-gray-300'
            } shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2`}
          />
          {errors.phone && (
            <p className="mt-1 text-sm text-red-600">{errors.phone}</p>
          )}
        </div>
      </div>

      <div className="flex items-center">
        <input
          type="checkbox"
          id="is_default"
          checked={formData.is_default || false}
          onChange={(e) => handleChange('is_default', e.target.checked)}
          className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
        />
        <label htmlFor="is_default" className="ml-2 text-sm text-gray-700">
          Set as default address
        </label>
      </div>

      <div className="flex gap-3 pt-4">
        <button
          type="submit"
          disabled={isSubmitting}
          className="flex-1 bg-blue-600 text-white py-2 px-4 rounded-md font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {isSubmitting ? 'Saving...' : address ? 'Update Address' : 'Add Address'}
        </button>
        <button
          type="button"
          onClick={onCancel}
          disabled={isSubmitting}
          className="flex-1 bg-white text-gray-700 py-2 px-4 rounded-md font-medium border border-gray-300 hover:bg-gray-50 disabled:opacity-50"
        >
          Cancel
        </button>
      </div>
    </form>
  );
};

export default AddressForm;
