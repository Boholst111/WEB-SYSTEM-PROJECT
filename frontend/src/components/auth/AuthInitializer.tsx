import { useEffect } from 'react';
import { useAppDispatch, useAppSelector } from '../../store';
import { restoreAuth, logout } from '../../store/slices/authSlice';
import { authService } from '../../services/authApi';

/**
 * AuthInitializer component
 * Restores authentication state on app load by fetching user data if token exists
 */
const AuthInitializer: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const dispatch = useAppDispatch();
  const { token, user } = useAppSelector((state) => state.auth);

  useEffect(() => {
    const initializeAuth = async () => {
      // If token exists but user data is not loaded, fetch user data
      if (token && !user) {
        try {
          const response = await authService.me();
          if (response.success && response.data.user) {
            dispatch(restoreAuth({
              user: response.data.user,
              token: token,
            }));
          } else {
            // Token is invalid, clear auth state
            dispatch(logout());
          }
        } catch (error) {
          // Token is invalid or expired, clear auth state
          console.error('Failed to restore auth:', error);
          dispatch(logout());
        }
      }
    };

    initializeAuth();
  }, [token, user, dispatch]);

  return <>{children}</>;
};

export default AuthInitializer;
