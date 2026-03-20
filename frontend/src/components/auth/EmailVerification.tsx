import React, { useState, useEffect } from 'react';
import { useSearchParams, Link } from 'react-router-dom';
import { useAppSelector } from '../../store';
import { authService } from '../../services/authApi';

const EmailVerification: React.FC = () => {
  const [searchParams] = useSearchParams();
  const { user } = useAppSelector((state) => state.auth);
  
  const [isVerifying, setIsVerifying] = useState(false);
  const [isVerified, setIsVerified] = useState(false);
  const [error, setError] = useState('');
  const [isResending, setIsResending] = useState(false);
  const [resendMessage, setResendMessage] = useState('');

  const id = searchParams.get('id');
  const hash = searchParams.get('hash');

  useEffect(() => {
    const verifyEmail = async () => {
      if (!id || !hash) {
        setError('Invalid verification link');
        return;
      }

      setIsVerifying(true);
      
      try {
        const response = await authService.verifyEmail(parseInt(id), hash);
        
        if (response.success) {
          setIsVerified(true);
        } else {
          setError(response.message || 'Email verification failed');
        }
      } catch (error: any) {
        const errorMessage = error.response?.data?.message || 'Email verification failed';
        setError(errorMessage);
      } finally {
        setIsVerifying(false);
      }
    };

    if (id && hash) {
      verifyEmail();
    }
  }, [id, hash]);

  const handleResendVerification = async () => {
    setIsResending(true);
    setResendMessage('');
    
    try {
      const response = await authService.resendVerification();
      
      if (response.success) {
        setResendMessage('Verification email sent! Please check your inbox.');
      }
    } catch (error: any) {
      const errorMessage = error.response?.data?.message || 'Failed to send verification email';
      setResendMessage(errorMessage);
    } finally {
      setIsResending(false);
    }
  };

  if (isVerifying) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
        <div className="max-w-md w-full space-y-8">
          <div className="text-center">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mx-auto"></div>
            <h2 className="mt-6 text-center text-3xl font-bold text-gray-900">
              Verifying your email
            </h2>
            <p className="mt-2 text-center text-sm text-gray-600">
              Please wait while we verify your email address...
            </p>
          </div>
        </div>
      </div>
    );
  }

  if (isVerified) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
        <div className="max-w-md w-full space-y-8">
          <div className="text-center">
            <div className="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
              <svg className="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <h2 className="mt-6 text-center text-3xl font-bold text-gray-900">
              Email verified successfully!
            </h2>
            <p className="mt-2 text-center text-sm text-gray-600">
              Your email address has been verified. You can now access all features of your account.
            </p>
          </div>
          
          <div className="text-center">
            <Link
              to="/"
              className="inline-flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700"
            >
              Continue to Diecast Empire
            </Link>
          </div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
        <div className="max-w-md w-full space-y-8">
          <div className="text-center">
            <div className="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
              <svg className="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              </svg>
            </div>
            <h2 className="mt-6 text-center text-3xl font-bold text-gray-900">
              Email verification failed
            </h2>
            <p className="mt-2 text-center text-sm text-gray-600">
              {error}
            </p>
          </div>
          
          {user && !user.emailVerifiedAt && (
            <div className="text-center space-y-4">
              {resendMessage && (
                <div className={`rounded-md p-4 ${resendMessage.includes('sent') ? 'bg-green-50' : 'bg-red-50'}`}>
                  <div className={`text-sm ${resendMessage.includes('sent') ? 'text-green-700' : 'text-red-700'}`}>
                    {resendMessage}
                  </div>
                </div>
              )}
              
              <button
                onClick={handleResendVerification}
                disabled={isResending}
                className="inline-flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {isResending ? (
                  <div className="flex items-center">
                    <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                    Sending...
                  </div>
                ) : (
                  'Resend verification email'
                )}
              </button>
            </div>
          )}
          
          <div className="text-center">
            <Link
              to="/"
              className="font-medium text-primary-600 hover:text-primary-500"
            >
              Back to home
            </Link>
          </div>
        </div>
      </div>
    );
  }

  // Show verification prompt for logged-in users who haven't verified their email
  if (user && !user.emailVerifiedAt) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
        <div className="max-w-md w-full space-y-8">
          <div className="text-center">
            <div className="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100">
              <svg className="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
              </svg>
            </div>
            <h2 className="mt-6 text-center text-3xl font-bold text-gray-900">
              Verify your email
            </h2>
            <p className="mt-2 text-center text-sm text-gray-600">
              Please check your email and click the verification link to activate your account.
            </p>
            <p className="mt-1 text-center text-sm text-gray-500">
              We sent a verification email to <strong>{user.email}</strong>
            </p>
          </div>
          
          <div className="text-center space-y-4">
            {resendMessage && (
              <div className={`rounded-md p-4 ${resendMessage.includes('sent') ? 'bg-green-50' : 'bg-red-50'}`}>
                <div className={`text-sm ${resendMessage.includes('sent') ? 'text-green-700' : 'text-red-700'}`}>
                  {resendMessage}
                </div>
              </div>
            )}
            
            <button
              onClick={handleResendVerification}
              disabled={isResending}
              className="inline-flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isResending ? (
                <div className="flex items-center">
                  <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                  Sending...
                </div>
              ) : (
                'Resend verification email'
              )}
            </button>
          </div>
          
          <div className="text-center">
            <Link
              to="/"
              className="font-medium text-primary-600 hover:text-primary-500"
            >
              Continue to site
            </Link>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-md w-full space-y-8">
        <div className="text-center">
          <h2 className="mt-6 text-center text-3xl font-bold text-gray-900">
            Email Verification
          </h2>
          <p className="mt-2 text-center text-sm text-gray-600">
            Please use a valid verification link to verify your email address.
          </p>
        </div>
        
        <div className="text-center">
          <Link
            to="/login"
            className="font-medium text-primary-600 hover:text-primary-500"
          >
            Sign in to your account
          </Link>
        </div>
      </div>
    </div>
  );
};

export default EmailVerification;