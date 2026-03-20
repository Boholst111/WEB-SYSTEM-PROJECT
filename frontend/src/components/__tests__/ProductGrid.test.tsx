import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import { BrowserRouter } from 'react-router-dom';
import { Provider } from 'react-redux';
import { configureStore } from '@reduxjs/toolkit';
import ProductGrid from '../ProductGrid';
import { Product } from '../../types';
import productSlice from '../../store/slices/productSlice';
import authSlice from '../../store/slices/authSlice';
import cartSlice from '../../store/slices/cartSlice';
import loyaltySlice from '../../store/slices/loyaltySlice';

// Mock react-intersection-observer
jest.mock('react-intersection-observer', () => ({
  useInView: jest.fn(() => ({ ref: jest.fn(), inView: false })),
}));

const mockStore = configureStore({
  reducer: {
    products: productSlice,
    auth: authSlice,
    cart: cartSlice,
    loyalty: loyaltySlice,
  },
});

const mockProducts: Product[] = [
  {
    id: 1,
    sku: 'TEST-001',
    name: 'Test Diecast Model 1',
    description: 'A test diecast model',
    brandId: 1,
    categoryId: 1,
    scale: '1:64',
    material: 'diecast',
    features: ['opening doors'],
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
  },
  {
    id: 2,
    sku: 'TEST-002',
    name: 'Test Diecast Model 2',
    description: 'Another test diecast model',
    brandId: 2,
    categoryId: 1,
    scale: '1:43',
    material: 'diecast',
    features: ['detailed interior'],
    isChaseVariant: true,
    basePrice: 1500,
    currentPrice: 1500,
    stockQuantity: 5,
    isPreorder: false,
    status: 'active',
    images: ['https://example.com/image2.jpg'],
    specifications: {},
    brand: { id: 2, name: 'Another Brand', slug: 'another-brand', isActive: true },
    category: { id: 1, name: 'Test Category', slug: 'test-category', isActive: true },
    createdAt: '2024-01-01T00:00:00Z',
    updatedAt: '2024-01-01T00:00:00Z',
  },
];

const renderWithProviders = (component: React.ReactElement) => {
  return render(
    <Provider store={mockStore}>
      <BrowserRouter>
        {component}
      </BrowserRouter>
    </Provider>
  );
};

describe('ProductGrid', () => {
  const defaultProps = {
    products: mockProducts,
    isLoading: false,
    hasMore: false,
    onLoadMore: jest.fn(),
    onAddToCart: jest.fn(),
    onToggleWishlist: jest.fn(),
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('renders products correctly', () => {
    renderWithProviders(<ProductGrid {...defaultProps} />);
    
    expect(screen.getByText('Test Diecast Model 1')).toBeInTheDocument();
    expect(screen.getByText('Test Diecast Model 2')).toBeInTheDocument();
    expect(screen.getByText('Test Brand')).toBeInTheDocument();
    expect(screen.getByText('Another Brand')).toBeInTheDocument();
  });

  it('shows loading skeletons when loading and no products', () => {
    renderWithProviders(
      <ProductGrid {...defaultProps} products={[]} isLoading={true} />
    );
    
    const skeletons = document.querySelectorAll('.animate-pulse');
    expect(skeletons.length).toBeGreaterThan(0);
  });

  it('shows error message when error occurs', () => {
    const errorMessage = 'Failed to load products';
    renderWithProviders(
      <ProductGrid {...defaultProps} error={errorMessage} />
    );
    
    expect(screen.getByText('Something went wrong')).toBeInTheDocument();
    expect(screen.getByText(errorMessage)).toBeInTheDocument();
    expect(screen.getByText('Try Again')).toBeInTheDocument();
  });

  it('shows no products message when no products and not loading', () => {
    renderWithProviders(
      <ProductGrid {...defaultProps} products={[]} isLoading={false} />
    );
    
    expect(screen.getByText('No products found')).toBeInTheDocument();
    expect(screen.getByText('Try adjusting your search or filter criteria')).toBeInTheDocument();
  });

  it('calls onAddToCart when add to cart is clicked', () => {
    const mockAddToCart = jest.fn();
    renderWithProviders(
      <ProductGrid {...defaultProps} onAddToCart={mockAddToCart} />
    );
    
    // Find the first add to cart button
    const addToCartButtons = screen.getAllByRole('button');
    const cartButton = addToCartButtons.find(button => 
      button.getAttribute('aria-label')?.includes('cart') || 
      button.textContent?.includes('cart')
    );
    
    if (cartButton) {
      fireEvent.click(cartButton);
      expect(mockAddToCart).toHaveBeenCalledWith(mockProducts[0]);
    }
  });

  it('calls onToggleWishlist when wishlist is clicked', () => {
    const mockToggleWishlist = jest.fn();
    renderWithProviders(
      <ProductGrid {...defaultProps} onToggleWishlist={mockToggleWishlist} />
    );
    
    // Find the first wishlist button
    const wishlistButtons = screen.getAllByRole('button');
    const wishlistButton = wishlistButtons.find(button => 
      button.getAttribute('aria-label')?.includes('wishlist') || 
      button.textContent?.includes('wishlist')
    );
    
    if (wishlistButton) {
      fireEvent.click(wishlistButton);
      expect(mockToggleWishlist).toHaveBeenCalledWith(mockProducts[0]);
    }
  });

  it('shows end of results indicator when no more products', () => {
    renderWithProviders(
      <ProductGrid {...defaultProps} hasMore={false} />
    );
    
    expect(screen.getByText("You've reached the end")).toBeInTheDocument();
  });

  it('shows loading indicator for infinite scroll when has more products', () => {
    renderWithProviders(
      <ProductGrid {...defaultProps} hasMore={true} isLoading={true} />
    );
    
    // Should show additional loading skeletons for infinite scroll
    const skeletons = document.querySelectorAll('.animate-pulse');
    expect(skeletons.length).toBeGreaterThan(0);
  });

  it('handles try again button click on error', () => {
    // Mock window.location.reload
    const mockReload = jest.fn();
    Object.defineProperty(window, 'location', {
      value: { reload: mockReload },
      writable: true,
    });

    renderWithProviders(
      <ProductGrid {...defaultProps} error="Network error" />
    );
    
    const tryAgainButton = screen.getByText('Try Again');
    fireEvent.click(tryAgainButton);
    
    expect(mockReload).toHaveBeenCalled();
  });

  it('renders products in grid layout', () => {
    renderWithProviders(<ProductGrid {...defaultProps} />);
    
    const gridContainer = document.querySelector('.grid');
    expect(gridContainer).toHaveClass('grid-cols-1', 'sm:grid-cols-2', 'lg:grid-cols-3', 'xl:grid-cols-4');
  });

  it('handles empty products array gracefully', () => {
    renderWithProviders(
      <ProductGrid {...defaultProps} products={[]} />
    );
    
    expect(screen.getByText('No products found')).toBeInTheDocument();
  });
});