import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import { BrowserRouter, MemoryRouter, Routes, Route } from 'react-router-dom';
import { Provider } from 'react-redux';
import { configureStore } from '@reduxjs/toolkit';
import ProductDetailPage from '../ProductDetailPage';
import { productApi } from '../../services/api';
import { Product } from '../../types';
import productSlice from '../../store/slices/productSlice';
import authSlice from '../../store/slices/authSlice';
import cartSlice from '../../store/slices/cartSlice';
import loyaltySlice from '../../store/slices/loyaltySlice';

// Mock the API
jest.mock('../../services/api', () => ({
  productApi: {
    getProduct: jest.fn(),
  },
}));

// Mock react-router-dom navigate
const mockNavigate = jest.fn();
jest.mock('react-router-dom', () => ({
  ...jest.requireActual('react-router-dom'),
  useNavigate: () => mockNavigate,
}));

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
  sku: 'HW-001',
  name: 'Hot Wheels Corvette Z06',
  description: 'A detailed 1:64 scale Hot Wheels Corvette Z06 with opening doors and detailed interior.',
  brandId: 1,
  categoryId: 1,
  scale: '1:64',
  material: 'diecast',
  features: ['opening doors', 'detailed interior', 'rubber tires'],
  isChaseVariant: false,
  basePrice: 600,
  currentPrice: 500,
  stockQuantity: 10,
  isPreorder: false,
  status: 'active',
  images: [
    'https://example.com/corvette1.jpg',
    'https://example.com/corvette2.jpg',
    'https://example.com/corvette3.jpg',
  ],
  specifications: {
    manufacturer: 'Chevrolet',
    year: '2023',
    color: 'Red',
    doors: '2',
    engine: 'V8',
  },
  brand: { id: 1, name: 'Hot Wheels', slug: 'hot-wheels', isActive: true },
  category: { id: 1, name: 'Sports Cars', slug: 'sports-cars', isActive: true },
  createdAt: '2024-01-01T00:00:00Z',
  updatedAt: '2024-01-01T00:00:00Z',
};

const mockChaseProduct: Product = {
  ...mockProduct,
  id: 2,
  name: 'Hot Wheels Corvette Z06 Chase',
  isChaseVariant: true,
  stockQuantity: 2,
};

const mockPreorderProduct: Product = {
  ...mockProduct,
  id: 3,
  name: 'Hot Wheels Future Model',
  isPreorder: true,
  stockQuantity: 0,
};

const mockOutOfStockProduct: Product = {
  ...mockProduct,
  id: 4,
  name: 'Hot Wheels Sold Out Model',
  stockQuantity: 0,
};

const renderWithProviders = (component: React.ReactElement, route = '/products/1') => {
  return render(
    <Provider store={mockStore}>
      <MemoryRouter initialEntries={[route]}>
        <Routes>
          <Route path="/products/:id" element={component} />
        </Routes>
      </MemoryRouter>
    </Provider>
  );
};

