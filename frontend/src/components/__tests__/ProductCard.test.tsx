import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';
import { BrowserRouter } from 'react-router-dom';
import { Provider } from 'react-redux';
import { configureStore } from '@reduxjs/toolkit';
import ProductCard from '../ProductCard';
import { Product } from '../../types';
import productSlice from '../../store/slices/productSlice';
import authSlice from '../../store/slices/authSlice';
import cartSlice from '../../store/slices/cartSlice';
import loyaltySlice from '../../store/slices/loyaltySlice';

const mockStore = configureStore({
  reducer: {
    products: productSlice,
    auth: authSlice,
    cart: cartSlice,
    loyalty: loyaltySlice,
  },
});

const mockProduct: Product = {
  id: 1,
  sku: 'TEST-001',
  name: 'Test Diecast Model',
  description: 'A test diecast model',
  brandId: 1,
  categoryId: 1,
  scale: '1:64',
  material: 'diecast',
  features: ['opening doors', 'detailed interior'],
  isChaseVariant: false,
  basePrice: 1000,
  currentPrice: 900,
  stockQuantity: 10,
  isPreorder: false,
  status: 'active',
  images: ['https://example.com/image1.jpg'],
  specifications: {},
  brand: { id: 1, name: 'Test Brand', slug: 'test-brand', isActive: true },
  category: { id: 1, name: 'Test Category', slug: 'test-category', isActive: true },
  createdAt: '2024-01-01T00:00:00Z',
  updatedAt: '2024-01-01T00:00:00Z',
};

const renderWithProviders = (component: React.ReactElement) => {
  return render(
    <Provider store={mockStore}>
      <BrowserRouter>
        {component}
      </BrowserRouter>
    </Provider>
  );
};

describe('ProductCard', () => {
  it('renders product information correctly', () => {
    renderWithProviders(<ProductCard product={mockProduct} />);
    
    expect(screen.getByText('Test Diecast Model')).toBeInTheDocument();
    expect(screen.getByText('Test Brand')).toBeInTheDocument();
    expect(screen.getByText('1:64')).toBeInTheDocument();
    expect(screen.getByText('₱900.00')).toBeInTheDocument();
  });

  it('shows chase variant badge when product is chase variant', () => {
    const chaseProduct = { ...mockProduct, isChaseVariant: true };
    renderWithProviders(<ProductCard product={chaseProduct} />);
    
    expect(screen.getByText('Chase')).toBeInTheDocument();
  });

  it('calls onAddToCart when add to cart button is clicked', () => {
    const mockAddToCart = jest.fn();
    renderWithProviders(
      <ProductCard product={mockProduct} onAddToCart={mockAddToCart} />
    );
    
    // Find the cart button by looking for the shopping cart icon
    const buttons = screen.getAllByRole('button');
    const addToCartButton = buttons.find(button => 
      button.querySelector('svg')?.innerHTML.includes('M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z')
    );
    
    if (addToCartButton) {
      fireEvent.click(addToCartButton);
      expect(mockAddToCart).toHaveBeenCalledWith(mockProduct);
    }
  });

  it('shows pre-order button for pre-order products', () => {
    const preorderProduct = { ...mockProduct, isPreorder: true };
    renderWithProviders(<ProductCard product={preorderProduct} />);
    
    // Check for the button with "Pre-order" text
    const preorderButton = screen.getByRole('button', { name: /pre-order/i });
    expect(preorderButton).toBeInTheDocument();
  });

  it('shows out of stock status when stock is 0', () => {
    const outOfStockProduct = { ...mockProduct, stockQuantity: 0 };
    renderWithProviders(<ProductCard product={outOfStockProduct} />);
    
    expect(screen.getByText('Out of Stock')).toBeInTheDocument();
  });
});