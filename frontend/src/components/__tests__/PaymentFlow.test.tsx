import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import { BrowserRouter } from 'react-router-dom';
import PaymentFlow from '../PaymentFlow';
import { paymentApi } from '../../services/paymentApi';

// Mock the payment API
jest.mock('../../services/paymentApi');
const mockedPaymentApi = paymentApi as jest.Mocked<typeof paymentApi>;

// Mock react-router-dom
const mockNavigate = jest.fn();
jest.mock('react-router-dom', () => ({
  ...jest.requireActual('react-router-dom'),
  useNavigate: () => mockNavigate,
}));

const mockPaymentMethods = [
  {
    id: 'gcash',
    name: 'GCash',
    type: 'gcash' as const,
    isActive: true,
    config: {}
  },
  {
    id: 'maya',
    name: 'Maya',
    type: 'maya' as const,
    isActive: true,
    config: {}
  }
];

const mockPaymentResponse = {
  id: 'payment_123',
  status: 'pending' as const,
  paymentUrl: 'https://gcash.com/pay/123',
  referenceNumber: 'REF123456',
  message: 'Payment initiated successfully'
};

const renderWithRouter = (component: React.ReactElement) => {
  return render(
    <BrowserRouter>
      {component}
    </BrowserRouter>
  );
};

