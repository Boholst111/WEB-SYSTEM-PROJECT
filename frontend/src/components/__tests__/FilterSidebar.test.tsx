import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import FilterSidebar from '../FilterSidebar';
import { ProductFilters, Brand, Category } from '../../types';
import { productApi } from '../../services/api';

// Mock the API
jest.mock('../../services/api', () => ({
  productApi: {
    getBrands: jest.fn(),
    getCategories: jest.fn(),
    getFilterOptions: jest.fn(),
  },
}));

const mockBrands: Brand[] = [
  { id: 1, name: 'Hot Wheels', slug: 'hot-wheels', isActive: true },
  { id: 2, name: 'Matchbox', slug: 'matchbox', isActive: true },
  { id: 3, name: 'Tomica', slug: 'tomica', isActive: true },
];

const mockCategories: Category[] = [
  { id: 1, name: 'Cars', slug: 'cars', isActive: true },
  { id: 2, name: 'Trucks', slug: 'trucks', isActive: true },
  { id: 3, name: 'Motorcycles', slug: 'motorcycles', isActive: true },
];

const mockFilterOptions = {
  scales: ['1:64', '1:43', '1:24', '1:18'],
  materials: ['diecast', 'plastic', 'resin'],
  features: ['opening doors', 'detailed interior', 'rubber tires', 'working lights'],
};

const mockFilters: ProductFilters = {
  search: '',
  categoryId: undefined,
  brandId: undefined,
  scale: [],
  material: [],
  features: [],
  isChaseVariant: undefined,
  isPreorder: undefined,
  minPrice: undefined,
  maxPrice: undefined,
  inStock: undefined,
  sortBy: 'name',
  sortOrder: 'asc',
  page: 1,
  limit: 20,
};