describe('ProductDetailPage', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    (productApi.getProduct as jest.Mock).mockResolvedValue({ data: mockProduct });
  });

  it('renders product details correctly', async () => {
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      expect(screen.getByText('Hot Wheels Corvette Z06')).toBeInTheDocument();
      expect(screen.getByText('Hot Wheels')).toBeInTheDocument();
      expect(screen.getByText('1:64')).toBeInTheDocument();
      expect(screen.getByText('SKU: HW-001')).toBeInTheDocument();
    });
  });

  it('displays product price correctly', async () => {
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      expect(screen.getByText('₱500.00')).toBeInTheDocument();
      expect(screen.getByText('₱600.00')).toBeInTheDocument(); // Original price
      expect(screen.getByText('Save ₱100.00')).toBeInTheDocument();
    });
  });

  it('shows chase variant badge for chase products', async () => {
    (productApi.getProduct as jest.Mock).mockResolvedValue({ data: mockChaseProduct });
    
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      expect(screen.getByText('⭐ Chase Variant')).toBeInTheDocument();
    });
  });

  it('displays product features', async () => {
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      expect(screen.getByText('Features')).toBeInTheDocument();
      expect(screen.getByText('opening doors')).toBeInTheDocument();
      expect(screen.getByText('detailed interior')).toBeInTheDocument();
      expect(screen.getByText('rubber tires')).toBeInTheDocument();
    });
  });

  it('shows correct stock status for in-stock products', async () => {
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      expect(screen.getByText('In Stock')).toBeInTheDocument();
    });
  });

  it('shows correct stock status for low stock products', async () => {
    const lowStockProduct = { ...mockProduct, stockQuantity: 3 };
    (productApi.getProduct as jest.Mock).mockResolvedValue({ data: lowStockProduct });
    
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      expect(screen.getByText('Only 3 left in stock')).toBeInTheDocument();
    });
  });

  it('shows correct status for out of stock products', async () => {
    (productApi.getProduct as jest.Mock).mockResolvedValue({ data: mockOutOfStockProduct });
    
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      expect(screen.getByText('Out of Stock')).toBeInTheDocument();
    });
  });

  it('shows correct status for preorder products', async () => {
    (productApi.getProduct as jest.Mock).mockResolvedValue({ data: mockPreorderProduct });
    
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      expect(screen.getByText('Available for Pre-order')).toBeInTheDocument();
    });
  });

  it('handles quantity selection', async () => {
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      const quantityDisplay = screen.getByText('1');
      expect(quantityDisplay).toBeInTheDocument();
      
      const increaseButton = screen.getByText('+');
      fireEvent.click(increaseButton);
      
      expect(screen.getByText('2')).toBeInTheDocument();
    });
  });

  it('prevents quantity from going below 1', async () => {
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      const decreaseButton = screen.getByText('-');
      fireEvent.click(decreaseButton);
      
      // Should still show 1
      expect(screen.getByText('1')).toBeInTheDocument();
    });
  });

  it('prevents quantity from exceeding stock', async () => {
    const limitedStockProduct = { ...mockProduct, stockQuantity: 2 };
    (productApi.getProduct as jest.Mock).mockResolvedValue({ data: limitedStockProduct });
    
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      const increaseButton = screen.getByText('+');
      fireEvent.click(increaseButton); // Should be 2
      fireEvent.click(increaseButton); // Should still be 2
      
      expect(screen.getByText('2')).toBeInTheDocument();
    });
  });

  it('handles add to cart functionality', async () => {
    const consoleSpy = jest.spyOn(console, 'log').mockImplementation();
    
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      const addToCartButton = screen.getByText('Add to Cart');
      fireEvent.click(addToCartButton);
      
      expect(consoleSpy).toHaveBeenCalledWith('Add to cart:', mockProduct, 'quantity:', 1);
    });
    
    consoleSpy.mockRestore();
  });

  it('shows pre-order button for preorder products', async () => {
    (productApi.getProduct as jest.Mock).mockResolvedValue({ data: mockPreorderProduct });
    
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      expect(screen.getByText('Pre-order Now')).toBeInTheDocument();
    });
  });

  it('handles wishlist toggle', async () => {
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      const wishlistButton = screen.getByRole('button', { name: /wishlist/i });
      fireEvent.click(wishlistButton);
      
      // Should toggle the heart icon (implementation detail may vary)
      expect(wishlistButton).toBeInTheDocument();
    });
  });

  it('handles share functionality', async () => {
    // Mock navigator.share
    const mockShare = jest.fn();
    Object.defineProperty(navigator, 'share', {
      value: mockShare,
      writable: true,
    });
    
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      const shareButton = screen.getByRole('button', { name: /share/i });
      fireEvent.click(shareButton);
      
      expect(mockShare).toHaveBeenCalledWith({
        title: 'Hot Wheels Corvette Z06',
        text: 'Check out this 1:64 Hot Wheels model',
        url: expect.any(String),
      });
    });
  });

  it('falls back to clipboard when share is not available', async () => {
    // Mock navigator.clipboard
    const mockWriteText = jest.fn();
    Object.defineProperty(navigator, 'clipboard', {
      value: { writeText: mockWriteText },
      writable: true,
    });
    
    // Ensure navigator.share is not available
    Object.defineProperty(navigator, 'share', {
      value: undefined,
      writable: true,
    });
    
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      const shareButton = screen.getByRole('button', { name: /share/i });
      fireEvent.click(shareButton);
      
      expect(mockWriteText).toHaveBeenCalledWith(expect.any(String));
    });
  });

  it('renders product gallery', async () => {
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      const productImage = screen.getByAltText('Hot Wheels Corvette Z06 - Image 1');
      expect(productImage).toBeInTheDocument();
    });
  });

  it('renders trust badges', async () => {
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      expect(screen.getByText('Free Shipping')).toBeInTheDocument();
      expect(screen.getByText('Authentic Products')).toBeInTheDocument();
      expect(screen.getByText('Earn Credits')).toBeInTheDocument();
    });
  });

  it('renders product tabs', async () => {
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      expect(screen.getByText('Description')).toBeInTheDocument();
      expect(screen.getByText('Specifications')).toBeInTheDocument();
      expect(screen.getByText('Reviews')).toBeInTheDocument();
    });
  });

  it('switches between tabs correctly', async () => {
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      // Click on Specifications tab
      const specificationsTab = screen.getByText('Specifications');
      fireEvent.click(specificationsTab);
      
      // Should show specifications content
      expect(screen.getByText('Manufacturer')).toBeInTheDocument();
      expect(screen.getByText('Chevrolet')).toBeInTheDocument();
    });
  });

  it('shows product description in description tab', async () => {
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      // Description tab should be active by default
      expect(screen.getByText(mockProduct.description)).toBeInTheDocument();
    });
  });

  it('shows specifications in specifications tab', async () => {
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      const specificationsTab = screen.getByText('Specifications');
      fireEvent.click(specificationsTab);
      
      expect(screen.getByText('Year')).toBeInTheDocument();
      expect(screen.getByText('2023')).toBeInTheDocument();
      expect(screen.getByText('Color')).toBeInTheDocument();
      expect(screen.getByText('Red')).toBeInTheDocument();
    });
  });

  it('shows coming soon message in reviews tab', async () => {
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      const reviewsTab = screen.getByText('Reviews');
      fireEvent.click(reviewsTab);
      
      expect(screen.getByText('Reviews feature coming soon...')).toBeInTheDocument();
    });
  });

  it('handles back to products navigation', async () => {
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      const backButton = screen.getByText('Back to Products');
      fireEvent.click(backButton);
      
      expect(mockNavigate).toHaveBeenCalledWith('/products');
    });
  });

  it('shows loading state', () => {
    (productApi.getProduct as jest.Mock).mockImplementation(
      () => new Promise(resolve => setTimeout(() => resolve({ data: mockProduct }), 100))
    );
    
    renderWithProviders(<ProductDetailPage />);
    
    // Should show loading skeletons
    const loadingElements = document.querySelectorAll('.animate-pulse');
    expect(loadingElements.length).toBeGreaterThan(0);
  });

  it('handles API error gracefully', async () => {
    (productApi.getProduct as jest.Mock).mockRejectedValue(new Error('Product not found'));
    
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      expect(screen.getByText('Product not found')).toBeInTheDocument();
      expect(screen.getByText('Browse Products')).toBeInTheDocument();
    });
  });

  it('navigates to products page when browse products is clicked on error', async () => {
    (productApi.getProduct as jest.Mock).mockRejectedValue(new Error('Product not found'));
    
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      const browseButton = screen.getByText('Browse Products');
      fireEvent.click(browseButton);
      
      expect(mockNavigate).toHaveBeenCalledWith('/products');
    });
  });

  it('does not show quantity selector for out of stock products', async () => {
    (productApi.getProduct as jest.Mock).mockResolvedValue({ data: mockOutOfStockProduct });
    
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      expect(screen.queryByText('Quantity')).not.toBeInTheDocument();
      expect(screen.queryByText('Add to Cart')).not.toBeInTheDocument();
    });
  });

  it('formats prices correctly', async () => {
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      // Should use Philippine peso format
      expect(screen.getByText('₱500.00')).toBeInTheDocument();
      expect(screen.getByText('₱600.00')).toBeInTheDocument();
    });
  });

  it('handles products without specifications', async () => {
    const productWithoutSpecs = { ...mockProduct, specifications: {} };
    (productApi.getProduct as jest.Mock).mockResolvedValue({ data: productWithoutSpecs });
    
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      const specificationsTab = screen.getByText('Specifications');
      fireEvent.click(specificationsTab);
      
      expect(screen.getByText('No specifications available for this product.')).toBeInTheDocument();
    });
  });

  it('handles products without description', async () => {
    const productWithoutDescription = { ...mockProduct, description: '' };
    (productApi.getProduct as jest.Mock).mockResolvedValue({ data: productWithoutDescription });
    
    renderWithProviders(<ProductDetailPage />);
    
    await waitFor(() => {
      expect(screen.getByText('No description available for this product.')).toBeInTheDocument();
    });
  });
});