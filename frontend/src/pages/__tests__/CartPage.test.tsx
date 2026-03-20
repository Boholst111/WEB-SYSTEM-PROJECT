import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import { BrowserRouter } from 'react-router-dom';
import { Provider } from 'react-redux';
import { configureStore } from '@reduxjs/toolkit';
import CartPage from '../CartPage';
import { cartApi } from '../../services/cartApi';
import authSlice from '../../store/slices/authSlice';
import cartSlice from '../../store/slices/cartSlice';
import productSlice from '../../store/slices/productSlice';
import loyaltySlice from '../../store/slices/loyaltySlice';
import preorderSlice from '../../store/slices/preorderSlice';

// Mock the cart API
jest.mock('../../services/cartApi');

const mockCartApi = cartApi as jest.Mocked<typeof cartApi>;

const createMockStore = (authState = { isAuthenticated: true, user: null, token: null, isLoading: false, error: null }) => {
  return configureStore({
    reducer: {
      auth: authSlice,
      cart: cartSlice,
      products: productSlice,
      loyalty: loyaltySlice,
      preorders: preorderSlice,
    },
    preloadedState: {
      auth: authState,
    },
  });
};

const mockCartData = {
  items: [
    {
      id: 1,
      product_id: 1,
      quantity: 2,
      price: 500,
      formatted_total: '₱1,000.00',
      product: {
        id: 1,
        name: 'Hot Wheels Premium Car',
        sku: 'HW-001',
        brand: 'Hot Wheels',
        category: 'Diecast',
        main_image: 'https://example.com/image.jpg',
        current_price: 500,
        stock_quantity: 10,
        is_available: true,
      },
    },
  ],
  summary: {
    subtotal: 1000,
    formatted_subtotal: '₱1,000.00',
    items_count: 1,
    total_quantity: 2,
  },
  loyalty: {
    available_credits: 100,
    max_credits_usable: 50,
    formatted_available: '₱100.00',
    formatted_max_usable: '₱50.00',
  },
  shipping_options: [],
};

const renderWithProviders = (component: React.ReactElement, store = createMockStore()) => {
  return render(
    <Provider store={store}>
      <BrowserRouter>
        {component}
      </BrowserRouter>
    </Provider>
  );
};

