import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';
import ShippingSelector from '../ShippingSelector';
import { ShippingOption } from '../../services/cartApi';

const mockShippingOptions: ShippingOption[] = [
  {
    id: 'standard',
    name: 'Standard Shipping',
    description: 'Delivery within Metro Manila',
    cost: 150,
    formatted_cost: '₱150.00',
    estimated_days: '3-5 business days',
  },
  {
    id: 'express',
    name: 'Express Shipping',
    description: 'Fast delivery within Metro Manila',
    cost: 300,
    formatted_cost: '₱300.00',
    estimated_days: '1-2 business days',
  },
];

describe('ShippingSelector', () => {
  const mockOnSelectOption = jest.fn();

  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('renders shipping options correctly', () => {
    render(
      <ShippingSelector
        options={mockShippingOptions}
        selectedOption={null}
        onSelectOption={mockOnSelectOption}
      />
    );

    expect(screen.getByText('Standard Shipping')).toBeInTheDocument();
    expect(screen.getByText('Express Shipping')).toBeInTheDocument();
    expect(screen.getByText('₱150.00')).toBeInTheDocument();
    expect(screen.getByText('₱300.00')).toBeInTheDocument();
  });

  it('displays shipping descriptions', () => {
    render(
      <ShippingSelector
        options={mockShippingOptions}
        selectedOption={null}
        onSelectOption={mockOnSelectOption}
      />
    );

    expect(screen.getByText('Delivery within Metro Manila')).toBeInTheDocument();
    expect(screen.getByText('Fast delivery within Metro Manila')).toBeInTheDocument();
  });

  it('displays estimated delivery times', () => {
    render(
      <ShippingSelector
        options={mockShippingOptions}
        selectedOption={null}
        onSelectOption={mockOnSelectOption}
      />
    );

    expect(screen.getByText(/3-5 business days/)).toBeInTheDocument();
    expect(screen.getByText(/1-2 business days/)).toBeInTheDocument();
  });

  it('calls onSelectOption when option is clicked', () => {
    render(
      <ShippingSelector
        options={mockShippingOptions}
        selectedOption={null}
        onSelectOption={mockOnSelectOption}
      />
    );

    const standardOption = screen.getByText('Standard Shipping').closest('div');
    if (standardOption) {
      fireEvent.click(standardOption);
      expect(mockOnSelectOption).toHaveBeenCalledWith('standard');
    }
  });

  it('highlights selected option', () => {
    render(
      <ShippingSelector
        options={mockShippingOptions}
        selectedOption="standard"
        onSelectOption={mockOnSelectOption}
      />
    );

    const standardOption = screen.getByText('Standard Shipping').closest('div');
    expect(standardOption).toHaveClass('border-blue-600');
  });

  it('displays empty state when no options available', () => {
    render(
      <ShippingSelector
        options={[]}
        selectedOption={null}
        onSelectOption={mockOnSelectOption}
      />
    );

    expect(screen.getByText('No shipping options available')).toBeInTheDocument();
  });

  it('checks radio button for selected option', () => {
    render(
      <ShippingSelector
        options={mockShippingOptions}
        selectedOption="express"
        onSelectOption={mockOnSelectOption}
      />
    );

    const radioButtons = screen.getAllByRole('radio');
    expect(radioButtons[1]).toBeChecked();
  });

  it('allows changing selection', () => {
    const { rerender } = render(
      <ShippingSelector
        options={mockShippingOptions}
        selectedOption="standard"
        onSelectOption={mockOnSelectOption}
      />
    );

    const expressOption = screen.getByText('Express Shipping').closest('div');
    if (expressOption) {
      fireEvent.click(expressOption);
      expect(mockOnSelectOption).toHaveBeenCalledWith('express');
    }
  });
});
