import { useEffect } from 'react';
import { useAppDispatch, useAppSelector } from '../store';
import { loginSuccess, logout } from '../store/slices/authSlice';
import { authService } from '../services/authApi';

export const useAuth = () => {
  const dispatch = useAppDispatch();
  const authState = useAppSelector((state) => state.auth);

  // Initialize auth state from localStorage on app start
  useEffect(() => {
    const initializeAuth = async () => {
      const token = localStorage.getItem('auth_token');
      
      if (token && !authState.user) {
        try {
          const response = await authService.me();
          
          if (response.success) {
            dispatch(loginSuccess({
              user: response.data.user,
              token,
            }));
          } else {
            // Invalid token, clear it
            localStorage.removeItem('auth_token');
            dispatch(logout());
          }
        } catch (error) {
          // Token is invalid or expired
          localStorage.removeItem('auth_token');
          dispatch(logout());
        }
      }
    };

    initializeAuth();
  }, [dispatch, authState.user]);

  const login = async (email: string, password: string, remember = false) => {
    try {
      const response = await authService.login({ email, password, remember });
      
      if (response.success) {
        dispatch(loginSuccess({
          user: response.data.user,
          token: response.data.token,
        }));
        localStorage.setItem('auth_token', response.data.token);
        return { success: true };
      } else {
        return { success: false, message: response.message };
      }
    } catch (error: any) {
      return {
        success: false,
        message: error.response?.data?.message || 'Login failed',
        errors: error.response?.data?.errors,
      };
    }
  };

  const register = async (userData: {
    first_name: string;
    last_name: string;
    email: string;
    password: string;
    password_confirmation: string;
    phone?: string;
    date_of_birth?: string;
  }) => {
    try {
      const response = await authService.register(userData);
      
      if (response.success) {
        dispatch(loginSuccess({
          user: response.data.user,
          token: response.data.token,
        }));
        localStorage.setItem('auth_token', response.data.token);
        return { success: true };
      } else {
        return { success: false, message: response.message };
      }
    } catch (error: any) {
      return {
        success: false,
        message: error.response?.data?.message || 'Registration failed',
        errors: error.response?.data?.errors,
      };
    }
  };

  const logoutUser = async () => {
    try {
      await authService.logout();
    } catch (error) {
      // Even if logout fails on server, clear local state
      console.error('Logout error:', error);
    } finally {
      dispatch(logout());
      localStorage.removeItem('auth_token');
    }
  };

  const refreshToken = async () => {
    try {
      const response = await authService.refreshToken();
      
      if (response.success) {
        localStorage.setItem('auth_token', response.data.token);
        return true;
      }
      return false;
    } catch (error) {
      // Token refresh failed, logout user
      dispatch(logout());
      localStorage.removeItem('auth_token');
      return false;
    }
  };

  return {
    ...authState,
    login,
    register,
    logout: logoutUser,
    refreshToken,
  };
};

export default useAuth;