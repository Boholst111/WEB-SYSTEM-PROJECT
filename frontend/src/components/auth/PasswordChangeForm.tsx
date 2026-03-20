import React, { useState } from 'react';
import { authService } from '../../services/authApi';
import FormInput from './FormInput';

const PasswordChangeForm: React.FC = () => {
  const [formData, setFormData] = useState({
    current_password: '',
    password: '',
    password_confirmation: '',
  });
  
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const [isLoading, setIsLoading] = useState(false);
  const [successMessage, setSuccessMessage] = useState('');

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
    
    if (!formData.current_password) {
      errors.current_password = 'Current password is required';
    }
    
    if (!formData.password) {
      errors.password = 'New password is required';
    } else if (formData.password.length < 8) {
      errors.password = 'Password must be at least 8 characters long';
    } else if (formData.password === formData.current_password) {
      errors.password = 'New password must be different from current password';
    }
    
    if (!formData.password_confirmation) {
      errors.password_confirmation = 'Please confirm your new password';
    } else if (formData.password !== formData.password_confirmation) {
      errors.password_confirmation = 'Passwords do not match';
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
      const response = await authService.changePassword({
        current_password: formData.current_password,
        password: formData.password,
        password_confirmation: formData.password_confirmation,
      });

      if (response.success) {
        setSuccessMessage('Password changed successfully!');
        setFormData({
          current_password: '',
          password: '',
          password_confirmation: '',
        });
      }
    } catch (error: any) {
      const errorMessage = error.response?.data?.message || 'Failed to change password';
      
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

  return (
    <div className="max-w-2xl mx-auto">
      <div className="bg-white shadow rounded-lg">
        <div className="px-6 py-4 border-b border-gray-200">
          <h3 className="text-lg font-medium text-gray-900">Change Password</h3>
          <p className="mt-1 text-sm text-gray-500">
            Update your password to keep your account secure.
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
          
          <FormInput
            label="Current Password"
            name="current_password"
            type="password"
            autoComplete="current-password"
            required
            value={formData.current_password}
            onChange={handleChange}
            error={formErrors.current_password}
          />
          
          <FormInput
            label="New Password"
            name="password"
            type="password"
            autoComplete="new-password"
            required
            value={formData.password}
            onChange={handleChange}
            error={formErrors.password}
            helperText="Must be at least 8 characters long and different from current password"
          />
          
          <FormInput
            label="Confirm New Password"
            name="password_confirmation"
            type="password"
            autoComplete="new-password"
            required
            value={formData.password_confirmation}
            onChange={handleChange}
            error={formErrors.password_confirmation}
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
                    Changing Password...
                  </div>
                ) : (
                  'Change Password'
                )}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  );
};

export default PasswordChangeForm;