import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import SearchInterface from '../SearchInterface';
import { productApi } from '../../services/api';

jest.mock('../../services/api');

const mockProductApi = productApi as jest.Mocked<typeof productApi>;

describe('SearchInterface', () => {
  const mockOnSearch = jest.fn();

  beforeEach(() => {
    jest.clearAllMocks();
  });

  const renderComponent = (props = {}) => {
    return render(
      <BrowserRouter>
        <SearchInterface onSearch={mockOnSearch} {...props} />
      </BrowserRouter>
    );
  };

  it('renders search input', () => {
    renderComponent();
    const input = screen.getByPlaceholderText(/search for diecast models/i);
    expect(input).toBeInTheDocument();
  });

  it('calls onSearch when form is submitted', () => {
    renderComponent();
    const input = screen.getByPlaceholderText(/search for diecast models/i);
    
    fireEvent.change(input, { target: { value: 'Ferrari' } });
    fireEvent.submit(input.closest('form')!);

    expect(mockOnSearch).toHaveBeenCalledWith('Ferrari');
  });

  it('shows clear button when input has value', () => {
    renderComponent();
    const input = screen.getByPlaceholderText(/search for diecast models/i);
    
    fireEvent.change(input, { target: { value: 'Ferrari' } });
    
    const clearButton = screen.getByRole('button', { name: '' });
    expect(clearButton).toBeInTheDocument();
  });

  it('clears input when clear button is clicked', () => {
    renderComponent();
    const input = screen.getByPlaceholderText(/search for diecast models/i) as HTMLInputElement;
    
    fireEvent.change(input, { target: { value: 'Ferrari' } });
    expect(input.value).toBe('Ferrari');
    
    const clearButton = screen.getByRole('button', { name: '' });
    fireEvent.click(clearButton);
    
    expect(input.value).toBe('');
  });

  it('fetches suggestions when typing', async () => {
    mockProductApi.getProductSuggestions.mockResolvedValue({
      success: true,
      data: ['Ferrari F40', 'Ferrari 458'],
    });

    mockProductApi.searchProducts.mockResolvedValue({
      success: true,
      data: [],
    });

    renderComponent({ showSuggestions: true });
    const input = screen.getByPlaceholderText(/search for diecast models/i);
    
    fireEvent.change(input, { target: { value: 'Ferrari' } });

    await waitFor(() => {
      expect(mockProductApi.getProductSuggestions).toHaveBeenCalledWith('Ferrari');
    }, { timeout: 500 });
  });

  it('does not show suggestions when showSuggestions is false', () => {
    renderComponent({ showSuggestions: false });
    const input = screen.getByPlaceholderText(/search for diecast models/i);
    
    fireEvent.change(input, { target: { value: 'Ferrari' } });
    
    // Suggestions dropdown should not appear
    expect(screen.queryByText('Suggestions')).not.toBeInTheDocument();
  });

  it('handles keyboard navigation', () => {
    renderComponent();
    const input = screen.getByPlaceholderText(/search for diecast models/i);
    
    fireEvent.change(input, { target: { value: 'Ferrari' } });
    fireEvent.keyDown(input, { key: 'ArrowDown' });
    fireEvent.keyDown(input, { key: 'ArrowUp' });
    fireEvent.keyDown(input, { key: 'Escape' });
    
    // Component should handle keyboard events without errors
    expect(input).toBeInTheDocument();
  });

  it('uses custom placeholder', () => {
    renderComponent({ placeholder: 'Custom placeholder' });
    expect(screen.getByPlaceholderText('Custom placeholder')).toBeInTheDocument();
  });
});
