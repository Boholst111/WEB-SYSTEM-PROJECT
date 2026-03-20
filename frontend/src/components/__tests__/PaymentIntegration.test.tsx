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

const renderWithRouter = (component: React.ReactElement) => {
  return render(
    <BrowserRouter>
      {component}
    </BrowserRouter>
  );
};

describe('Payment Integration Tests', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockedPaymentApi.getPaymentMethods.mockResolvedValue({
      success: true,
      payment_methods: mockPaymentMethods
    });
  });

  it('completes a basic payment flow', async () => {
    renderWithRouter(
      <PaymentFlow
        amount={1000}
        orderId={123}
        onSuccess={jest.fn()}
        onCancel={jest.fn()}
      />
    );

    // Should start with method selection
    await waitFor(() => {
      expect(screen.getByText('GCash')).toBeInTheDocument();
      expect(screen.getByText('Maya')).toBeInTheDocument();
    });

    // Select GCash
    fireEvent.click(screen.getByText('GCash').closest('label')!);

    // Should navigate to payment form
    await waitFor(() => {
      expect(screen.getByText('GCash Payment')).toBeInTheDocument();
      expect(screen.getByPlaceholderText('09XX XXX XXXX')).toBeInTheDocument();
    });

    // Verify the form is functional
    expect(screen.getByText('Cancel')).toBeInTheDocument();
    expect(screen.getByText('Pay ₱1,000.00')).toBeInTheDocument();
  });

  it('handles back navigation correctly', async () => {
    renderWithRouter(
      <PaymentFlow
        amount={1000}
        orderId={123}
        onSuccess={jest.fn()}
        onCancel={jest.fn()}
      />
    );

    // Select a payment method
    await waitFor(() => {
      expect(screen.getByText('GCash')).toBeInTheDocument();
    });
    fireEvent.click(screen.getByText('GCash').closest('label')!);

    // Should be on payment form
    await waitFor(() => {
      expect(screen.getByText('GCash Payment')).toBeInTheDocument();
    });

    // Click back
    fireEvent.click(screen.getByText('← Back'));

    // Should be back to method selection
    await waitFor(() => {
      expect(screen.getByText('GCash')).toBeInTheDocument();
      expect(screen.getByText('Maya')).toBeInTheDocument();
    });
  });

  it('shows progress indicator correctly', async () => {
    renderWithRouter(
      <PaymentFlow
        amount={1000}
        orderId={123}
        onSuccess={jest.fn()}
        onCancel={jest.fn()}
      />
    );

    // Initial state - 33% progress
    await waitFor(() => {
      const progressBar = document.querySelector('.bg-blue-600');
      expect(progressBar).toHaveStyle('width: 33%');
    });

    // Select payment method
    await waitFor(() => {
      expect(screen.getByText('GCash')).toBeInTheDocument();
    });
    fireEvent.click(screen.getByText('GCash').closest('label')!);

    // Payment form state - 66% progress
    await waitFor(() => {
      const progressBar = document.querySelector('.bg-blue-600');
      expect(progressBar).toHaveStyle('width: 66%');
    });
  });

  it('displays correct amount throughout the flow', async () => {
    renderWithRouter(
      <PaymentFlow
        amount={2500}
        orderId={123}
        onSuccess={jest.fn()}
        onCancel={jest.fn()}
      />
    );

    // Check amount in method selection
    await waitFor(() => {
      expect(screen.getByText('(₱2,500.00)')).toBeInTheDocument();
    });

    // Select payment method
    fireEvent.click(screen.getByText('GCash').closest('label')!);

    // Check amount in payment form
    await waitFor(() => {
      expect(screen.getAllByText('₱2,500.00')).toHaveLength(3); // Header, summary, button
    });
  });
});