import React from 'react';
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import CheckoutProgress from '../CheckoutProgress';

describe('CheckoutProgress', () => {
  it('renders all checkout steps', () => {
    render(<CheckoutProgress currentStep={1} />);

    expect(screen.getByText('Shipping')).toBeInTheDocument();
    expect(screen.getByText('Payment')).toBeInTheDocument();
    expect(screen.getByText('Review')).toBeInTheDocument();
  });

  it('highlights current step', () => {
    render(<CheckoutProgress currentStep={2} />);

    const paymentStep = screen.getByText('Payment').previousElementSibling;
    expect(paymentStep).toHaveClass('bg-blue-600');
  });

  it('shows completed steps with checkmark', () => {
    render(<CheckoutProgress currentStep={3} />);

    // Step 1 and 2 should be completed (showing checkmarks)
    const steps = screen.getAllByRole('img', { hidden: true });
    expect(steps.length).toBeGreaterThan(0);
  });

  it('shows inactive steps in gray', () => {
    render(<CheckoutProgress currentStep={1} />);

    const paymentStep = screen.getByText('Payment').previousElementSibling;
    expect(paymentStep).toHaveClass('bg-gray-200');
  });

  it('displays step numbers correctly', () => {
    render(<CheckoutProgress currentStep={1} />);

    // Current step should show number
    expect(screen.getByText('1')).toBeInTheDocument();
  });

  it('applies correct styling to completed steps', () => {
    render(<CheckoutProgress currentStep={2} />);

    const shippingLabel = screen.getByText('Shipping');
    expect(shippingLabel).toHaveClass('text-blue-600');
  });

  it('applies correct styling to inactive steps', () => {
    render(<CheckoutProgress currentStep={1} />);

    const reviewLabel = screen.getByText('Review');
    expect(reviewLabel).toHaveClass('text-gray-600');
  });
});
