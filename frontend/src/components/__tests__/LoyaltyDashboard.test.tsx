import React from 'react';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import { Provider } from 'react-redux';
import { configureStore } from '@reduxjs/toolkit';
import LoyaltyDashboard from '../LoyaltyDashboard';
import { loyaltyApi } from '../../services/loyaltyApi';
import authSlice from '../../store/slices/authSlice';

// Mock the loyalty API
jest.mock('../../services/loyaltyApi');
const mockedLoyaltyApi = loyaltyApi as jest.Mocked<typeof loyaltyApi>;

// Mock store setup
const createMockStore = (isAuthenticated = true) => {
  return configureStore({
    reducer: {
      auth: authSlice,
    },
    preloadedState: {
      auth: {
        isAuthenticated,
        user: isAuthenticated ? {
          id: 1,
          email: 'test@example.com',
          firstName: 'John',
          lastName: 'Doe',
          loyaltyTier: 'silver' as const,
          loyaltyCredits: 1500,
          totalSpent: 7500,
        } : null,
        token: isAuthenticated ? 'mock-token' : null,
        isLoading: false,
        error: null,
      },
    },
  });
};

const mockBalanceData = {
  available_credits: 1500,
  total_earned: 2000,
  total_redeemed: 500,
  expiring_soon: 200,
  expiring_days: 30,
  current_tier: 'silver' as const,
  tier_benefits: {
    credits_multiplier: 1.2,
    bonus_rate: 0.02,
    free_shipping_threshold: 2000,
    early_access: false,
    priority_support: false,
  },
  next_tier: 'gold',
  progress_to_next_tier: 50,
  total_spent: 7500,
};

const mockTransactionsData = [
  {
    id: 1,
    userId: 1,
    transactionType: 'earned' as const,
    amount: 100,
    balanceAfter: 1500,
    description: 'Credits earned from order #12345',
    createdAt: '2024-01-15T10:00:00Z',
    expiresAt: '2025-01-15T10:00:00Z',
  },
  {
    id: 2,
    userId: 1,
    transactionType: 'redeemed' as const,
    amount: -50,
    balanceAfter: 1450,
    description: 'Credits redeemed during checkout',
    createdAt: '2024-01-10T15:30:00Z',
  },
];

