import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { Provider } from 'react-redux';
import { BrowserRouter } from 'react-router-dom';
import { configureStore } from '@reduxjs/toolkit';
import authSlice from '../../../store/slices/authSlice';
import { authService } from '../../../services/authApi';
import LoginForm from '../LoginForm';
import AccountSettings from '../AccountSettings';

// Mock the auth service
jest.mock('../../../services/authApi');
const mockedAuthService = authService as jest.Mocked<typeof authService>;

// Mock localStorage
const localStorageMock = {
  getItem: jest.fn(),
  setItem: jest.fn(),
  removeItem: jest.fn(),
  clear: jest.fn(),
};
Object.defineProperty(window, 'localStorage', {
  value: localStorageMock,
});

const createTestStore = (initialState?: any) => {
  return configureStore({
    reducer: {
      auth: authSlice,
    },
    preloadedState: initialState,
  });
};

const renderWithProviders = (component: React.ReactElement, initialState?: any) => {
  const store = createTestStore(initialState);
  return render(
    <Provider store={store}>
      <BrowserRouter>
        {component}
      </BrowserRouter>
    </Provider>
  );
};

describe('Authentication Integration', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('completes full login flow and shows user account', async () => {
    const mockUser = {
      id: 1,
      email: 'test@example.com',
      firstName: 'Test',
      lastName: 'User',
      loyaltyTier: 'bronze' as const,
      loyaltyCredits: 100,
      totalSpent: 500,
      status: 'active' as const,
      emailVerifiedAt: '2024-01-01T00:00:00Z',
      createdAt: '2024-01-01',
      updatedAt: '2024-01-01',
    };

    mockedAuthService.login.mockResolvedValue({
      success: true,
      data: {
        user: mockUser,
        token: 'test-token',
        token_type: 'Bearer',
      },
    });

    // Start with login form
    const { rerender } = renderWithProviders(<LoginForm />);
    
    const emailInput = screen.getByLabelText('Email address');
    const passwordInput = screen.getByLabelText('Password');
    const submitButton = screen.getByRole('button', { name: 'Sign in' });
    
    fireEvent.change(emailInput, { target: { value: 'test@example.com' } });
    fireEvent.change(passwordInput, { target: { value: 'password123' } });
    fireEvent.click(submitButton);

    await waitFor(() => {
      expect(mockedAuthService.login).toHaveBeenCalledWith({
        email: 'test@example.com',
        password: 'password123',
        remember: false,
      });
    });

    // Now render account settings with authenticated state
    const authenticatedState = {
      auth: {
        user: mockUser,
        token: 'test-token',
        isAuthenticated: true,
        isLoading: false,
        error: null,
      },
    };

    rerender(
      <Provider store={createTestStore(authenticatedState)}>
        <BrowserRouter>
          <AccountSettings />
        </BrowserRouter>
      </Provider>
    );

    // Verify account settings shows user information
    expect(screen.getByText('Account Settings')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Test')).toBeInTheDocument(); // First name
    expect(screen.getByDisplayValue('User')).toBeInTheDocument(); // Last name
    expect(screen.getByDisplayValue('test@example.com')).toBeInTheDocument(); // Email
    expect(screen.getByText('Current Tier: bronze')).toBeInTheDocument();
    expect(screen.getByText('Available Credits: 100.00')).toBeInTheDocument();
  });

  it('handles logout flow', async () => {
    const mockUser = {
      id: 1,
      email: 'test@example.com',
      firstName: 'Test',
      lastName: 'User',
      loyaltyTier: 'bronze' as const,
      loyaltyCredits: 100,
      totalSpent: 500,
      status: 'active' as const,
      emailVerifiedAt: '2024-01-01T00:00:00Z',
      createdAt: '2024-01-01',
      updatedAt: '2024-01-01',
    };

    mockedAuthService.logout.mockResolvedValue({
      success: true,
      data: null,
    });

    // Mock window.location.href
    delete (window as any).location;
    (window as any).location = { href: '' };

    const authenticatedState = {
      auth: {
        user: mockUser,
        token: 'test-token',
        isAuthenticated: true,
        isLoading: false,
        error: null,
      },
    };

    renderWithProviders(<AccountSettings />, authenticatedState);

    // Click on Security tab
    const securityTab = screen.getByText('Security');
    fireEvent.click(securityTab);

    // Find and click logout button
    const logoutButton = screen.getByText('Sign out');
    fireEvent.click(logoutButton);

    await waitFor(() => {
      expect(mockedAuthService.logout).toHaveBeenCalled();
    });
  });

  it('shows unauthenticated state correctly', () => {
    renderWithProviders(<AccountSettings />);
    
    expect(screen.getByText('Please log in to access account settings.')).toBeInTheDocument();
  });
});