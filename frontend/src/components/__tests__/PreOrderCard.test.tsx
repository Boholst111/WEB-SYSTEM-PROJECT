import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { Provider } from 'react-redux';
import { BrowserRouter } from 'react-router-dom';
import { configureStore } from '@reduxjs/toolkit';
import PreOrderCard from '../PreOrderCard';
import { PreOrder } from '../../types';
import preorderSlice from '../../store/slices/preorderSlice';

// Mock store
const createMockStore = () => {
  return configureStore({
    reducer: {
      preorders: preorderSlice,
    },
    preloadedState: {
      preorders: {
        preorders: [],
        currentPreOrder: null,
        isLoading: false,
        isCreating: false,
        isProcessingPayment: false,
        error: null,
        pagination: {
          currentPage: 1,
          lastPage: 1,
          perPage: 10,
          total: 0,
        },
        filters: {},
      },
    },
  });
};

const renderWithProviders = (component: React.ReactElement) => {
  const store = createMockStore();
  return render(
    <Provider store={store}>
      <BrowserRouter>
        {component}
      </BrowserRouter>
    </Provider>
  );
};

const mockPreOrder: PreOrder = {
  id: 1,
  productId: 1,
  userId: 1,
  quantity: 2,
  depositAmount: 500,
  remainingAmount: 1000,
  depositPaidAt: '2024-01-15T10:00:00Z',
  fullPaymentDueDate: '2024-03-01',
  status: 'deposit_paid',
  estimatedArrivalDate: '2024-02-15',
  actualArrivalDate: undefined,
  notes: '',
  product: {
    id: 1,
    sku: 'HW-001',
    name: 'Hot Wheels Corvette 1:64',
    description: 'Classic red Corvette die-cast model',
    brandId: 1,
    categoryId: 1,
    scale: '1:64',
    material: 'Die-cast',
    features: ['Opening doors', 'Detailed interior'],
    isChaseVariant: false,
    basePrice: 1500,
    currentPrice: 1500,
    stockQuantity: 0,
    isPreorder: true,
    preorderDate: '2024-02-15',
    status: 'active',
    images: ['https://example.com/corvette.jpg'],
    specifications: {},
    createdAt: '2024-01-01T00:00:00Z',
    updatedAt: '2024-01-01T00:00:00Z',
  },
  createdAt: '2024-01-01T00:00:00Z',
  updatedAt: '2024-01-15T10:00:00Z',
};

