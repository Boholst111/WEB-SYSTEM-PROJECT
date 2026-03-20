import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import PaymentForm from '../PaymentForm';

describe('PaymentForm', () => {
  const defaultProps = {
    paymentMethod: 'gcash' as const,
    amount: 1000,
    onSubmit: jest.fn(),
    onCancel: jest.fn(),
    loading: false,
    error: null
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('GCash Payment Form', () => {
    it('renders GCash payment form correctly', () => {
      render(<PaymentForm {...defaultProps} paymentMethod="gcash" />);
      
      expect(screen.getByText('GCash Payment')).toBeInTheDocument();
      expect(screen.getAllByText('₱1,000.00')).toHaveLength(3); // Header, summary, and button
      expect(screen.getByLabelText('Mobile Number')).toBeInTheDocument();
      expect(screen.getByPlaceholderText('09XX XXX XXXX')).toBeInTheDocument();
    });

    it('validates phone number for GCash', async () => {
      const onSubmit = jest.fn();
      render(<PaymentForm {...defaultProps} paymentMethod="gcash" onSubmit={onSubmit} />);
      
      // Submit without phone number
      fireEvent.click(screen.getByText('Pay ₱1,000.00'));
      
      await waitFor(() => {
        expect(screen.getByText('Phone number is required')).toBeInTheDocument();
      });
      expect(onSubmit).not.toHaveBeenCalled();
    });

    it('validates phone number format for GCash', async () => {
      const onSubmit = jest.fn();
      render(<PaymentForm {...defaultProps} paymentMethod="gcash" onSubmit={onSubmit} />);
      
      // Enter invalid phone number
      fireEvent.change(screen.getByPlaceholderText('09XX XXX XXXX'), {
        target: { value: '123456' }
      });
      fireEvent.click(screen.getByText('Pay ₱1,000.00'));
      
      await waitFor(() => {
        expect(screen.getByText('Please enter a valid Philippine mobile number')).toBeInTheDocument();
      });
      expect(onSubmit).not.toHaveBeenCalled();
    });

    it('submits valid GCash form', async () => {
      const onSubmit = jest.fn();
      render(<PaymentForm {...defaultProps} paymentMethod="gcash" onSubmit={onSubmit} />);
      
      // Enter valid phone number
      fireEvent.change(screen.getByPlaceholderText('09XX XXX XXXX'), {
        target: { value: '09123456789' }
      });
      fireEvent.click(screen.getByText('Pay ₱1,000.00'));
      
      await waitFor(() => {
        expect(onSubmit).toHaveBeenCalledWith({
          paymentMethod: 'gcash',
          amount: 1000,
          phone: '09123456789',
          bank: 'bpi'
        });
      });
    });
  });

  describe('Maya Payment Form', () => {
    it('renders Maya payment form correctly', () => {
      render(<PaymentForm {...defaultProps} paymentMethod="maya" />);
      
      expect(screen.getByText('Maya Payment')).toBeInTheDocument();
      expect(screen.getByText('You will be redirected to Maya to complete your payment')).toBeInTheDocument();
    });

    it('validates phone number for Maya', async () => {
      const onSubmit = jest.fn();
      render(<PaymentForm {...defaultProps} paymentMethod="maya" onSubmit={onSubmit} />);
      
      // Submit without phone number
      fireEvent.click(screen.getByText('Pay ₱1,000.00'));
      
      await waitFor(() => {
        expect(screen.getByText('Phone number is required')).toBeInTheDocument();
      });
      expect(onSubmit).not.toHaveBeenCalled();
    });
  });

  describe('Bank Transfer Payment Form', () => {
    it('renders Bank Transfer payment form correctly', () => {
      render(<PaymentForm {...defaultProps} paymentMethod="bank_transfer" />);
      
      expect(screen.getByText('Bank Transfer Payment')).toBeInTheDocument();
      expect(screen.getByText('Select Bank')).toBeInTheDocument();
      expect(screen.getByDisplayValue('Bank of the Philippine Islands (BPI)')).toBeInTheDocument();
    });

    it('shows bank transfer instructions', () => {
      render(<PaymentForm {...defaultProps} paymentMethod="bank_transfer" />);
      
      expect(screen.getByText('Bank Transfer Instructions:')).toBeInTheDocument();
      expect(screen.getByText('You will receive bank account details after confirmation')).toBeInTheDocument();
      expect(screen.getByText('Transfer must be completed within 24 hours')).toBeInTheDocument();
    });

    it('submits valid bank transfer form', async () => {
      const onSubmit = jest.fn();
      render(<PaymentForm {...defaultProps} paymentMethod="bank_transfer" onSubmit={onSubmit} />);
      
      // Select different bank
      fireEvent.change(screen.getByDisplayValue('Bank of the Philippine Islands (BPI)'), {
        target: { value: 'bdo' }
      });
      fireEvent.click(screen.getByText('Pay ₱1,000.00'));
      
      await waitFor(() => {
        expect(onSubmit).toHaveBeenCalledWith({
          paymentMethod: 'bank_transfer',
          amount: 1000,
          phone: '',
          bank: 'bdo'
        });
      });
    });
  });

  describe('Common Form Features', () => {
    it('displays error message when provided', () => {
      render(<PaymentForm {...defaultProps} error="Payment processing failed" />);
      
      expect(screen.getByText('Payment Error')).toBeInTheDocument();
      expect(screen.getByText('Payment processing failed')).toBeInTheDocument();
    });

    it('shows loading state when processing', () => {
      render(<PaymentForm {...defaultProps} loading={true} />);
      
      expect(screen.getByText('Processing...')).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /processing/i })).toBeDisabled();
    });

    it('calls onCancel when cancel button is clicked', () => {
      const onCancel = jest.fn();
      render(<PaymentForm {...defaultProps} onCancel={onCancel} />);
      
      fireEvent.click(screen.getByText('Cancel'));
      expect(onCancel).toHaveBeenCalled();
    });

    it('displays payment summary correctly', () => {
      render(<PaymentForm {...defaultProps} amount={2500} />);
      
      expect(screen.getByText('Payment Summary')).toBeInTheDocument();
      expect(screen.getAllByText('₱2,500.00')).toHaveLength(3); // Header, summary, and button
    });

    it('clears validation errors when user starts typing', async () => {
      render(<PaymentForm {...defaultProps} paymentMethod="gcash" />);
      
      // Trigger validation error
      fireEvent.click(screen.getByText('Pay ₱1,000.00'));
      
      await waitFor(() => {
        expect(screen.getByText('Phone number is required')).toBeInTheDocument();
      });

      // Start typing to clear error
      fireEvent.change(screen.getByPlaceholderText('09XX XXX XXXX'), {
        target: { value: '09' }
      });

      await waitFor(() => {
        expect(screen.queryByText('Phone number is required')).not.toBeInTheDocument();
      });
    });

    it('disables form inputs when loading', () => {
      render(<PaymentForm {...defaultProps} paymentMethod="gcash" loading={true} />);
      
      expect(screen.getByPlaceholderText('09XX XXX XXXX')).toBeDisabled();
      expect(screen.getByText('Cancel')).toBeDisabled();
    });
  });
});