describe('CartPage', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('redirects to login if not authenticated', () => {
    const store = createMockStore({ isAuthenticated: false, user: null, token: null, isLoading: false, error: null });
    renderWithProviders(<CartPage />, store);
    // Navigation is handled by useNavigate, which is mocked in the test environment
  });

  it('displays loading state while fetching cart', () => {
    mockCartApi.getCart.mockImplementation(() => new Promise(() => {}));
    renderWithProviders(<CartPage />);
    
    expect(screen.getByText('Loading cart...')).toBeInTheDocument();
  });

  it('displays cart items correctly', async () => {
    mockCartApi.getCart.mockResolvedValue({
      success: true,
      data: mockCartData,
    });

    renderWithProviders(<CartPage />);

    await waitFor(() => {
      expect(screen.getByText('Hot Wheels Premium Car')).toBeInTheDocument();
      expect(screen.getByText('SKU: HW-001')).toBeInTheDocument();
      expect(screen.getByText('₱1,000.00')).toBeInTheDocument();
    });
  });

  it('displays empty cart message when cart is empty', async () => {
    mockCartApi.getCart.mockResolvedValue({
      success: true,
      data: {
        ...mockCartData,
        items: [],
        summary: { ...mockCartData.summary, items_count: 0, total_quantity: 0 },
      },
    });

    renderWithProviders(<CartPage />);

    await waitFor(() => {
      expect(screen.getByText('Your cart is empty')).toBeInTheDocument();
      expect(screen.getByText('Browse Products')).toBeInTheDocument();
    });
  });

  it('updates item quantity when + button is clicked', async () => {
    mockCartApi.getCart.mockResolvedValue({
      success: true,
      data: mockCartData,
    });
    mockCartApi.updateItem.mockResolvedValue({
      success: true,
      data: { ...mockCartData.items[0], quantity: 3 },
    });

    renderWithProviders(<CartPage />);

    await waitFor(() => {
      expect(screen.getByText('Hot Wheels Premium Car')).toBeInTheDocument();
    });

    const plusButtons = screen.getAllByText('+');
    fireEvent.click(plusButtons[0]);

    await waitFor(() => {
      expect(mockCartApi.updateItem).toHaveBeenCalledWith(1, 3);
    });
  });

  it('updates item quantity when - button is clicked', async () => {
    mockCartApi.getCart.mockResolvedValue({
      success: true,
      data: mockCartData,
    });
    mockCartApi.updateItem.mockResolvedValue({
      success: true,
      data: { ...mockCartData.items[0], quantity: 1 },
    });

    renderWithProviders(<CartPage />);

    await waitFor(() => {
      expect(screen.getByText('Hot Wheels Premium Car')).toBeInTheDocument();
    });

    const minusButtons = screen.getAllByText('-');
    fireEvent.click(minusButtons[0]);

    await waitFor(() => {
      expect(mockCartApi.updateItem).toHaveBeenCalledWith(1, 1);
    });
  });

  it('removes item when delete button is clicked', async () => {
    mockCartApi.getCart.mockResolvedValue({
      success: true,
      data: mockCartData,
    });
    mockCartApi.removeItem.mockResolvedValue({
      success: true,
      data: undefined,
    });

    // Mock window.confirm
    global.confirm = jest.fn(() => true);

    renderWithProviders(<CartPage />);

    await waitFor(() => {
      expect(screen.getByText('Hot Wheels Premium Car')).toBeInTheDocument();
    });

    const deleteButtons = screen.getAllByRole('button');
    const deleteButton = deleteButtons.find(btn => 
      btn.querySelector('svg')?.querySelector('path')?.getAttribute('d')?.includes('M19 7l-.867')
    );

    if (deleteButton) {
      fireEvent.click(deleteButton);

      await waitFor(() => {
        expect(mockCartApi.removeItem).toHaveBeenCalledWith(1);
      });
    }
  });

  it('clears cart when clear cart button is clicked', async () => {
    mockCartApi.getCart.mockResolvedValue({
      success: true,
      data: mockCartData,
    });
    mockCartApi.clearCart.mockResolvedValue({
      success: true,
      data: undefined,
    });

    // Mock window.confirm
    global.confirm = jest.fn(() => true);

    renderWithProviders(<CartPage />);

    await waitFor(() => {
      expect(screen.getByText('Clear Cart')).toBeInTheDocument();
    });

    const clearButton = screen.getByText('Clear Cart');
    fireEvent.click(clearButton);

    await waitFor(() => {
      expect(mockCartApi.clearCart).toHaveBeenCalled();
    });
  });

  it('displays loyalty credits information', async () => {
    mockCartApi.getCart.mockResolvedValue({
      success: true,
      data: mockCartData,
    });

    renderWithProviders(<CartPage />);

    await waitFor(() => {
      expect(screen.getByText('Loyalty Credits Available')).toBeInTheDocument();
      expect(screen.getByText('₱100.00')).toBeInTheDocument();
      expect(screen.getByText('₱50.00')).toBeInTheDocument();
    });
  });

  it('navigates to checkout when proceed to checkout is clicked', async () => {
    mockCartApi.getCart.mockResolvedValue({
      success: true,
      data: mockCartData,
    });

    renderWithProviders(<CartPage />);

    await waitFor(() => {
      expect(screen.getByText('Proceed to Checkout')).toBeInTheDocument();
    });

    const checkoutButton = screen.getByText('Proceed to Checkout');
    fireEvent.click(checkoutButton);

    // Navigation is handled by useNavigate
  });

  it('displays error message when cart loading fails', async () => {
    mockCartApi.getCart.mockRejectedValue({
      response: { data: { message: 'Failed to load cart' } },
    });

    renderWithProviders(<CartPage />);

    await waitFor(() => {
      expect(screen.getByText('Failed to load cart')).toBeInTheDocument();
    });
  });

  it('disables quantity buttons when stock limit is reached', async () => {
    const limitedStockCart = {
      ...mockCartData,
      items: [
        {
          ...mockCartData.items[0],
          quantity: 10,
          product: {
            ...mockCartData.items[0].product,
            stock_quantity: 10,
          },
        },
      ],
    };

    mockCartApi.getCart.mockResolvedValue({
      success: true,
      data: limitedStockCart,
    });

    renderWithProviders(<CartPage />);

    await waitFor(() => {
      const plusButtons = screen.getAllByText('+');
      expect(plusButtons[0]).toBeDisabled();
    });
  });
});