describe('FilterSidebar', () => {
  const defaultProps = {
    filters: mockFilters,
    onFiltersChange: jest.fn(),
    onClearFilters: jest.fn(),
    isOpen: true,
    onClose: jest.fn(),
  };

  beforeEach(() => {
    jest.clearAllMocks();
    
    // Setup API mocks
    (productApi.getBrands as jest.Mock).mockResolvedValue({ data: mockBrands });
    (productApi.getCategories as jest.Mock).mockResolvedValue({ data: mockCategories });
    (productApi.getFilterOptions as jest.Mock).mockResolvedValue({ data: mockFilterOptions });
  });

  it('renders filter sidebar with all sections', async () => {
    render(<FilterSidebar {...defaultProps} />);
    
    await waitFor(() => {
      expect(screen.getByText('Filters')).toBeInTheDocument();
      expect(screen.getByText('Category')).toBeInTheDocument();
      expect(screen.getByText('Brand')).toBeInTheDocument();
      expect(screen.getByText('Scale')).toBeInTheDocument();
      expect(screen.getByText('Material')).toBeInTheDocument();
      expect(screen.getByText('Features')).toBeInTheDocument();
      expect(screen.getByText('Price Range')).toBeInTheDocument();
      expect(screen.getByText('Availability')).toBeInTheDocument();
    });
  });

  it('loads and displays brands correctly', async () => {
    render(<FilterSidebar {...defaultProps} />);
    
    await waitFor(() => {
      expect(screen.getByText('Hot Wheels')).toBeInTheDocument();
      expect(screen.getByText('Matchbox')).toBeInTheDocument();
      expect(screen.getByText('Tomica')).toBeInTheDocument();
    });
  });

  it('loads and displays categories correctly', async () => {
    render(<FilterSidebar {...defaultProps} />);
    
    await waitFor(() => {
      expect(screen.getByText('Cars')).toBeInTheDocument();
      expect(screen.getByText('Trucks')).toBeInTheDocument();
      expect(screen.getByText('Motorcycles')).toBeInTheDocument();
    });
  });

  it('calls onFiltersChange when category is selected', async () => {
    const mockOnFiltersChange = jest.fn();
    render(<FilterSidebar {...defaultProps} onFiltersChange={mockOnFiltersChange} />);
    
    await waitFor(() => {
      const carsOption = screen.getByText('Cars');
      fireEvent.click(carsOption);
    });
    
    expect(mockOnFiltersChange).toHaveBeenCalledWith({ categoryId: 1 });
  });

  it('calls onFiltersChange when brand is selected', async () => {
    const mockOnFiltersChange = jest.fn();
    render(<FilterSidebar {...defaultProps} onFiltersChange={mockOnFiltersChange} />);
    
    await waitFor(() => {
      const hotWheelsOption = screen.getByText('Hot Wheels');
      fireEvent.click(hotWheelsOption);
    });
    
    expect(mockOnFiltersChange).toHaveBeenCalledWith({ brandId: 1 });
  });

  it('handles scale filter selection', async () => {
    const mockOnFiltersChange = jest.fn();
    render(<FilterSidebar {...defaultProps} onFiltersChange={mockOnFiltersChange} />);
    
    // Expand scale section first
    await waitFor(() => {
      const scaleSection = screen.getByText('Scale');
      fireEvent.click(scaleSection);
    });
    
    await waitFor(() => {
      const scale164 = screen.getByText('1:64');
      fireEvent.click(scale164);
    });
    
    expect(mockOnFiltersChange).toHaveBeenCalledWith({ scale: ['1:64'] });
  });

  it('handles material filter selection', async () => {
    const mockOnFiltersChange = jest.fn();
    render(<FilterSidebar {...defaultProps} onFiltersChange={mockOnFiltersChange} />);
    
    // Expand material section first
    await waitFor(() => {
      const materialSection = screen.getByText('Material');
      fireEvent.click(materialSection);
    });
    
    await waitFor(() => {
      const diecastOption = screen.getByText('diecast');
      fireEvent.click(diecastOption);
    });
    
    expect(mockOnFiltersChange).toHaveBeenCalledWith({ material: ['diecast'] });
  });

  it('handles features filter selection', async () => {
    const mockOnFiltersChange = jest.fn();
    render(<FilterSidebar {...defaultProps} onFiltersChange={mockOnFiltersChange} />);
    
    // Expand features section first
    await waitFor(() => {
      const featuresSection = screen.getByText('Features');
      fireEvent.click(featuresSection);
    });
    
    await waitFor(() => {
      const openingDoorsOption = screen.getByText('opening doors');
      fireEvent.click(openingDoorsOption);
    });
    
    expect(mockOnFiltersChange).toHaveBeenCalledWith({ features: ['opening doors'] });
  });

  it('handles price range input', async () => {
    const mockOnFiltersChange = jest.fn();
    render(<FilterSidebar {...defaultProps} onFiltersChange={mockOnFiltersChange} />);
    
    // Expand price section first
    await waitFor(() => {
      const priceSection = screen.getByText('Price Range');
      fireEvent.click(priceSection);
    });
    
    await waitFor(() => {
      const minPriceInput = screen.getByPlaceholderText('₱0');
      fireEvent.change(minPriceInput, { target: { value: '100' } });
    });
    
    expect(mockOnFiltersChange).toHaveBeenCalledWith({ minPrice: 100 });
  });

  it('handles availability filters', async () => {
    const mockOnFiltersChange = jest.fn();
    render(<FilterSidebar {...defaultProps} onFiltersChange={mockOnFiltersChange} />);
    
    // Expand availability section first
    await waitFor(() => {
      const availabilitySection = screen.getByText('Availability');
      fireEvent.click(availabilitySection);
    });
    
    await waitFor(() => {
      const inStockCheckbox = screen.getByText('In Stock Only');
      fireEvent.click(inStockCheckbox);
    });
    
    expect(mockOnFiltersChange).toHaveBeenCalledWith({ inStock: true });
  });

  it('calls onClearFilters when clear all is clicked', async () => {
    const mockOnClearFilters = jest.fn();
    render(<FilterSidebar {...defaultProps} onClearFilters={mockOnClearFilters} />);
    
    const clearAllButton = screen.getByText('Clear All');
    fireEvent.click(clearAllButton);
    
    expect(mockOnClearFilters).toHaveBeenCalled();
  });

  it('calls onClose when close button is clicked', () => {
    const mockOnClose = jest.fn();
    render(<FilterSidebar {...defaultProps} onClose={mockOnClose} />);
    
    const closeButton = screen.getByRole('button', { name: /close/i });
    fireEvent.click(closeButton);
    
    expect(mockOnClose).toHaveBeenCalled();
  });

  it('shows active filters count', async () => {
    const filtersWithSelections: ProductFilters = {
      ...mockFilters,
      categoryId: 1,
      brandId: 1,
      scale: ['1:64'],
    };
    
    render(<FilterSidebar {...defaultProps} filters={filtersWithSelections} />);
    
    await waitFor(() => {
      expect(screen.getByText('3')).toBeInTheDocument(); // Active filters count badge
    });
  });

  it('toggles filter sections open and closed', async () => {
    render(<FilterSidebar {...defaultProps} />);
    
    await waitFor(() => {
      const materialSection = screen.getByText('Material');
      fireEvent.click(materialSection);
    });
    
    // Material section should now be expanded and show options
    await waitFor(() => {
      expect(screen.getByText('diecast')).toBeInTheDocument();
    });
  });

  it('handles API errors gracefully', async () => {
    (productApi.getBrands as jest.Mock).mockRejectedValue(new Error('API Error'));
    (productApi.getCategories as jest.Mock).mockRejectedValue(new Error('API Error'));
    (productApi.getFilterOptions as jest.Mock).mockRejectedValue(new Error('API Error'));
    
    render(<FilterSidebar {...defaultProps} />);
    
    // Should still render the sidebar structure even with API errors
    expect(screen.getByText('Filters')).toBeInTheDocument();
  });

  it('handles mobile overlay click', () => {
    const mockOnClose = jest.fn();
    render(<FilterSidebar {...defaultProps} onClose={mockOnClose} />);
    
    const overlay = document.querySelector('.fixed.inset-0.bg-black');
    if (overlay) {
      fireEvent.click(overlay);
      expect(mockOnClose).toHaveBeenCalled();
    }
  });

  it('removes filter when already selected option is clicked', async () => {
    const mockOnFiltersChange = jest.fn();
    const filtersWithScale: ProductFilters = {
      ...mockFilters,
      scale: ['1:64'],
    };
    
    render(<FilterSidebar {...defaultProps} filters={filtersWithScale} onFiltersChange={mockOnFiltersChange} />);
    
    // Expand scale section
    await waitFor(() => {
      const scaleSection = screen.getByText('Scale');
      fireEvent.click(scaleSection);
    });
    
    // Click the already selected scale to remove it
    await waitFor(() => {
      const scale164 = screen.getByText('1:64');
      fireEvent.click(scale164);
    });
    
    expect(mockOnFiltersChange).toHaveBeenCalledWith({ scale: undefined });
  });
});