describe('LoyaltyDashboard', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('renders login message for unauthenticated users', () => {
    const store = createMockStore(false);
    
    render(
      <Provider store={store}>
        <LoyaltyDashboard />
      </Provider>
    );

    expect(screen.getByText('Please log in to view your loyalty dashboard.')).toBeInTheDocument();
  });

  it('displays loading state initially', () => {
    const store = createMockStore(true);
    
    // Mock API calls to never resolve
    mockedLoyaltyApi.getBalance.mockImplementation(() => new Promise(() => {}));
    mockedLoyaltyApi.getTransactions.mockImplementation(() => new Promise(() => {}));

    render(
      <Provider store={store}>
        <LoyaltyDashboard />
      </Provider>
    );

    expect(document.querySelector('.animate-pulse')).toBeInTheDocument();
  });

  it('displays loyalty balance and transaction data', async () => {
    const store = createMockStore(true);
    
    mockedLoyaltyApi.getBalance.mockResolvedValue({
      success: true,
      data: mockBalanceData,
    });
    
    mockedLoyaltyApi.getTransactions.mockResolvedValue({
      data: mockTransactionsData,
      meta: {
        currentPage: 1,
        lastPage: 1,
        perPage: 10,
        total: 2,
        from: 1,
        to: 2,
      },
      links: {
        first: '',
        last: '',
      },
    });

    render(
      <Provider store={store}>
        <LoyaltyDashboard />
      </Provider>
    );

    await waitFor(() => {
      expect(screen.getByText('Diecast Credits Dashboard')).toBeInTheDocument();
      expect(screen.getAllByText('1500.00')[0]).toBeInTheDocument(); // Available credits
      expect(screen.getByText('2000.00')).toBeInTheDocument(); // Total earned
      expect(screen.getByText('₱7500.00')).toBeInTheDocument(); // Total spent
    });
  });

  it('shows expiring credits warning when applicable', async () => {
    const store = createMockStore(true);
    
    mockedLoyaltyApi.getBalance.mockResolvedValue({
      success: true,
      data: mockBalanceData,
    });
    
    mockedLoyaltyApi.getTransactions.mockResolvedValue({
      data: mockTransactionsData,
      meta: {
        currentPage: 1,
        lastPage: 1,
        perPage: 10,
        total: 2,
        from: 1,
        to: 2,
      },
      links: {
        first: '',
        last: '',
      },
    });

    render(
      <Provider store={store}>
        <LoyaltyDashboard />
      </Provider>
    );

    await waitFor(() => {
      expect(screen.getByText('Credits Expiring Soon')).toBeInTheDocument();
      expect(screen.getByText(/200.00 credits will expire in the next 30 days/)).toBeInTheDocument();
    });
  });

  it('switches between overview and transactions tabs', async () => {
    const store = createMockStore(true);
    
    mockedLoyaltyApi.getBalance.mockResolvedValue({
      success: true,
      data: mockBalanceData,
    });
    
    mockedLoyaltyApi.getTransactions.mockResolvedValue({
      data: mockTransactionsData,
      meta: {
        currentPage: 1,
        lastPage: 1,
        perPage: 10,
        total: 2,
        from: 1,
        to: 2,
      },
      links: {
        first: '',
        last: '',
      },
    });

    render(
      <Provider store={store}>
        <LoyaltyDashboard />
      </Provider>
    );

    await waitFor(() => {
      expect(screen.getByText('Available Credits')).toBeInTheDocument();
    });

    // Click on transactions tab
    fireEvent.click(screen.getByText('Transaction History'));

    await waitFor(() => {
      expect(screen.getByText('All Transactions')).toBeInTheDocument();
      expect(screen.getByText('Credits earned from order #12345')).toBeInTheDocument();
    });
  });

  it('filters transactions by type', async () => {
    const store = createMockStore(true);
    
    mockedLoyaltyApi.getBalance.mockResolvedValue({
      success: true,
      data: mockBalanceData,
    });
    
    mockedLoyaltyApi.getTransactions.mockResolvedValue({
      data: mockTransactionsData,
      meta: {
        currentPage: 1,
        lastPage: 1,
        perPage: 10,
        total: 2,
        from: 1,
        to: 2,
      },
      links: {
        first: '',
        last: '',
      },
    });

    render(
      <Provider store={store}>
        <LoyaltyDashboard />
      </Provider>
    );

    // Switch to transactions tab
    await waitFor(() => {
      fireEvent.click(screen.getByText('Transaction History'));
    });

    // Click on earned filter
    fireEvent.click(screen.getByText('Earned'));

    await waitFor(() => {
      expect(mockedLoyaltyApi.getTransactions).toHaveBeenCalledWith({
        per_page: 20,
        type: 'earned',
      });
    });
  });

  it('handles API errors gracefully', async () => {
    const store = createMockStore(true);
    
    mockedLoyaltyApi.getBalance.mockRejectedValue(new Error('API Error'));
    mockedLoyaltyApi.getTransactions.mockRejectedValue(new Error('API Error'));

    render(
      <Provider store={store}>
        <LoyaltyDashboard />
      </Provider>
    );

    await waitFor(() => {
      expect(screen.getByText('Failed to load loyalty data')).toBeInTheDocument();
      expect(screen.getByText('Retry')).toBeInTheDocument();
    });
  });

  it('retries loading data when retry button is clicked', async () => {
    const store = createMockStore(true);
    
    // First call fails
    mockedLoyaltyApi.getBalance.mockRejectedValueOnce(new Error('API Error'));
    mockedLoyaltyApi.getTransactions.mockRejectedValueOnce(new Error('API Error'));
    
    // Second call succeeds
    mockedLoyaltyApi.getBalance.mockResolvedValue({
      success: true,
      data: mockBalanceData,
    });
    
    mockedLoyaltyApi.getTransactions.mockResolvedValue({
      data: mockTransactionsData,
      meta: {
        currentPage: 1,
        lastPage: 1,
        perPage: 10,
        total: 2,
        from: 1,
        to: 2,
      },
      links: {
        first: '',
        last: '',
      },
    });

    render(
      <Provider store={store}>
        <LoyaltyDashboard />
      </Provider>
    );

    await waitFor(() => {
      expect(screen.getByText('Retry')).toBeInTheDocument();
    });

    fireEvent.click(screen.getByText('Retry'));

    await waitFor(() => {
      expect(screen.getByText('Diecast Credits Dashboard')).toBeInTheDocument();
    });
  });
});