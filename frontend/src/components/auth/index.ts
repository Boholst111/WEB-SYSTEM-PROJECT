// Authentication Components
export { default as LoginForm } from './LoginForm';
export { default as RegisterForm } from './RegisterForm';
export { default as ForgotPasswordForm } from './ForgotPasswordForm';
export { default as ResetPasswordForm } from './ResetPasswordForm';
export { default as PasswordChangeForm } from './PasswordChangeForm';
export { default as ProfileForm } from './ProfileForm';
export { default as AccountSettings } from './AccountSettings';
export { default as EmailVerification } from './EmailVerification';

// Utility Components
export { default as FormInput } from './FormInput';
export { default as ProtectedRoute } from './ProtectedRoute';
export { default as AuthRedirect } from './AuthRedirect';
export { default as AuthInitializer } from './AuthInitializer';

// Authentication Service
export { authService } from '../../services/authApi';
export type {
  LoginRequest,
  LoginResponse,
  RegisterRequest,
  ChangePasswordRequest,
  ForgotPasswordRequest,
  ResetPasswordRequest,
  UpdateProfileRequest,
} from '../../services/authApi';

// Authentication Hook
export { default as useAuth } from '../../hooks/useAuth';