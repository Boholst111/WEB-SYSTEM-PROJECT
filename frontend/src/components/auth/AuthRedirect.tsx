import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAppSelector } from '../../store';

interface AuthRedirectProps {
  children: React.ReactNode;
}

const AuthRedirect: React.FC<AuthRedirectProps> = ({ children }) => {
  const { isAuthenticated, user } = useAppSelector((state) => state.auth);
  const location = useLocation();

  if (isAuthenticated) {
    // Get the intended destination from location state
    const from = (location.state as any)?.from?.pathname;
    
    // If there's a specific destination, go there
    if (from) {
      return <Navigate to={from} replace />;
    }
    
    // Otherwise, redirect based on user role
    if (user?.role === 'admin') {
      return <Navigate to="/admin" replace />;
    }
    
    // Default to home for regular users
    return <Navigate to="/" replace />;
  }

  return <>{children}</>;
};

export default AuthRedirect;