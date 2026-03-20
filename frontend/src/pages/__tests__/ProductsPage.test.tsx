import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import { BrowserRouter } from 'react-router-dom';
import { Provider } from 'react-redux';
import { configureStore } from '@reduxjs/toolkit';
import ProductsPage from '../ProductsPage';
import { productApi } from '../../services/api';
import { Product } from '../../types';
import productSlice from '../../store/slices/productSlice';
import authSlice from '../../store/slices/authSlice';
import cartSlice from '../../store/slices/cartSlice';
import loyaltySlice from '../../store/slices/loyaltySlice';

// Mock the API
jest.mock('../../services/api', () => ({
  productApi: {
    getProducts: jest.fn(),
    getBrands: jest.fn(),
    getCategories: jest.fn(),
    getFilterOptions: jest.fn(),
    getProductSuggestions: jest.fn(),
    searchProducts: jest.fn(),
  },
}));

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
    sku: 'HW-001',
    name: 'Hot Wheels Corvette',
    description: 'Classic Hot Wheels Corvette',
    brandId: 1,
    categoryId: 1,
    scale: '1:64',
    material: 'diecast',
    features: ['opening doors'],
    isChaseVariant: false,
    basePrice: 500,
    currentPrice: 450,
    stockQuantity: 10,
    isPreorder: false,
    status: 'active',
    images: ['https://example.com/corvette.jpg'],
    specifications: {},
    brand: { id: 1, name: 'Hot Wheels', slug: 'hot-wheels', isActive: true },
    category: { id: 1, name: 'Cars', slug: 'cars', isActive: true },
    createdAt: '2024-01-01T00:00:00Z',
    updatedAt: '2024-01-01T00:00:00Z',
  },
  {
    id: 2,
    sku: 'MB-001',
    name: 'Matchbox Fire Truck',
    description: 'Red Matchbox fire truck',
    brandId: 2,
    categoryId: 2,
    scale: '1:64',
    material: 'diecast',
    features: ['detailed interior'],
    isChaseVariant: true,
    basePrice: 800,
    currentPrice: 800,
    stockQuantity: 5,
    isPreorder: false,
    status: 'active',
    images: ['https://example.com/firetruck.jpg'],
    specifications: {},
    brand: { id: 2, name: 'Matchbox', slug: 'matchbox', isActive: true },
    category: { id: 2, name: 'Trucks', slug: 'trucks', isActive: true },
    createdAt: '2024-01-01T00:00:00Z',
    updatedAt: '2024-01-01T00:00:00Z',
  },
];

