import axios from 'axios';
import { User, ApiResponse } from '../types';

const API_BASE_URL = process.env.REACT_APP_API_URL || 'http://localhost:8080/api';

const authApi = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Add auth token to requests if available
authApi.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export interface LoginRequest {
  email: string;
  password: string;
  remember?: boolean;
}

export interface LoginResponse {
  user: User;
  token: string;
  token_type: string;
}

export interface RegisterRequest {
  first_name: string;
  last_name: string;
  email: string;
  password: string;
  password_confirmation: string;
  phone?: string;
  date_of_birth?: string;
}

export interface ChangePasswordRequest {
  current_password: string;
  password: string;
  password_confirmation: string;
}

export interface ForgotPasswordRequest {
  email: string;
}

export interface ResetPasswordRequest {
  email: string;
  token: string;
  password: string;
  password_confirmation: string;
}

export interface UpdateProfileRequest {
  first_name?: string;
  last_name?: string;
  phone?: string;
  date_of_birth?: string;
  preferences?: Record<string, any>;
}

export const authService = {
  // Authentication
  login: async (data: LoginRequest): Promise<ApiResponse<LoginResponse>> => {
    const response = await authApi.post('/auth/login', data);
    return response.data;
  },

  register: async (data: RegisterRequest): Promise<ApiResponse<LoginResponse>> => {
    const response = await authApi.post('/auth/register', data);
    return response.data;
  },

  logout: async (): Promise<ApiResponse<null>> => {
    const response = await authApi.post('/auth/logout');
    return response.data;
  },

  logoutAll: async (): Promise<ApiResponse<null>> => {
    const response = await authApi.post('/auth/logout-all');
    return response.data;
  },

  refreshToken: async (): Promise<ApiResponse<{ token: string; token_type: string }>> => {
    const response = await authApi.post('/auth/refresh');
    return response.data;
  },

  me: async (): Promise<ApiResponse<{ user: User }>> => {
    const response = await authApi.get('/auth/me');
    return response.data;
  },

  // Password Management
  changePassword: async (data: ChangePasswordRequest): Promise<ApiResponse<null>> => {
    const response = await authApi.post('/auth/change-password', data);
    return response.data;
  },

  forgotPassword: async (data: ForgotPasswordRequest): Promise<ApiResponse<null>> => {
    const response = await authApi.post('/auth/forgot-password', data);
    return response.data;
  },

  resetPassword: async (data: ResetPasswordRequest): Promise<ApiResponse<null>> => {
    const response = await authApi.post('/auth/reset-password', data);
    return response.data;
  },

  validateResetToken: async (email: string, token: string): Promise<ApiResponse<null>> => {
    const response = await authApi.post('/auth/validate-reset-token', { email, token });
    return response.data;
  },

  // Email Verification
  verifyEmail: async (id: number, hash: string): Promise<ApiResponse<null>> => {
    const response = await authApi.post('/auth/verify-email', { id, hash });
    return response.data;
  },

  resendVerification: async (): Promise<ApiResponse<null>> => {
    const response = await authApi.post('/auth/resend-verification');
    return response.data;
  },

  // Profile Management
  updateProfile: async (data: UpdateProfileRequest): Promise<ApiResponse<{ user: User }>> => {
    const response = await authApi.put('/auth/profile', data);
    return response.data;
  },
};

export default authService;