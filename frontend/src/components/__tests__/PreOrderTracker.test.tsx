import React from 'react';
import { render, screen } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import PreOrderTracker from '../PreOrderTracker';
import { PreOrder } from '../../types';

const renderWithRouter = (component: React.ReactElement) => {
  return render(
    <BrowserRouter>
      {component}
    </BrowserRouter>
  );
};

const mockPreOrder: PreOrder = {
  id: 1,
  productId: 1,
  userId: 1,
  quantity: 1,
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
    features: ['Opening doors'],
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
    updatedAt: '2024-01-15T10:00:00Z',
  },
  createdAt: '2024-01-01T00:00:00Z',
  updatedAt: '2024-01-15T10:00:00Z',
};

describe('PreOrderTracker', () => {
  it('renders progress header correctly', () => {
    renderWithRouter(<PreOrderTracker preorder={mockPreOrder} />);
    
    expect(screen.getByText('Pre-Order Progress')).toBeInTheDocument();
    expect(screen.getByText(/steps completed/)).toBeInTheDocument();
  });

  it('shows all tracking steps', () => {
    renderWithRouter(<PreOrderTracker preorder={mockPreOrder} />);
    
    expect(screen.getByText('Pre-order Created')).toBeInTheDocument();
    expect(screen.getByText('Deposit Payment')).toBeInTheDocument();
    expect(screen.getByText('Product Arrival')).toBeInTheDocument();
    expect(screen.getByText('Final Payment')).toBeInTheDocument();
    expect(screen.getByText('Order Shipped')).toBeInTheDocument();
  });

  it('shows completed status for pre-order creation', () => {
    renderWithRouter(<PreOrderTracker preorder={mockPreOrder} />);
    
    const createdStep = screen.getByText('Pre-order Created').closest('div');
    expect(createdStep).toBeInTheDocument();
    expect(screen.getByText('Your pre-order has been successfully created')).toBeInTheDocument();
  });

  it('shows completed status for paid deposit', () => {
    renderWithRouter(<PreOrderTracker preorder={mockPreOrder} />);
    
    expect(screen.getByText('Deposit payment received and confirmed')).toBeInTheDocument();
  });

  it('shows current status for product arrival when deposit is paid', () => {
    renderWithRouter(<PreOrderTracker preorder={mockPreOrder} />);
    
    expect(screen.getByText('Waiting for product to arrive from manufacturer')).toBeInTheDocument();
  });

  it('shows pending status for final payment when product hasnt arrived', () => {
    renderWithRouter(<PreOrderTracker preorder={mockPreOrder} />);
    
    expect(screen.getByText('Waiting for product arrival before final payment')).toBeInTheDocument();
  });

  it('calculates progress percentage correctly', () => {
    renderWithRouter(<PreOrderTracker preorder={mockPreOrder} />);
    
    // For deposit_paid status, 2 out of 5 steps should be completed (40%)
    expect(screen.getByText('2 of 5 steps completed')).toBeInTheDocument();
  });

  it('shows overdue status for overdue payments', () => {
    const overdueDate = new Date();
    overdueDate.setDate(overdueDate.getDate() - 5); // 5 days ago
    
    const overduePreOrder = {
      ...mockPreOrder,
      status: 'ready_for_payment' as const,
      fullPaymentDueDate: overdueDate.toISOString().split('T')[0],
      actualArrivalDate: '2024-02-10'
    };
    
    renderWithRouter(<PreOrderTracker preorder={overduePreOrder} />);
    
    expect(screen.getByText(/Payment is overdue/)).toBeInTheDocument();
  });

  it('shows action required for deposit pending', () => {
    const pendingPreOrder = {
      ...mockPreOrder,
      status: 'deposit_pending' as const,
      depositPaidAt: undefined
    };
    
    renderWithRouter(<PreOrderTracker preorder={pendingPreOrder} showDetails={true} />);
    
    expect(screen.getByText('Action Required:')).toBeInTheDocument();
    expect(screen.getByText(/Pay your deposit/)).toBeInTheDocument();
  });

  it('shows action required for ready for payment', () => {
    const readyPreOrder = {
      ...mockPreOrder,
      status: 'ready_for_payment' as const,
      actualArrivalDate: '2024-02-10'
    };
    
    renderWithRouter(<PreOrderTracker preorder={readyPreOrder} showDetails={true} />);
    
    // The component shows "Urgent:" for overdue payments, not "Action Required:"
    expect(screen.getByText('Urgent:')).toBeInTheDocument();
    expect(screen.getByText(/Complete your final payment/)).toBeInTheDocument();
  });

  it('shows waiting message for product arrival', () => {
    renderWithRouter(<PreOrderTracker preorder={mockPreOrder} showDetails={true} />);
    
    expect(screen.getByText(/We're waiting for your product to arrive/)).toBeInTheDocument();
  });

  it('displays summary cards when showDetails is true', () => {
    renderWithRouter(<PreOrderTracker preorder={mockPreOrder} showDetails={true} />);
    
    expect(screen.getByText('Deposit Status')).toBeInTheDocument();
    expect(screen.getByText('Remaining Payment')).toBeInTheDocument();
    expect(screen.getByText('Paid')).toBeInTheDocument();
    expect(screen.getByText('Pending')).toBeInTheDocument();
  });

  it('does not display summary cards when showDetails is false', () => {
    renderWithRouter(<PreOrderTracker preorder={mockPreOrder} showDetails={false} />);
    
    expect(screen.queryByText('Deposit Status')).not.toBeInTheDocument();
    expect(screen.queryByText('Remaining Payment')).not.toBeInTheDocument();
  });

  it('shows completed status for all steps when pre-order is completed', () => {
    const completedPreOrder = {
      ...mockPreOrder,
      status: 'completed' as const,
      actualArrivalDate: '2024-02-10'
    };
    
    renderWithRouter(<PreOrderTracker preorder={completedPreOrder} />);
    
    expect(screen.getByText('Final payment completed successfully')).toBeInTheDocument();
    expect(screen.getByText('Your order is being prepared for shipment')).toBeInTheDocument();
  });

  it('formats dates correctly', () => {
    renderWithRouter(<PreOrderTracker preorder={mockPreOrder} />);
    
    // Check for formatted dates (exact format may vary based on locale)
    expect(screen.getByText(/Jan 1, 2024/)).toBeInTheDocument(); // Created date
    expect(screen.getByText(/Jan 15, 2024/)).toBeInTheDocument(); // Deposit paid date
  });

  it('shows estimated dates for pending steps', () => {
    renderWithRouter(<PreOrderTracker preorder={mockPreOrder} />);
    
    expect(screen.getByText(/Est\. Feb 15, 2024/)).toBeInTheDocument(); // Estimated arrival
    expect(screen.getByText(/Est\. Mar 1, 2024/)).toBeInTheDocument(); // Payment due date
  });

  it('shows actual arrival date when product has arrived', () => {
    const arrivedPreOrder = {
      ...mockPreOrder,
      status: 'ready_for_payment' as const,
      actualArrivalDate: '2024-02-10'
    };
    
    renderWithRouter(<PreOrderTracker preorder={arrivedPreOrder} />);
    
    // The component shows the date in the timeline, check for the formatted date
    expect(screen.getByText('Feb 10, 2024')).toBeInTheDocument();
  });

  it('shows urgent status for overdue payments in summary', () => {
    const overdueDate = new Date();
    overdueDate.setDate(overdueDate.getDate() - 5);
    
    const overduePreOrder = {
      ...mockPreOrder,
      status: 'ready_for_payment' as const,
      fullPaymentDueDate: overdueDate.toISOString().split('T')[0],
      actualArrivalDate: '2024-02-10'
    };
    
    renderWithRouter(<PreOrderTracker preorder={overduePreOrder} showDetails={true} />);
    
    expect(screen.getByText('Overdue')).toBeInTheDocument();
  });
});