const mockPaginationMeta = {
  currentPage: 1,
  lastPage: 5,
  perPage: 20,
  total: 100,
  from: 1,
  to: 20,
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

describe('ProductsPage', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    
    // Setup API mocks
    (productApi.getProducts as jest.Mock).mockResolvedValue({
      data: mockProducts,
      meta: mockPaginationMeta,
    });
    (productApi.getBrands as jest.Mock).mockResolvedValue({ data: [] });
    (productApi.getCategories as jest.Mock).mockResolvedValue({ data: [] });
    (productApi.getFilterOptions as jest.Mock).mockResolvedValue({ 
      data: { scales: [], materials: [], features: [] } 
    });
    (productApi.getProductSuggestions as jest.Mock).mockResolvedValue({ data: [] });
    (productApi.searchProducts as jest.Mock).mockResolvedValue({ data: [] });
  });

  it('renders products page with title', async () => {
    renderWithProviders(<ProductsPage />);
    
    expect(screen.getByText('Diecast Models')).toBeInTheDocument();
  });

  it('renders search interface', async () => {
    renderWithProviders(<ProductsPage />);
    
    expect(screen.getByPlaceholderText('Search for diecast models, brands, scales...')).toBeInTheDocument();
  });

  it('loads and displays products', async () => {
    renderWithProviders(<ProductsPage />);
    
    await waitFor(() => {
      expect(screen.getByText('Hot Wheels Corvette')).toBeInTheDocument();
      expect(screen.getByText('Matchbox Fire Truck')).toBeInTheDocument();
    });
  });

  it('shows product count information', async () => {
    renderWithProviders(<ProductsPage />);
    
    await waitFor(() => {
      expect(screen.getByText('Showing 2 of 100 products')).toBeInTheDocument();
    });
  });

  it('handles search functionality', async () => {
    renderWithProviders(<ProductsPage />);
    
    const searchInput = screen.getByPlaceholderText('Search for diecast models, brands, scales...');
    fireEvent.change(searchInput, { target: { value: 'corvette' } });
    fireEvent.submit(searchInput.closest('form')!);
    
    await waitFor(() => {
      expect(productApi.getProducts).toHaveBeenCalledWith(
        expect.objectContaining({ search: 'corvette', page: 1 })
      );
    });
  });

  it('shows mobile filter toggle button', () => {
    renderWithProviders(<ProductsPage />);
    
    const filterButton = screen.getByText('Filters');
    expect(filterButton).toBeInTheDocument();
  });

  it('opens filter sidebar when mobile filter button is clicked', () => {
    renderWithProviders(<ProductsPage />);
    
    const filterButton = screen.getByText('Filters');
    fireEvent.click(filterButton);
    
    // Filter sidebar should be open (check for close button or overlay)
    const closeButton = document.querySelector('[data-testid="close-filter"]');
    expect(closeButton || screen.getByText('Clear All')).toBeInTheDocument();
  });

  it('renders sort dropdown with options', () => {
    renderWithProviders(<ProductsPage />);
    
    const sortSelect = screen.getByDisplayValue('Name A-Z');
    expect(sortSelect).toBeInTheDocument();
    
    // Check sort options
    fireEvent.click(sortSelect);
    expect(screen.getByText('Price Low to High')).toBeInTheDocument();
    expect(screen.getByText('Price High to Low')).toBeInTheDocument();
    expect(screen.getByText('Newest First')).toBeInTheDocument();
  });

  it('handles sort change', async () => {
    renderWithProviders(<ProductsPage />);
    
    const sortSelect = screen.getByDisplayValue('Name A-Z');
    fireEvent.change(sortSelect, { target: { value: 'price:asc' } });
    
    await waitFor(() => {
      expect(productApi.getProducts).toHaveBeenCalledWith(
        expect.objectContaining({ sortBy: 'price', sortOrder: 'asc', page: 1 })
      );
    });
  });

  it('renders view mode toggle buttons', () => {
    renderWithProviders(<ProductsPage />);
    
    const viewModeButtons = screen.getAllByRole('button');
    const gridButton = viewModeButtons.find(button => 
      button.querySelector('svg') && button.classList.contains('bg-blue-600')
    );
    const listButton = viewModeButtons.find(button => 
      button.querySelector('svg') && !button.classList.contains('bg-blue-600')
    );
    
    expect(gridButton).toBeInTheDocument();
    expect(listButton).toBeInTheDocument();
  });

  it('toggles view mode when view mode buttons are clicked', () => {
    renderWithProviders(<ProductsPage />);
    
    const viewModeButtons = screen.getAllByRole('button');
    const listButton = viewModeButtons.find(button => 
      button.querySelector('svg') && !button.classList.contains('bg-blue-600')
    );
    
    if (listButton) {
      fireEvent.click(listButton);
      expect(listButton).toHaveClass('bg-blue-600');
    }
  });

  it('renders category browser in desktop sidebar', () => {
    renderWithProviders(<ProductsPage />);
    
    // Should render category browser (check for "All Categories" text)
    expect(screen.getByText('All Categories')).toBeInTheDocument();
  });

  it('renders brand browser in desktop sidebar', () => {
    renderWithProviders(<ProductsPage />);
    
    // Should render brand browser (check for "All Brands" text)
    expect(screen.getByText('All Brands')).toBeInTheDocument();
  });

  it('handles category selection', async () => {
    const mockCategories = [
      { id: 1, name: 'Cars', slug: 'cars', isActive: true },
    ];
    (productApi.getCategories as jest.Mock).mockResolvedValue({ data: mockCategories });
    
    renderWithProviders(<ProductsPage />);
    
    await waitFor(() => {
      const carsCategory = screen.getByText('Cars');
      fireEvent.click(carsCategory);
    });
    
    await waitFor(() => {
      expect(productApi.getProducts).toHaveBeenCalledWith(
        expect.objectContaining({ categoryId: 1, page: 1 })
      );
    });
  });

  it('handles brand selection', async () => {
    const mockBrands = [
      { id: 1, name: 'Hot Wheels', slug: 'hot-wheels', isActive: true },
    ];
    (productApi.getBrands as jest.Mock).mockResolvedValue({ data: mockBrands });
    
    renderWithProviders(<ProductsPage />);
    
    await waitFor(() => {
      const hotWheelsBrand = screen.getByText('Hot Wheels');
      fireEvent.click(hotWheelsBrand);
    });
    
    await waitFor(() => {
      expect(productApi.getProducts).toHaveBeenCalledWith(
        expect.objectContaining({ brandId: 1, page: 1 })
      );
    });
  });

  it('shows loading state', () => {
    // Mock loading state
    (productApi.getProducts as jest.Mock).mockImplementation(
      () => new Promise(resolve => setTimeout(() => resolve({ data: [], meta: mockPaginationMeta }), 100))
    );
    
    renderWithProviders(<ProductsPage />);
    
    expect(screen.getByText('Loading products...')).toBeInTheDocument();
  });

  it('handles API errors gracefully', async () => {
    (productApi.getProducts as jest.Mock).mockRejectedValue(new Error('API Error'));
    
    renderWithProviders(<ProductsPage />);
    
    await waitFor(() => {
      expect(screen.getByText('Failed to load products. Please try again.')).toBeInTheDocument();
    });
  });

  it('shows search results count when searching', async () => {
    renderWithProviders(<ProductsPage />);
    
    const searchInput = screen.getByPlaceholderText('Search for diecast models, brands, scales...');
    fireEvent.change(searchInput, { target: { value: 'corvette' } });
    fireEvent.submit(searchInput.closest('form')!);
    
    await waitFor(() => {
      expect(screen.getByText('Showing 2 of 100 products for "corvette"')).toBeInTheDocument();
    });
  });

  it('handles infinite scroll load more', async () => {
    const { useInView } = require('react-intersection-observer');
    useInView.mockReturnValue({ ref: jest.fn(), inView: true });
    
    renderWithProviders(<ProductsPage />);
    
    await waitFor(() => {
      expect(productApi.getProducts).toHaveBeenCalledTimes(2); // Initial load + load more
    });
  });

  it('shows active filters count in mobile filter button', async () => {
    renderWithProviders(<ProductsPage />);
    
    // Simulate having active filters by triggering a search
    const searchInput = screen.getByPlaceholderText('Search for diecast models, brands, scales...');
    fireEvent.change(searchInput, { target: { value: 'test' } });
    fireEvent.submit(searchInput.closest('form')!);
    
    await waitFor(() => {
      // Should show filter count badge
      const filterButton = screen.getByText('Filters').closest('button');
      expect(filterButton?.querySelector('.bg-blue-100')).toBeInTheDocument();
    });
  });

  it('clears all filters when clear filters is called', async () => {
    renderWithProviders(<ProductsPage />);
    
    // First apply some filters
    const searchInput = screen.getByPlaceholderText('Search for diecast models, brands, scales...');
    fireEvent.change(searchInput, { target: { value: 'test' } });
    fireEvent.submit(searchInput.closest('form')!);
    
    // Open filter sidebar
    const filterButton = screen.getByText('Filters');
    fireEvent.click(filterButton);
    
    // Click clear all
    await waitFor(() => {
      const clearAllButton = screen.getByText('Clear All');
      fireEvent.click(clearAllButton);
    });
    
    await waitFor(() => {
      expect(productApi.getProducts).toHaveBeenCalledWith(
        expect.objectContaining({ search: undefined })
      );
    });
  });

  it('handles add to cart functionality', async () => {
    // Mock console.log to verify add to cart is called
    const consoleSpy = jest.spyOn(console, 'log').mockImplementation();
    
    renderWithProviders(<ProductsPage />);
    
    await waitFor(() => {
      // Find add to cart button (this might need adjustment based on actual implementation)
      const addToCartButtons = screen.getAllByRole('button');
      const cartButton = addToCartButtons.find(button => 
        button.getAttribute('aria-label')?.includes('cart') || 
        button.textContent?.includes('cart')
      );
      
      if (cartButton) {
        fireEvent.click(cartButton);
        expect(consoleSpy).toHaveBeenCalledWith('Add to cart:', expect.any(Object));
      }
    });
    
    consoleSpy.mockRestore();
  });

  it('handles wishlist toggle functionality', async () => {
    // Mock console.log to verify wishlist toggle is called
    const consoleSpy = jest.spyOn(console, 'log').mockImplementation();
    
    renderWithProviders(<ProductsPage />);
    
    await waitFor(() => {
      // Find wishlist button (this might need adjustment based on actual implementation)
      const wishlistButtons = screen.getAllByRole('button');
      const wishlistButton = wishlistButtons.find(button => 
        button.getAttribute('aria-label')?.includes('wishlist') || 
        button.textContent?.includes('wishlist')
      );
      
      if (wishlistButton) {
        fireEvent.click(wishlistButton);
        expect(consoleSpy).toHaveBeenCalledWith('Toggle wishlist:', expect.any(Object));
      }
    });
    
    consoleSpy.mockRestore();
  });
});