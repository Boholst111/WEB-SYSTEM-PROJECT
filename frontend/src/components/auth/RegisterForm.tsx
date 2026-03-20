import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useAppDispatch, useAppSelector } from '../../store';
import { loginStart, loginSuccess, loginFailure } from '../../store/slices/authSlice';
import { authService } from '../../services/authApi';
import FormInput from './FormInput';

interface RegisterFormProps {
  onSuccess?: () => void;
  redirectTo?: string;
}

const RegisterForm: React.FC<RegisterFormProps> = ({ onSuccess, redirectTo = '/' }) => {
  const dispatch = useAppDispatch();
  const { isLoading, error } = useAppSelector((state) => state.auth);
  
  const [formData, setFormData] = useState({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
    phone: '',
    date_of_birth: '',
  });
  
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});

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
  };

  const validateForm = () => {
    const errors: Record<string, string> = {};
    
    if (!formData.first_name) {
      errors.first_name = 'First name is required';
    }
    
    if (!formData.last_name) {
      errors.last_name = 'Last name is required';
    }
    
    if (!formData.email) {
      errors.email = 'Email is required';
    } else if (!/\S+@\S+\.\S+/.test(formData.email)) {
      errors.email = 'Please enter a valid email address';
    }
    
    if (!formData.password) {
      errors.password = 'Password is required';
    } else if (formData.password.length < 8) {
      errors.password = 'Password must be at least 8 characters long';
    }
    
    if (!formData.password_confirmation) {
      errors.password_confirmation = 'Please confirm your password';
    } else if (formData.password !== formData.password_confirmation) {
      errors.password_confirmation = 'Passwords do not match';
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

    dispatch(loginStart());
    
    try {
      const response = await authService.register({
        first_name: formData.first_name,
        last_name: formData.last_name,
        email: formData.email,
        password: formData.password,
        password_confirmation: formData.password_confirmation,
        phone: formData.phone || undefined,
        date_of_birth: formData.date_of_birth || undefined,
      });

      if (response.success) {
        dispatch(loginSuccess({
          user: response.data.user,
          token: response.data.token,
        }));
        
        // Store token in localStorage
        localStorage.setItem('auth_token', response.data.token);
        
        if (onSuccess) {
          onSuccess();
        } else {
          window.location.href = redirectTo;
        }
      } else {
        dispatch(loginFailure(response.message || 'Registration failed'));
      }
    } catch (error: any) {
      const errorMessage = error.response?.data?.message || 'Registration failed. Please try again.';
      dispatch(loginFailure(errorMessage));
      
      // Handle validation errors
      if (error.response?.data?.errors) {
        setFormErrors(error.response.data.errors);
      }
    }
  };

  return (
    <div className="max-w-md w-full space-y-8">
      <div>
        <h2 className="mt-6 text-center text-3xl font-bold text-gray-900">
          Create your account
        </h2>
        <p className="mt-2 text-center text-sm text-gray-600">
          Or{' '}
          <Link
            to="/login"
            className="font-medium text-primary-600 hover:text-primary-500"
          >
            sign in to existing account
          </Link>
        </p>
      </div>
      
      <form className="mt-8 space-y-6" onSubmit={handleSubmit}>
        {error && (
          <div className="rounded-md bg-red-50 p-4">
            <div className="text-sm text-red-700">{error}</div>
          </div>
        )}
        
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
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
            label="Email address"
            name="email"
            type="email"
            autoComplete="email"
            required
            value={formData.email}
            onChange={handleChange}
            error={formErrors.email}
          />
          
          <FormInput
            label="Password"
            name="password"
            type="password"
            autoComplete="new-password"
            required
            value={formData.password}
            onChange={handleChange}
            error={formErrors.password}
            helperText="Must be at least 8 characters long"
          />
          
          <FormInput
            label="Confirm Password"
            name="password_confirmation"
            type="password"
            autoComplete="new-password"
            required
            value={formData.password_confirmation}
            onChange={handleChange}
            error={formErrors.password_confirmation}
          />
          
          <FormInput
            label="Phone Number (Optional)"
            name="phone"
            type="tel"
            autoComplete="tel"
            value={formData.phone}
            onChange={handleChange}
            error={formErrors.phone}
            helperText="Philippine format: +63 or 09xx-xxx-xxxx"
          />
          
          <FormInput
            label="Date of Birth (Optional)"
            name="date_of_birth"
            type="date"
            autoComplete="bday"
            value={formData.date_of_birth}
            onChange={handleChange}
            error={formErrors.date_of_birth}
          />
        </div>

        <div>
          <button
            type="submit"
            disabled={isLoading}
            className="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isLoading ? (
              <div className="flex items-center">
                <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                Creating account...
              </div>
            ) : (
              'Create account'
            )}
          </button>
        </div>
        
        <div className="text-xs text-gray-500 text-center">
          By creating an account, you agree to our Terms of Service and Privacy Policy.
        </div>
      </form>
    </div>
  );
};

export default RegisterForm;