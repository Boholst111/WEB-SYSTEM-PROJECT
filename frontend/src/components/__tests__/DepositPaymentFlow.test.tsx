import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { Provider } from 'react-redux';
import { configureStore } from '@reduxjs/toolkit';
import DepositPaymentFlow from '../DepositPaymentFlow';
import { PreOrder } from '../../types';
import preorderSlice from '../../store/slices/preorderSlice';

// Mock the dispatch function
const mockDispatch = jest.fn();
jest.mock('../../store', () => ({
  useAppDispatch: () => mockDispatch,
  useAppSelector: (selector: any) => selector({
    preorders: {
      isProcessingPayment: false,
      error: null
    }
  })
}));

const mockPreOrder: PreOrder = {
  id: 1,
  productId: 1,
  userId: 1,
  quantity: 1,
  depositAmount: 500,
  remainingAmount: 1000,
  depositPaidAt: undefined,
  fullPaymentDueDate: '2024-03-01',
  status: 'deposit_pending',
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
    updatedAt: '2024-01-01T00:00:00Z',
  },
  createdAt: '2024-01-01T00:00:00Z',
  updatedAt: '2024-01-01T00:00:00Z',
};

describe('DepositPaymentFlow', () => {
  beforeEach(() => {
    mockDispatch.mockClear();
  });

  it('renders payment method selection initially', () => {
    render(<DepositPaymentFlow preorder={mockPreOrder} />);
    
    expect(screen.getByText('Choose Payment Method')).toBeInTheDocument();
    expect(screen.getByText(/Pay your deposit of ₱500.00/)).toBeInTheDocument();
  });

  it('shows all payment method options', () => {
    render(<DepositPaymentFlow preorder={mockPreOrder} />);
    
    expect(screen.getByText('GCash')).toBeInTheDocument();
    expect(screen.getByText('Maya (PayMaya)')).toBeInTheDocument();
    expect(screen.getByText('Bank Transfer')).toBeInTheDocument();
  });

  it('shows popular badges for GCash and Maya', () => {
    render(<DepositPaymentFlow preorder={mockPreOrder} />);
    
    const popularBadges = screen.getAllByText('Popular');
    expect(popularBadges).toHaveLength(2);
  });

  it('navigates to details step when payment method is selected', () => {
    render(<DepositPaymentFlow preorder={mockPreOrder} />);
    
    const gcashButton = screen.getByText('GCash').closest('button');
    fireEvent.click(gcashButton!);
    
    expect(screen.getByText('GCash Payment')).toBeInTheDocument();
    expect(screen.getByText(/Enter your payment details/)).toBeInTheDocument();
  });

  it('shows mobile number input for GCash payment', () => {
    render(<DepositPaymentFlow preorder={mockPreOrder} />);
    
    const gcashButton = screen.getByText('GCash').closest('button');
    fireEvent.click(gcashButton!);
    
    expect(screen.getByLabelText('Mobile Number')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('09123456789')).toBeInTheDocument();
  });

  it('shows mobile number input for Maya payment', () => {
    render(<DepositPaymentFlow preorder={mockPreOrder} />);
    
    const mayaButton = screen.getByText('Maya (PayMaya)').closest('button');
    fireEvent.click(mayaButton!);
    
    expect(screen.getByLabelText('Mobile Number')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('09123456789')).toBeInTheDocument();
  });

  it('shows bank details inputs for bank transfer', () => {
    render(<DepositPaymentFlow preorder={mockPreOrder} />);
    
    const bankButton = screen.getByText('Bank Transfer').closest('button');
    fireEvent.click(bankButton!);
    
    expect(screen.getByLabelText('Bank Name')).toBeInTheDocument();
    expect(screen.getByLabelText('Account Number')).toBeInTheDocument();
    expect(screen.getByLabelText('Account Name')).toBeInTheDocument();
  });

  it('validates required fields before proceeding', () => {
    render(<DepositPaymentFlow preorder={mockPreOrder} />);
    
    const gcashButton = screen.getByText('GCash').closest('button');
    fireEvent.click(gcashButton!);
    
    const continueButton = screen.getByText('Continue');
    fireEvent.click(continueButton);
    
    // Form should not proceed without required mobile number
    expect(screen.getByText('GCash Payment')).toBeInTheDocument();
  });

  it('proceeds to confirmation step with valid form data', () => {
    render(<DepositPaymentFlow preorder={mockPreOrder} />);
    
    const gcashButton = screen.getByText('GCash').closest('button');
    fireEvent.click(gcashButton!);
    
    const mobileInput = screen.getByPlaceholderText('09123456789');
    fireEvent.change(mobileInput, { target: { value: '09123456789' } });
    
    const continueButton = screen.getByText('Continue');
    fireEvent.click(continueButton);
    
    expect(screen.getByText('Confirm Payment')).toBeInTheDocument();
  });

  it('shows order summary in confirmation step', () => {
    render(<DepositPaymentFlow preorder={mockPreOrder} />);
    
    // Navigate to confirmation
    const gcashButton = screen.getByText('GCash').closest('button');
    fireEvent.click(gcashButton!);
    
    const mobileInput = screen.getByPlaceholderText('09123456789');
    fireEvent.change(mobileInput, { target: { value: '09123456789' } });
    
    const continueButton = screen.getByText('Continue');
    fireEvent.click(continueButton);
    
    expect(screen.getByText('Order Summary')).toBeInTheDocument();
    expect(screen.getByText('Hot Wheels Corvette 1:64')).toBeInTheDocument();
    expect(screen.getByText('₱500.00')).toBeInTheDocument(); // Deposit amount
  });

  it('shows payment method details in confirmation', () => {
    render(<DepositPaymentFlow preorder={mockPreOrder} />);
    
    // Navigate to confirmation
    const gcashButton = screen.getByText('GCash').closest('button');
    fireEvent.click(gcashButton!);
    
    const mobileInput = screen.getByPlaceholderText('09123456789');
    fireEvent.change(mobileInput, { target: { value: '09123456789' } });
    
    const continueButton = screen.getByText('Continue');
    fireEvent.click(continueButton);
    
    expect(screen.getByText('Payment Method')).toBeInTheDocument();
    expect(screen.getByText('GCash')).toBeInTheDocument();
    expect(screen.getByText('09123456789')).toBeInTheDocument();
  });

  it('shows important notice in confirmation step', () => {
    render(<DepositPaymentFlow preorder={mockPreOrder} />);
    
    // Navigate to confirmation
    const gcashButton = screen.getByText('GCash').closest('button');
    fireEvent.click(gcashButton!);
    
    const mobileInput = screen.getByPlaceholderText('09123456789');
    fireEvent.change(mobileInput, { target: { value: '09123456789' } });
    
    const continueButton = screen.getByText('Continue');
    fireEvent.click(continueButton);
    
    expect(screen.getByText('Important Notice')).toBeInTheDocument();
    expect(screen.getByText(/non-refundable once the product arrives/)).toBeInTheDocument();
  });

  it('allows navigation back to previous steps', () => {
    render(<DepositPaymentFlow preorder={mockPreOrder} />);
    
    // Navigate to details
    const gcashButton = screen.getByText('GCash').closest('button');
    fireEvent.click(gcashButton!);
    
    // Go back to method selection
    const backButton = screen.getByText('Back');
    fireEvent.click(backButton);
    
    expect(screen.getByText('Choose Payment Method')).toBeInTheDocument();
  });

  it('shows progress indicator correctly', () => {
    render(<DepositPaymentFlow preorder={mockPreOrder} />);
    
    // Should show step indicators
    expect(screen.getByText('1')).toBeInTheDocument();
    expect(screen.getByText('2')).toBeInTheDocument();
    expect(screen.getByText('3')).toBeInTheDocument();
  });

  it('calls onCancel when cancel button is clicked', () => {
    const mockOnCancel = jest.fn();
    render(<DepositPaymentFlow preorder={mockPreOrder} onCancel={mockOnCancel} />);
    
    const cancelButton = screen.getByRole('button', { name: '' }); // X button
    fireEvent.click(cancelButton);
    
    expect(mockOnCancel).toHaveBeenCalled();
  });

  it('formats currency amounts correctly', () => {
    const preorderWithLargeAmount = {
      ...mockPreOrder,
      depositAmount: 12345.67
    };
    
    render(<DepositPaymentFlow preorder={preorderWithLargeAmount} />);
    
    expect(screen.getByText(/₱12,345.67/)).toBeInTheDocument();
  });

  it('shows bank selection dropdown for bank transfer', () => {
    render(<DepositPaymentFlow preorder={mockPreOrder} />);
    
    const bankButton = screen.getByText('Bank Transfer').closest('button');
    fireEvent.click(bankButton!);
    
    const bankSelect = screen.getByLabelText('Bank Name');
    expect(bankSelect).toBeInTheDocument();
    
    // Check for some bank options
    expect(screen.getByText('Bank of the Philippine Islands (BPI)')).toBeInTheDocument();
    expect(screen.getByText('Banco de Oro (BDO)')).toBeInTheDocument();
  });

  it('validates bank transfer form fields', () => {
    render(<DepositPaymentFlow preorder={mockPreOrder} />);
    
    const bankButton = screen.getByText('Bank Transfer').closest('button');
    fireEvent.click(bankButton!);
    
    // Try to continue without filling required fields
    const continueButton = screen.getByText('Continue');
    fireEvent.click(continueButton);
    
    // Should still be on the details step
    expect(screen.getByText('Bank Transfer Payment')).toBeInTheDocument();
  });
});