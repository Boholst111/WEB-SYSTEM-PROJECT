import React from 'react';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import { Provider } from 'react-redux';
import { configureStore } from '@reduxjs/toolkit';
import CreditsRedemption from '../CreditsRedemption';
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

describe('CreditsRedemption', () => {
  const mockOnRedemptionChange = jest.fn();
  const defaultProps = {
    orderTotal: 2000,
    onRedemptionChange: mockOnRedemptionChange,
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('does not render for unauthenticated users', () => {
    const store = createMockStore(false);
    
    render(
      <Provider store={store}>
        <CreditsRedemption {...defaultProps} />
      </Provider>
    );

    expect(screen.queryByText('Use Diecast Credits')).not.toBeInTheDocument();
  });

  it('displays loading state while fetching balance', () => {
    const store = createMockStore(true);
    
    // Mock API call to never resolve
    mockedLoyaltyApi.getBalance.mockImplementation(() => new Promise(() => {}));

    render(
      <Provider store={store}>
        <CreditsRedemption {...defaultProps} />
      </Provider>
    );

    expect(document.querySelector('.animate-pulse')).toBeInTheDocument();
  });

  it('shows unavailable message when credits are insufficient', async () => {
    const store = createMockStore(true);
    
    mockedLoyaltyApi.getBalance.mockResolvedValue({
      success: true,
      data: {
        ...mockBalanceData,
        available_credits: 50, // Below minimum
      },
    });

    render(
      <Provider store={store}>
        <CreditsRedemption {...defaultProps} />
      </Provider>
    );

    await waitFor(() => {
      expect(screen.getByText('Credits Redemption Not Available')).toBeInTheDocument();
      expect(screen.getByText('Minimum 100 credits required')).toBeInTheDocument();
    });
  });

  it('displays redemption interface when credits are sufficient', async () => {
    const store = createMockStore(true);
    
    mockedLoyaltyApi.getBalance.mockResolvedValue({
      success: true,
      data: mockBalanceData,
    });

    render(
      <Provider store={store}>
        <CreditsRedemption {...defaultProps} />
      </Provider>
    );

    await waitFor(() => {
      expect(screen.getByText('Use Diecast Credits')).toBeInTheDocument();
      expect(screen.getByText('Available Credits:')).toBeInTheDocument();
      expect(screen.getByText('1500.00')).toBeInTheDocument();
    });
  });

  it('enables redemption controls when checkbox is checked', async () => {
    const store = createMockStore(true);
    
    mockedLoyaltyApi.getBalance.mockResolvedValue({
      success: true,
      data: mockBalanceData,
    });

    render(
      <Provider store={store}>
        <CreditsRedemption {...defaultProps} />
      </Provider>
    );

    await waitFor(() => {
      const checkbox = screen.getByRole('checkbox');
      fireEvent.click(checkbox);
    });

    expect(screen.getByText('Credits to Redeem')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('Min: 100')).toBeInTheDocument();
  });

  it('calculates redemption correctly when credits are entered', async () => {
    const store = createMockStore(true);
    
    mockedLoyaltyApi.getBalance.mockResolvedValue({
      success: true,
      data: mockBalanceData,
    });

    render(
      <Provider store={store}>
        <CreditsRedemption {...defaultProps} />
      </Provider>
    );

    await waitFor(() => {
      const checkbox = screen.getByRole('checkbox');
      fireEvent.click(checkbox);
    });

    const input = screen.getByPlaceholderText('Min: 100');
    fireEvent.change(input, { target: { value: '500' } });

    await waitFor(() => {
      expect(screen.getByText('Redemption Summary')).toBeInTheDocument();
      expect(screen.getByText('500.00')).toBeInTheDocument(); // Credits used
      expect(screen.getByText('-₱500.00')).toBeInTheDocument(); // Discount amount
      expect(screen.getByText('1000.00')).toBeInTheDocument(); // Remaining credits
    });

    expect(mockOnRedemptionChange).toHaveBeenCalledWith({
      creditsUsed: 500,
      discountAmount: 500,
      remainingCredits: 1000,
    });
  });

  it('respects maximum redemption limit (50% of order total)', async () => {
    const store = createMockStore(true);
    
    mockedLoyaltyApi.getBalance.mockResolvedValue({
      success: true,
      data: mockBalanceData,
    });

    render(
      <Provider store={store}>
        <CreditsRedemption {...defaultProps} />
      </Provider>
    );

    await waitFor(() => {
      const checkbox = screen.getByRole('checkbox');
      fireEvent.click(checkbox);
    });

    // Try to enter more than 50% of order total (2000 * 0.5 = 1000)
    const input = screen.getByPlaceholderText('Min: 100');
    fireEvent.change(input, { target: { value: '1200' } });

    await waitFor(() => {
      // Should be clamped to maximum allowed (1000)
      expect(mockOnRedemptionChange).toHaveBeenCalledWith({
        creditsUsed: 1000,
        discountAmount: 1000,
        remainingCredits: 500,
      });
    });
  });

  it('uses max credits when Max button is clicked', async () => {
    const store = createMockStore(true);
    
    mockedLoyaltyApi.getBalance.mockResolvedValue({
      success: true,
      data: mockBalanceData,
    });

    render(
      <Provider store={store}>
        <CreditsRedemption {...defaultProps} />
      </Provider>
    );

    await waitFor(() => {
      const checkbox = screen.getByRole('checkbox');
      fireEvent.click(checkbox);
    });

    const maxButton = screen.getByText('Max');
    fireEvent.click(maxButton);

    await waitFor(() => {
      // Should use maximum allowed (50% of 2000 = 1000)
      expect(mockOnRedemptionChange).toHaveBeenCalledWith({
        creditsUsed: 1000,
        discountAmount: 1000,
        remainingCredits: 500,
      });
    });
  });

  it('shows expiring credits warning', async () => {
    const store = createMockStore(true);
    
    mockedLoyaltyApi.getBalance.mockResolvedValue({
      success: true,
      data: mockBalanceData,
    });

    render(
      <Provider store={store}>
        <CreditsRedemption {...defaultProps} />
      </Provider>
    );

    await waitFor(() => {
      const checkbox = screen.getByRole('checkbox');
      fireEvent.click(checkbox);
    });

    expect(screen.getByText('200.00 credits expire in 30 days')).toBeInTheDocument();
  });

  it('clears redemption when checkbox is unchecked', async () => {
    const store = createMockStore(true);
    
    mockedLoyaltyApi.getBalance.mockResolvedValue({
      success: true,
      data: mockBalanceData,
    });

    render(
      <Provider store={store}>
        <CreditsRedemption {...defaultProps} />
      </Provider>
    );

    await waitFor(() => {
      const checkbox = screen.getByRole('checkbox');
      fireEvent.click(checkbox);
    });

    const input = screen.getByPlaceholderText('Min: 100');
    fireEvent.change(input, { target: { value: '500' } });

    await waitFor(() => {
      expect(mockOnRedemptionChange).toHaveBeenCalledWith({
        creditsUsed: 500,
        discountAmount: 500,
        remainingCredits: 1000,
      });
    });

    // Uncheck the checkbox
    const checkbox = screen.getByRole('checkbox');
    fireEvent.click(checkbox);

    expect(mockOnRedemptionChange).toHaveBeenCalledWith(null);
  });

  it('handles API errors gracefully', async () => {
    const store = createMockStore(true);
    
    mockedLoyaltyApi.getBalance.mockRejectedValue(new Error('API Error'));

    render(
      <Provider store={store}>
        <CreditsRedemption {...defaultProps} />
      </Provider>
    );

    await waitFor(() => {
      expect(screen.getByText('Failed to load credits balance')).toBeInTheDocument();
    });
  });

  it('disables controls when disabled prop is true', async () => {
    const store = createMockStore(true);
    
    mockedLoyaltyApi.getBalance.mockResolvedValue({
      success: true,
      data: mockBalanceData,
    });

    render(
      <Provider store={store}>
        <CreditsRedemption {...defaultProps} disabled />
      </Provider>
    );

    await waitFor(() => {
      const checkbox = screen.getByRole('checkbox');
      expect(checkbox).toBeDisabled();
    });
  });
});