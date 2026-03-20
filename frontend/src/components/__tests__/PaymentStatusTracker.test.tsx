import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import PaymentStatusTracker from '../PaymentStatusTracker';
import { paymentApi } from '../../services/paymentApi';

// Mock the payment API
jest.mock('../../services/paymentApi');
const mockedPaymentApi = paymentApi as jest.Mocked<typeof paymentApi>;

// Mock timers
jest.useFakeTimers();

const mockPaymentStatus = {
  id: 'payment_123',
  status: 'pending' as const,
  amount: 1000,
  currency: 'PHP',
  paymentMethod: 'gcash',
  referenceNumber: 'REF123456',
  paymentUrl: 'https://gcash.com/pay/123',
  message: 'Payment is being processed',
  createdAt: '2024-01-01T10:00:00Z',
  updatedAt: '2024-01-01T10:00:00Z'
};

describe('PaymentStatusTracker', () => {
  const defaultProps = {
    paymentId: 'payment_123',
    onStatusChange: jest.fn(),
    autoRefresh: true,
    refreshInterval: 5000
  };

  beforeEach(() => {
    jest.clearAllMocks();
    mockedPaymentApi.getPaymentStatus.mockResolvedValue(mockPaymentStatus);
  });

  afterEach(() => {
    jest.clearAllTimers();
  });

  it('renders loading state initially', () => {
    render(<PaymentStatusTracker {...defaultProps} />);
    
    // Check for loading animation elements
    expect(document.querySelector('.animate-pulse')).toBeInTheDocument();
  });

  it('renders payment status after loading', async () => {
    render(<PaymentStatusTracker {...defaultProps} />);
    
    await waitFor(() => {
      expect(screen.getByText('Payment Pending')).toBeInTheDocument();
      expect(screen.getByText('Waiting for payment confirmation')).toBeInTheDocument();
      expect(screen.getByText('payment_123')).toBeInTheDocument();
      expect(screen.getByText('₱1,000.00')).toBeInTheDocument();
    });
  });

  it('displays different status states correctly', async () => {
    // Test completed status
    mockedPaymentApi.getPaymentStatus.mockResolvedValue({
      ...mockPaymentStatus,
      status: 'completed'
    });

    render(<PaymentStatusTracker {...defaultProps} />);
    
    await waitFor(() => {
      expect(screen.getByText('Payment Successful')).toBeInTheDocument();
      expect(screen.getByText('Your payment has been completed successfully')).toBeInTheDocument();
    });
  });

  it('displays failed status correctly', async () => {
    mockedPaymentApi.getPaymentStatus.mockResolvedValue({
      ...mockPaymentStatus,
      status: 'failed',
      message: 'Insufficient funds'
    });

    render(<PaymentStatusTracker {...defaultProps} />);
    
    await waitFor(() => {
      expect(screen.getByText('Payment Failed')).toBeInTheDocument();
      expect(screen.getByText('Your payment could not be processed')).toBeInTheDocument();
    });
  });

  it('calls onStatusChange when status changes', async () => {
    const onStatusChange = jest.fn();
    render(<PaymentStatusTracker {...defaultProps} onStatusChange={onStatusChange} />);
    
    await waitFor(() => {
      expect(onStatusChange).toHaveBeenCalledWith('pending');
    });
  });

  it('shows payment URL for pending payments', async () => {
    render(<PaymentStatusTracker {...defaultProps} />);
    
    await waitFor(() => {
      const completePaymentLink = screen.getByText('Complete Payment');
      expect(completePaymentLink).toBeInTheDocument();
      expect(completePaymentLink.closest('a')).toHaveAttribute('href', 'https://gcash.com/pay/123');
    });
  });

  it('auto-refreshes payment status', async () => {
    render(<PaymentStatusTracker {...defaultProps} />);
    
    await waitFor(() => {
      expect(mockedPaymentApi.getPaymentStatus).toHaveBeenCalledTimes(1);
    });

    // Fast-forward time to trigger auto-refresh
    jest.advanceTimersByTime(5000);
    
    // Wait a bit more for the async operation to complete
    await waitFor(() => {
      expect(mockedPaymentApi.getPaymentStatus).toHaveBeenCalledTimes(2);
    }, { timeout: 6000 });
  });

  it('stops auto-refresh for final states', async () => {
    mockedPaymentApi.getPaymentStatus.mockResolvedValue({
      ...mockPaymentStatus,
      status: 'completed'
    });

    render(<PaymentStatusTracker {...defaultProps} />);
    
    await waitFor(() => {
      expect(mockedPaymentApi.getPaymentStatus).toHaveBeenCalledTimes(1);
    });

    // Fast-forward time - should not trigger another call for completed status
    jest.advanceTimersByTime(10000);
    
    await waitFor(() => {
      expect(mockedPaymentApi.getPaymentStatus).toHaveBeenCalledTimes(1);
    });
  });

  it('handles manual refresh', async () => {
    render(<PaymentStatusTracker {...defaultProps} />);
    
    await waitFor(() => {
      expect(screen.getByText('Refresh')).toBeInTheDocument();
    });

    fireEvent.click(screen.getByText('Refresh'));
    
    await waitFor(() => {
      expect(mockedPaymentApi.getPaymentStatus).toHaveBeenCalledTimes(2);
    });
  });

  it('handles API errors gracefully', async () => {
    mockedPaymentApi.getPaymentStatus.mockRejectedValue(new Error('API Error'));
    
    render(<PaymentStatusTracker {...defaultProps} />);
    
    await waitFor(() => {
      expect(screen.getByText('Unable to load payment status')).toBeInTheDocument();
      expect(screen.getByText('Failed to fetch payment status')).toBeInTheDocument();
      expect(screen.getByText('Retry')).toBeInTheDocument();
    });
  });

  it('shows auto-refresh indicator for non-final states', async () => {
    render(<PaymentStatusTracker {...defaultProps} />);
    
    await waitFor(() => {
      expect(screen.getByText(/Auto-refreshing every 5 seconds/)).toBeInTheDocument();
    });
  });

  it('hides auto-refresh indicator for final states', async () => {
    mockedPaymentApi.getPaymentStatus.mockResolvedValue({
      ...mockPaymentStatus,
      status: 'completed'
    });

    render(<PaymentStatusTracker {...defaultProps} />);
    
    await waitFor(() => {
      expect(screen.queryByText(/Auto-refreshing/)).not.toBeInTheDocument();
    });
  });

  it('disables auto-refresh when autoRefresh prop is false', async () => {
    render(<PaymentStatusTracker {...defaultProps} autoRefresh={false} />);
    
    await waitFor(() => {
      expect(mockedPaymentApi.getPaymentStatus).toHaveBeenCalledTimes(1);
    });

    // Fast-forward time - should not trigger another call
    jest.advanceTimersByTime(10000);
    
    await waitFor(() => {
      expect(mockedPaymentApi.getPaymentStatus).toHaveBeenCalledTimes(1);
    });
  });

  it('formats currency correctly', async () => {
    mockedPaymentApi.getPaymentStatus.mockResolvedValue({
      ...mockPaymentStatus,
      amount: 2500.50
    });

    render(<PaymentStatusTracker {...defaultProps} />);
    
    await waitFor(() => {
      expect(screen.getByText('₱2,500.50')).toBeInTheDocument();
    });
  });

  it('formats dates correctly', async () => {
    render(<PaymentStatusTracker {...defaultProps} />);
    
    await waitFor(() => {
      // The date format will depend on the user's locale, so we'll check for the year
      expect(screen.getByText(/2024/)).toBeInTheDocument();
    });
  });
});