describe('PreOrderCard', () => {
  it('renders pre-order information correctly', () => {
    renderWithProviders(<PreOrderCard preorder={mockPreOrder} />);
    
    expect(screen.getByText('Hot Wheels Corvette 1:64')).toBeInTheDocument();
    expect(screen.getByText('Quantity:')).toBeInTheDocument();
    expect(screen.getByText('2')).toBeInTheDocument();
    expect(screen.getByText('Scale:')).toBeInTheDocument();
    expect(screen.getByText('1:64')).toBeInTheDocument();
  });

  it('displays correct status for deposit paid pre-order', () => {
    renderWithProviders(<PreOrderCard preorder={mockPreOrder} />);
    
    expect(screen.getByText('Deposit Paid')).toBeInTheDocument();
    expect(screen.getByText('Waiting for product arrival')).toBeInTheDocument();
  });

  it('shows deposit amount with checkmark when paid', () => {
    renderWithProviders(<PreOrderCard preorder={mockPreOrder} />);
    
    expect(screen.getByText('₱500.00')).toBeInTheDocument();
    // Check for checkmark icon (using test id or aria-label would be better)
    const depositSection = screen.getByText('Deposit Amount').closest('div');
    expect(depositSection).toBeInTheDocument();
  });

  it('displays remaining amount correctly', () => {
    renderWithProviders(<PreOrderCard preorder={mockPreOrder} />);
    
    expect(screen.getByText('₱1,000.00')).toBeInTheDocument();
  });

  it('shows estimated arrival date', () => {
    renderWithProviders(<PreOrderCard preorder={mockPreOrder} />);
    
    expect(screen.getByText(/Est\. Arrival:/)).toBeInTheDocument();
    expect(screen.getByText(/Feb 15, 2024/)).toBeInTheDocument();
  });

  it('shows payment due date', () => {
    renderWithProviders(<PreOrderCard preorder={mockPreOrder} />);
    
    expect(screen.getByText(/Due:/)).toBeInTheDocument();
    expect(screen.getByText(/Mar 1, 2024/)).toBeInTheDocument();
  });

  it('calls onPayDeposit when pay deposit button is clicked', () => {
    const mockOnPayDeposit = jest.fn();
    const pendingPreOrder = { ...mockPreOrder, status: 'deposit_pending' as const, depositPaidAt: undefined };
    
    renderWithProviders(
      <PreOrderCard preorder={pendingPreOrder} onPayDeposit={mockOnPayDeposit} />
    );
    
    const payDepositButton = screen.getByText('Pay Deposit');
    fireEvent.click(payDepositButton);
    
    expect(mockOnPayDeposit).toHaveBeenCalledWith(1);
  });

  it('calls onCompletePayment when complete payment button is clicked', () => {
    const mockOnCompletePayment = jest.fn();
    const readyPreOrder = { ...mockPreOrder, status: 'ready_for_payment' as const };
    
    renderWithProviders(
      <PreOrderCard preorder={readyPreOrder} onCompletePayment={mockOnCompletePayment} />
    );
    
    const completePaymentButton = screen.getByText('Complete Payment');
    fireEvent.click(completePaymentButton);
    
    expect(mockOnCompletePayment).toHaveBeenCalledWith(1);
  });

  it('calls onCancel when cancel button is clicked', () => {
    const mockOnCancel = jest.fn();
    
    renderWithProviders(
      <PreOrderCard preorder={mockPreOrder} onCancel={mockOnCancel} />
    );
    
    const cancelButton = screen.getByText('Cancel');
    fireEvent.click(cancelButton);
    
    expect(mockOnCancel).toHaveBeenCalledWith(1);
  });

  it('shows overdue status for overdue payments', () => {
    const overdueDate = new Date();
    overdueDate.setDate(overdueDate.getDate() - 5); // 5 days ago
    
    const overduePreOrder = {
      ...mockPreOrder,
      status: 'ready_for_payment' as const,
      fullPaymentDueDate: overdueDate.toISOString().split('T')[0]
    };
    
    renderWithProviders(<PreOrderCard preorder={overduePreOrder} />);
    
    expect(screen.getByText(/overdue/i)).toBeInTheDocument();
  });

  it('shows due soon status for payments due within 7 days', () => {
    const soonDate = new Date();
    soonDate.setDate(soonDate.getDate() + 3); // 3 days from now
    
    const dueSoonPreOrder = {
      ...mockPreOrder,
      status: 'ready_for_payment' as const,
      fullPaymentDueDate: soonDate.toISOString().split('T')[0]
    };
    
    renderWithProviders(<PreOrderCard preorder={dueSoonPreOrder} />);
    
    expect(screen.getByText(/3 days left/)).toBeInTheDocument();
  });

  it('renders view details link correctly', () => {
    renderWithProviders(<PreOrderCard preorder={mockPreOrder} />);
    
    const viewDetailsLink = screen.getByText('View Details');
    expect(viewDetailsLink).toBeInTheDocument();
    expect(viewDetailsLink.closest('a')).toHaveAttribute('href', '/preorders/1');
  });

  it('shows completed status correctly', () => {
    const completedPreOrder = { ...mockPreOrder, status: 'completed' as const };
    
    renderWithProviders(<PreOrderCard preorder={completedPreOrder} />);
    
    expect(screen.getByText('Completed')).toBeInTheDocument();
    expect(screen.getByText('Payment completed')).toBeInTheDocument();
  });

  it('shows cancelled status correctly', () => {
    const cancelledPreOrder = { ...mockPreOrder, status: 'cancelled' as const };
    
    renderWithProviders(<PreOrderCard preorder={cancelledPreOrder} />);
    
    expect(screen.getByText('Cancelled')).toBeInTheDocument();
    expect(screen.getByText('Pre-order cancelled')).toBeInTheDocument();
  });

  it('does not show cancel button for completed pre-orders', () => {
    const completedPreOrder = { ...mockPreOrder, status: 'completed' as const };
    const mockOnCancel = jest.fn();
    
    renderWithProviders(
      <PreOrderCard preorder={completedPreOrder} onCancel={mockOnCancel} />
    );
    
    expect(screen.queryByText('Cancel')).not.toBeInTheDocument();
  });

  it('formats currency correctly', () => {
    const preOrderWithLargeAmount = {
      ...mockPreOrder,
      depositAmount: 12345.67,
      remainingAmount: 98765.43
    };
    
    renderWithProviders(<PreOrderCard preorder={preOrderWithLargeAmount} />);
    
    expect(screen.getByText('₱12,345.67')).toBeInTheDocument();
    expect(screen.getByText('₱98,765.43')).toBeInTheDocument();
  });
});