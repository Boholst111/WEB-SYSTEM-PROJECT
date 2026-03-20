import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';
import AddressSelector from '../AddressSelector';
import { UserAddress } from '../../services/checkoutApi';

const mockAddresses: UserAddress[] = [
  {
    id: 1,
    user_id: 1,
    type: 'shipping',
    first_name: 'John',
    last_name: 'Doe',
    address_line_1: '123 Main St',
    city: 'Manila',
    province: 'Metro Manila',
    postal_code: '1000',
    country: 'Philippines',
    phone: '+63 912 345 6789',
    is_default: true,
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-01T00:00:00Z',
  },
  {
    id: 2,
    user_id: 1,
    type: 'shipping',
    first_name: 'Jane',
    last_name: 'Smith',
    address_line_1: '456 Oak Ave',
    city: 'Quezon City',
    province: 'Metro Manila',
    postal_code: '1100',
    country: 'Philippines',
    phone: '+63 923 456 7890',
    is_default: false,
    created_at: '2024-01-02T00:00:00Z',
    updated_at: '2024-01-02T00:00:00Z',
  },
];

describe('AddressSelector', () => {
  const mockOnSelectAddress = jest.fn();
  const mockOnAddNew = jest.fn();
  const mockOnEdit = jest.fn();
  const mockOnDelete = jest.fn();

  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('renders addresses correctly', () => {
    render(
      <AddressSelector
        addresses={mockAddresses}
        selectedAddressId={null}
        onSelectAddress={mockOnSelectAddress}
        onAddNew={mockOnAddNew}
        onEdit={mockOnEdit}
        onDelete={mockOnDelete}
      />
    );

    expect(screen.getByText('John Doe')).toBeInTheDocument();
    expect(screen.getByText('Jane Smith')).toBeInTheDocument();
    expect(screen.getByText(/123 Main St/)).toBeInTheDocument();
    expect(screen.getByText(/456 Oak Ave/)).toBeInTheDocument();
  });

  it('shows default badge for default address', () => {
    render(
      <AddressSelector
        addresses={mockAddresses}
        selectedAddressId={null}
        onSelectAddress={mockOnSelectAddress}
        onAddNew={mockOnAddNew}
        onEdit={mockOnEdit}
        onDelete={mockOnDelete}
      />
    );

    expect(screen.getByText('Default')).toBeInTheDocument();
  });

  it('calls onSelectAddress when address is clicked', () => {
    render(
      <AddressSelector
        addresses={mockAddresses}
        selectedAddressId={null}
        onSelectAddress={mockOnSelectAddress}
        onAddNew={mockOnAddNew}
        onEdit={mockOnEdit}
        onDelete={mockOnDelete}
      />
    );

    const addressCard = screen.getByText('John Doe').closest('div')?.parentElement;
    if (addressCard) {
      fireEvent.click(addressCard);
      expect(mockOnSelectAddress).toHaveBeenCalledWith(1);
    }
  });

  it('highlights selected address', () => {
    render(
      <AddressSelector
        addresses={mockAddresses}
        selectedAddressId={1}
        onSelectAddress={mockOnSelectAddress}
        onAddNew={mockOnAddNew}
        onEdit={mockOnEdit}
        onDelete={mockOnDelete}
      />
    );

    const addressCard = screen.getByText('John Doe').closest('div')?.parentElement;
    expect(addressCard).toHaveClass('border-blue-600');
  });

  it('calls onAddNew when add new address button is clicked', () => {
    render(
      <AddressSelector
        addresses={mockAddresses}
        selectedAddressId={null}
        onSelectAddress={mockOnSelectAddress}
        onAddNew={mockOnAddNew}
        onEdit={mockOnEdit}
        onDelete={mockOnDelete}
      />
    );

    const addButton = screen.getByText('+ Add New Address');
    fireEvent.click(addButton);
    expect(mockOnAddNew).toHaveBeenCalled();
  });

  it('calls onEdit when edit button is clicked', () => {
    render(
      <AddressSelector
        addresses={mockAddresses}
        selectedAddressId={null}
        onSelectAddress={mockOnSelectAddress}
        onAddNew={mockOnAddNew}
        onEdit={mockOnEdit}
        onDelete={mockOnDelete}
      />
    );

    const editButtons = screen.getAllByText('Edit');
    fireEvent.click(editButtons[0]);
    expect(mockOnEdit).toHaveBeenCalledWith(mockAddresses[0]);
  });

  it('calls onDelete when delete button is clicked and confirmed', () => {
    global.confirm = jest.fn(() => true);

    render(
      <AddressSelector
        addresses={mockAddresses}
        selectedAddressId={null}
        onSelectAddress={mockOnSelectAddress}
        onAddNew={mockOnAddNew}
        onEdit={mockOnEdit}
        onDelete={mockOnDelete}
      />
    );

    const deleteButton = screen.getByText('Delete');
    fireEvent.click(deleteButton);
    expect(mockOnDelete).toHaveBeenCalledWith(2);
  });

  it('does not show delete button for default address', () => {
    render(
      <AddressSelector
        addresses={mockAddresses}
        selectedAddressId={null}
        onSelectAddress={mockOnSelectAddress}
        onAddNew={mockOnAddNew}
        onEdit={mockOnEdit}
        onDelete={mockOnDelete}
      />
    );

    const deleteButtons = screen.queryAllByText('Delete');
    expect(deleteButtons).toHaveLength(1); // Only one delete button for non-default address
  });

  it('displays empty state when no addresses', () => {
    render(
      <AddressSelector
        addresses={[]}
        selectedAddressId={null}
        onSelectAddress={mockOnSelectAddress}
        onAddNew={mockOnAddNew}
        onEdit={mockOnEdit}
        onDelete={mockOnDelete}
      />
    );

    expect(screen.getByText('No saved addresses')).toBeInTheDocument();
    expect(screen.getByText('Add Address')).toBeInTheDocument();
  });

  it('displays phone numbers correctly', () => {
    render(
      <AddressSelector
        addresses={mockAddresses}
        selectedAddressId={null}
        onSelectAddress={mockOnSelectAddress}
        onAddNew={mockOnAddNew}
        onEdit={mockOnEdit}
        onDelete={mockOnDelete}
      />
    );

    expect(screen.getByText(/\+63 912 345 6789/)).toBeInTheDocument();
    expect(screen.getByText(/\+63 923 456 7890/)).toBeInTheDocument();
  });
});