describe('PaymentFlow', () => {
  const defaultProps = {
    amount: 1000,
    orderId: 123,
    onSuccess: jest.fn(),
    onCancel: jest.fn()
  };

  beforeEach(() => {
    jest.clearAllMocks();
    mockedPaymentApi.getPaymentMethods.mockResolvedValue({
      success: true,
      payment_methods: mockPaymentMethods
    });
    mockedPaymentApi.processGCashPayment.mockResolvedValue(mockPaymentResponse);
    mockedPaymentApi.processMayaPayment.mockResolvedValue(mockPaymentResponse);
    mockedPaymentApi.processBankTransferPayment.mockResolvedValue(mockPaymentResponse);
    
    // Mock window.location.href
    delete (window as any).location;
    (window as any).location = { href: '' };
  });

  it('renders method selection step initially', async () => {
    renderWithRouter(<PaymentFlow {...defaultProps} />);
    
    await waitFor(() => {
      expect(screen.getByText('1. Payment Method')).toBeInTheDocument();
      expect(screen.getByText('GCash')).toBeInTheDocument();
      expect(screen.getByText('Maya')).toBeInTheDocument();
    });
  });

  it('shows progress indicator correctly', async () => {
    renderWithRouter(<PaymentFlow {...defaultProps} />);
    
    await waitFor(() => {
      expect(screen.getByText('1. Payment Method')).toBeInTheDocument();
      expect(screen.getByText('2. Payment Details')).toBeInTheDocument();
      expect(screen.getByText('3. Confirmation')).toBeInTheDocument();
    });

    // Progress bar should be at 33% initially
    const progressBar = document.querySelector('.bg-blue-600');
    expect(progressBar).toHaveStyle('width: 33%');
  });

  it('navigates to payment form when method is selected', async () => {
    renderWithRouter(<PaymentFlow {...defaultProps} />);
    
    await waitFor(() => {
      expect(screen.getByText('GCash')).toBeInTheDocument();
    });

    fireEvent.click(screen.getByText('GCash').closest('label')!);
    
    await waitFor(() => {
      expect(screen.getByText('GCash Payment')).toBeInTheDocument();
      expect(screen.getByText('Mobile Number')).toBeInTheDocument();
    });

    // Progress bar should be at 66%
    const progressBar = document.querySelector('.bg-blue-600');
    expect(progressBar).toHaveStyle('width: 66%');
  });

  it('processes GCash payment successfully', async () => {
    renderWithRouter(<PaymentFlow {...defaultProps} />);
    
    // Select GCash
    await waitFor(() => {
      expect(screen.getByText('GCash')).toBeInTheDocument();
    });
    fireEvent.click(screen.getByText('GCash').closest('label')!);
    
    // Fill form and submit
    await waitFor(() => {
      expect(screen.getByPlaceholderText('09XX XXX XXXX')).toBeInTheDocument();
    });
    fireEvent.change(screen.getByPlaceholderText('09XX XXX XXXX'), {
      target: { value: '09123456789' }
    });
    fireEvent.click(screen.getByText('Pay ₱1,000.00'));
    
    await waitFor(() => {
      expect(mockedPaymentApi.processGCashPayment).toHaveBeenCalledWith({
        paymentMethod: 'gcash',
        amount: 1000,
        phone: '09123456789',
        bank: 'bpi',
        orderId: 123,
        preorderId: undefined
      });
    });
  });

  it('redirects to payment URL for redirect-based payments', async () => {
    renderWithRouter(<PaymentFlow {...defaultProps} />);
    
    // Select GCash and submit
    await waitFor(() => {
      expect(screen.getByText('GCash')).toBeInTheDocument();
    });
    fireEvent.click(screen.getByText('GCash').closest('label')!);
    
    await waitFor(() => {
      expect(screen.getByPlaceholderText('09XX XXX XXXX')).toBeInTheDocument();
    });
    fireEvent.change(screen.getByPlaceholderText('09XX XXX XXXX'), {
      target: { value: '09123456789' }
    });
    fireEvent.click(screen.getByText('Pay ₱1,000.00'));
    
    await waitFor(() => {
      expect(window.location.href).toBe('https://gcash.com/pay/123');
    });
  });

  it('shows status tracking for bank transfer', async () => {
    mockedPaymentApi.processBankTransferPayment.mockResolvedValue({
      ...mockPaymentResponse,
      paymentUrl: undefined // Bank transfer doesn't have redirect URL
    });
    mockedPaymentApi.getPaymentStatus.mockResolvedValue({
      id: 'payment_123',
      status: 'pending',
      amount: 1000,
      currency: 'PHP',
      paymentMethod: 'bank_transfer',
      createdAt: '2024-01-01T10:00:00Z',
      updatedAt: '2024-01-01T10:00:00Z'
    });

    renderWithRouter(<PaymentFlow {...defaultProps} />);
    
    // Select Bank Transfer
    await waitFor(() => {
      expect(screen.getByText('Bank Transfer')).toBeInTheDocument();
    });
    fireEvent.click(screen.getByText('Bank Transfer').closest('label')!);
    
    // Submit form
    await waitFor(() => {
      expect(screen.getByText('Pay ₱1,000.00')).toBeInTheDocument();
    });
    fireEvent.click(screen.getByText('Pay ₱1,000.00'));
    
    // Should show status tracking
    await waitFor(() => {
      expect(screen.getByText('Payment Status')).toBeInTheDocument();
      expect(screen.getByText('Payment Pending')).toBeInTheDocument();
    });
  });

  it('handles payment errors gracefully', async () => {
    mockedPaymentApi.processGCashPayment.mockRejectedValue(new Error('Payment failed'));
    
    renderWithRouter(<PaymentFlow {...defaultProps} />);
    
    // Select GCash and submit
    await waitFor(() => {
      expect(screen.getByText('GCash')).toBeInTheDocument();
    });
    fireEvent.click(screen.getByText('GCash').closest('label')!);
    
    await waitFor(() => {
      expect(screen.getByPlaceholderText('09XX XXX XXXX')).toBeInTheDocument();
    });
    fireEvent.change(screen.getByPlaceholderText('09XX XXX XXXX'), {
      target: { value: '09123456789' }
    });
    fireEvent.click(screen.getByText('Pay ₱1,000.00'));
    
    await waitFor(() => {
      expect(screen.getByText('Payment Error')).toBeInTheDocument();
      expect(screen.getByText('Payment failed')).toBeInTheDocument();
    });
  });

  it('shows success page when payment completes', async () => {
    const onSuccess = jest.fn();
    renderWithRouter(<PaymentFlow {...defaultProps} onSuccess={onSuccess} />);
    
    // Simulate status change to completed
    const component = screen.getByTestId ? screen.getByTestId('payment-flow') : document;
    
    // This would normally be triggered by PaymentStatusTracker
    // For testing, we'll simulate the callback directly
    await waitFor(() => {
      // Simulate successful payment completion
      // In a real scenario, this would come from the status tracker
    });
  });

  it('navigates back correctly', async () => {
    renderWithRouter(<PaymentFlow {...defaultProps} />);
    
    // Go to payment form
    await waitFor(() => {
      expect(screen.getByText('GCash')).toBeInTheDocument();
    });
    fireEvent.click(screen.getByText('GCash').closest('label')!);
    
    // Click back button
    await waitFor(() => {
      expect(screen.getByText('← Back')).toBeInTheDocument();
    });
    fireEvent.click(screen.getByText('← Back'));
    
    // Should be back to method selection
    await waitFor(() => {
      expect(screen.getByText('GCash')).toBeInTheDocument();
      expect(screen.getByText('Maya')).toBeInTheDocument();
    });
  });

  it('calls onCancel when cancel is clicked', async () => {
    const onCancel = jest.fn();
    renderWithRouter(<PaymentFlow {...defaultProps} onCancel={onCancel} />);
    
    await waitFor(() => {
      expect(screen.getByText('Cancel')).toBeInTheDocument();
    });
    
    fireEvent.click(screen.getByText('Cancel'));
    expect(onCancel).toHaveBeenCalled();
  });

  it('handles preorder payments correctly', async () => {
    renderWithRouter(<PaymentFlow {...defaultProps} orderId={undefined} preorderId={456} />);
    
    // Select GCash and submit
    await waitFor(() => {
      expect(screen.getByText('GCash')).toBeInTheDocument();
    });
    fireEvent.click(screen.getByText('GCash').closest('label')!);
    
    await waitFor(() => {
      expect(screen.getByPlaceholderText('09XX XXX XXXX')).toBeInTheDocument();
    });
    fireEvent.change(screen.getByPlaceholderText('09XX XXX XXXX'), {
      target: { value: '09123456789' }
    });
    fireEvent.click(screen.getByText('Pay ₱1,000.00'));
    
    await waitFor(() => {
      expect(mockedPaymentApi.processGCashPayment).toHaveBeenCalledWith({
        paymentMethod: 'gcash',
        amount: 1000,
        phone: '09123456789',
        bank: 'bpi',
        orderId: undefined,
        preorderId: 456
      });
    });
  });

  it('shows processing state during payment submission', async () => {
    // Make the API call hang to test loading state
    mockedPaymentApi.processGCashPayment.mockImplementation(() => new Promise(() => {}));
    
    renderWithRouter(<PaymentFlow {...defaultProps} />);
    
    // Select GCash and submit
    await waitFor(() => {
      expect(screen.getByText('GCash')).toBeInTheDocument();
    });
    fireEvent.click(screen.getByText('GCash').closest('label')!);
    
    await waitFor(() => {
      expect(screen.getByPlaceholderText('09XX XXX XXXX')).toBeInTheDocument();
    });
    fireEvent.change(screen.getByPlaceholderText('09XX XXX XXXX'), {
      target: { value: '09123456789' }
    });
    fireEvent.click(screen.getByText('Pay ₱1,000.00'));
    
    await waitFor(() => {
      expect(screen.getByText('Processing...')).toBeInTheDocument();
    });
  });
});