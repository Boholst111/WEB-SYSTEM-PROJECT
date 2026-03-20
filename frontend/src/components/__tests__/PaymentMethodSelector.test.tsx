import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import PaymentMethodSelector from '../PaymentMethodSelector';
import { paymentApi } from '../../services/paymentApi';

// Mock the payment API
jest.mock('../../services/paymentApi');
const mockedPaymentApi = paymentApi as jest.Mocked<typeof paymentApi>;

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
  },
  {
    id: 'bank_transfer',
    name: 'Bank Transfer',
    type: 'bank_transfer' as const,
    isActive: true,
    config: {}
  }
];

describe('PaymentMethodSelector', () => {
  const defaultProps = {
    selectedMethod: '',
    onMethodSelect: jest.fn(),
    amount: 1000,
    disabled: false
  };

  beforeEach(() => {
    jest.clearAllMocks();
    mockedPaymentApi.getPaymentMethods.mockResolvedValue({
      success: true,
      payment_methods: mockPaymentMethods
    });
  });

  it('renders loading state initially', () => {
    render(<PaymentMethodSelector {...defaultProps} />);
    
    expect(screen.getByText('Payment Method')).toBeInTheDocument();
    // Check for loading animation elements
    expect(document.querySelector('.animate-pulse')).toBeInTheDocument();
  });

  it('renders payment methods after loading', async () => {
    render(<PaymentMethodSelector {...defaultProps} />);
    
    await waitFor(() => {
      expect(screen.getByText('GCash')).toBeInTheDocument();
      expect(screen.getByText('Maya')).toBeInTheDocument();
      expect(screen.getByText('Bank Transfer')).toBeInTheDocument();
    });
  });

  it('displays the correct amount in the header', async () => {
    render(<PaymentMethodSelector {...defaultProps} amount={2500} />);
    
    await waitFor(() => {
      expect(screen.getByText('(₱2,500.00)')).toBeInTheDocument();
    });
  });

  it('calls onMethodSelect when a payment method is selected', async () => {
    const onMethodSelect = jest.fn();
    render(<PaymentMethodSelector {...defaultProps} onMethodSelect={onMethodSelect} />);
    
    await waitFor(() => {
      expect(screen.getByText('GCash')).toBeInTheDocument();
    });

    fireEvent.click(screen.getByLabelText(/gcash/i));
    expect(onMethodSelect).toHaveBeenCalledWith('gcash');
  });

  it('shows selected method with proper styling', async () => {
    render(<PaymentMethodSelector {...defaultProps} selectedMethod="gcash" />);
    
    await waitFor(() => {
      const gcashOption = screen.getByText('GCash').closest('label');
      expect(gcashOption).toHaveClass('border-blue-500');
    });
  });

  it('disables interaction when disabled prop is true', async () => {
    const onMethodSelect = jest.fn();
    render(<PaymentMethodSelector {...defaultProps} onMethodSelect={onMethodSelect} disabled={true} />);
    
    await waitFor(() => {
      expect(screen.getByText('GCash')).toBeInTheDocument();
    });

    fireEvent.click(screen.getByText('GCash').closest('label')!);
    expect(onMethodSelect).not.toHaveBeenCalled();
  });

  it('handles API error gracefully', async () => {
    mockedPaymentApi.getPaymentMethods.mockRejectedValue(new Error('API Error'));
    
    render(<PaymentMethodSelector {...defaultProps} />);
    
    await waitFor(() => {
      expect(screen.getByText('Unable to load payment methods')).toBeInTheDocument();
      expect(screen.getByText('Failed to load payment methods')).toBeInTheDocument();
    });
  });

  it('filters out inactive payment methods', async () => {
    const methodsWithInactive = [
      ...mockPaymentMethods,
      {
        id: 'inactive_method',
        name: 'Inactive Method',
        type: 'gcash' as const,
        isActive: false,
        config: {}
      }
    ];

    mockedPaymentApi.getPaymentMethods.mockResolvedValue({
      success: true,
      payment_methods: methodsWithInactive
    });

    render(<PaymentMethodSelector {...defaultProps} />);
    
    await waitFor(() => {
      expect(screen.getByText('GCash')).toBeInTheDocument();
      expect(screen.queryByText('Inactive Method')).not.toBeInTheDocument();
    });
  });

  it('shows security notice', async () => {
    render(<PaymentMethodSelector {...defaultProps} />);
    
    await waitFor(() => {
      expect(screen.getByText('All payments are secured with SSL encryption')).toBeInTheDocument();
    });
  });

  it('shows empty state when no payment methods are available', async () => {
    mockedPaymentApi.getPaymentMethods.mockResolvedValue({
      success: true,
      payment_methods: []
    });

    render(<PaymentMethodSelector {...defaultProps} />);
    
    await waitFor(() => {
      expect(screen.getByText('No payment methods available')).toBeInTheDocument();
    });
  });
});