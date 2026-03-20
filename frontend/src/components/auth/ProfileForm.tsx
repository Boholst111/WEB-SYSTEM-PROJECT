import React, { useState, useEffect } from 'react';
import { useAppDispatch, useAppSelector } from '../../store';
import { updateUser } from '../../store/slices/authSlice';
import { authService } from '../../services/authApi';
import FormInput from './FormInput';

const ProfileForm: React.FC = () => {
  const dispatch = useAppDispatch();
  const { user } = useAppSelector((state) => state.auth);
  
  const [formData, setFormData] = useState({
    first_name: '',
    last_name: '',
    phone: '',
    date_of_birth: '',
  });
  
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const [isLoading, setIsLoading] = useState(false);
  const [successMessage, setSuccessMessage] = useState('');

  useEffect(() => {
    if (user) {
      setFormData({
        first_name: user.firstName || '',
        last_name: user.lastName || '',
        phone: user.phone || '',
        date_of_birth: user.dateOfBirth || '',
      });
    }
  }, [user]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
    
    // Clear field error when user starts typing
    if (formErrors[name]) {
      setFormErrors(prev => ({ ...prev, [name]: '' }));
    }
    
    // Clear success message when user makes changes
    if (successMessage) {
      setSuccessMessage('');
    }
  };

  const validateForm = () => {
    const errors: Record<string, string> = {};
    
    if (!formData.first_name) {
      errors.first_name = 'First name is required';
    }
    
    if (!formData.last_name) {
      errors.last_name = 'Last name is required';
    }
    
    if (formData.phone && !/^(\+63|0)[0-9]{10}$/.test(formData.phone.replace(/\s/g, ''))) {
      errors.phone = 'Please enter a valid Philippine phone number';
    }
    
    if (formData.date_of_birth) {
      const birthDate = new Date(formData.date_of_birth);
      const today = new Date();
      if (birthDate >= today) {
        errors.date_of_birth = 'Birth date must be in the past';
      }
    }
    
    setFormErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!validateForm()) {
      return;
    }

    setIsLoading(true);
    
    try {
      const response = await authService.updateProfile({
        first_name: formData.first_name,
        last_name: formData.last_name,
        phone: formData.phone || undefined,
        date_of_birth: formData.date_of_birth || undefined,
      });

      if (response.success) {
        dispatch(updateUser(response.data.user));
        setSuccessMessage('Profile updated successfully!');
      }
    } catch (error: any) {
      const errorMessage = error.response?.data?.message || 'Failed to update profile';
      
      // Handle validation errors
      if (error.response?.data?.errors) {
        setFormErrors(error.response.data.errors);
      } else {
        setFormErrors({ general: errorMessage });
      }
    } finally {
      setIsLoading(false);
    }
  };

  if (!user) {
    return (
      <div className="text-center py-8">
        <p className="text-gray-500">Please log in to view your profile.</p>
      </div>
    );
  }

  return (
    <div className="max-w-2xl mx-auto">
      <div className="bg-white shadow rounded-lg">
        <div className="px-6 py-4 border-b border-gray-200">
          <h3 className="text-lg font-medium text-gray-900">Profile Information</h3>
          <p className="mt-1 text-sm text-gray-500">
            Update your account information and preferences.
          </p>
        </div>
        
        <form onSubmit={handleSubmit} className="px-6 py-4 space-y-6">
          {successMessage && (
            <div className="rounded-md bg-green-50 p-4">
              <div className="text-sm text-green-700">{successMessage}</div>
            </div>
          )}
          
          {formErrors.general && (
            <div className="rounded-md bg-red-50 p-4">
              <div className="text-sm text-red-700">{formErrors.general}</div>
            </div>
          )}
          
          <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <FormInput
              label="First Name"
              name="first_name"
              type="text"
              autoComplete="given-name"
              required
              value={formData.first_name}
              onChange={handleChange}
              error={formErrors.first_name}
            />
            
            <FormInput
              label="Last Name"
              name="last_name"
              type="text"
              autoComplete="family-name"
              required
              value={formData.last_name}
              onChange={handleChange}
              error={formErrors.last_name}
            />
          </div>
          
          <FormInput
            label="Email Address"
            name="email"
            type="email"
            value={user.email}
            disabled
            className="bg-gray-50"
            helperText="Email address cannot be changed. Contact support if needed."
          />
          
          <FormInput
            label="Phone Number"
            name="phone"
            type="tel"
            autoComplete="tel"
            value={formData.phone}
            onChange={handleChange}
            error={formErrors.phone}
            helperText="Philippine format: +63 or 09xx-xxx-xxxx"
          />
          
          <FormInput
            label="Date of Birth"
            name="date_of_birth"
            type="date"
            autoComplete="bday"
            value={formData.date_of_birth}
            onChange={handleChange}
            error={formErrors.date_of_birth}
          />
          
          <div className="pt-4 border-t border-gray-200">
            <div className="flex justify-end">
              <button
                type="submit"
                disabled={isLoading}
                className="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {isLoading ? (
                  <div className="flex items-center">
                    <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                    Updating...
                  </div>
                ) : (
                  'Update Profile'
                )}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  );
};

export default ProfileForm;