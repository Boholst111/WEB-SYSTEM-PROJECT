import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import CategoryBrowser from '../CategoryBrowser';
import { productApi } from '../../services/api';
import { Category } from '../../types';

// Mock the API
jest.mock('../../services/api', () => ({
  productApi: {
    getCategories: jest.fn(),
  },
}));

const mockCategories: Category[] = [
  { id: 1, name: 'Cars', slug: 'cars', isActive: true },
  { id: 2, name: 'Trucks', slug: 'trucks', isActive: true },
  { id: 3, name: 'Motorcycles', slug: 'motorcycles', isActive: true },
  { id: 4, name: 'Sports Cars', slug: 'sports-cars', parentId: 1, isActive: true },
  { id: 5, name: 'Sedans', slug: 'sedans', parentId: 1, isActive: true },
  { id: 6, name: 'Fire Trucks', slug: 'fire-trucks', parentId: 2, isActive: true },
  { id: 7, name: 'Pickup Trucks', slug: 'pickup-trucks', parentId: 2, isActive: true },
];

describe('CategoryBrowser', () => {
  const defaultProps = {
    selectedCategoryId: undefined,
    onCategorySelect: jest.fn(),
  };

  beforeEach(() => {
    jest.clearAllMocks();
    (productApi.getCategories as jest.Mock).mockResolvedValue({ data: mockCategories });
  });

  it('renders category browser with title', async () => {
    render(<CategoryBrowser {...defaultProps} />);
    
    await waitFor(() => {
      expect(screen.getByText('Categories')).toBeInTheDocument();
    });
  });

  it('loads and displays categories correctly', async () => {
    render(<CategoryBrowser {...defaultProps} />);
    
    await waitFor(() => {
      expect(screen.getByText('Cars')).toBeInTheDocument();
      expect(screen.getByText('Trucks')).toBeInTheDocument();
      expect(screen.getByText('Motorcycles')).toBeInTheDocument();
    });
  });

  it('shows loading state initially', () => {
    render(<CategoryBrowser {...defaultProps} />);
    
    // Should show loading skeletons
    const loadingElements = document.querySelectorAll('.animate-pulse');
    expect(loadingElements.length).toBeGreaterThan(0);
  });

  it('calls onCategorySelect when category is clicked', async () => {
    const mockOnCategorySelect = jest.fn();
    render(<CategoryBrowser {...defaultProps} onCategorySelect={mockOnCategorySelect} />);
    
    await waitFor(() => {
      const carsOption = screen.getByText('Cars');
      fireEvent.click(carsOption);
    });
    
    expect(mockOnCategorySelect).toHaveBeenCalledWith(1);
  });

  it('calls onCategorySelect with undefined when "All Categories" is clicked', async () => {
    const mockOnCategorySelect = jest.fn();
    render(<CategoryBrowser {...defaultProps} onCategorySelect={mockOnCategorySelect} />);
    
    await waitFor(() => {
      const allCategoriesOption = screen.getByText('All Categories');
      fireEvent.click(allCategoriesOption);
    });
    
    expect(mockOnCategorySelect).toHaveBeenCalledWith(undefined);
  });

  it('highlights selected category', async () => {
    render(<CategoryBrowser {...defaultProps} selectedCategoryId={1} />);
    
    await waitFor(() => {
      const carsOption = screen.getByText('Cars').closest('div');
      expect(carsOption).toHaveClass('bg-blue-100', 'text-blue-800');
    });
  });

  it('highlights "All Categories" when no category is selected', async () => {
    render(<CategoryBrowser {...defaultProps} selectedCategoryId={undefined} />);
    
    await waitFor(() => {
      const allCategoriesOption = screen.getByText('All Categories').closest('div');
      expect(allCategoriesOption).toHaveClass('bg-blue-100', 'text-blue-800');
    });
  });

  it('builds category tree structure correctly', async () => {
    render(<CategoryBrowser {...defaultProps} />);
    
    await waitFor(() => {
      // Parent categories should be visible
      expect(screen.getByText('Cars')).toBeInTheDocument();
      expect(screen.getByText('Trucks')).toBeInTheDocument();
      
      // Child categories should not be visible initially (collapsed)
      expect(screen.queryByText('Sports Cars')).not.toBeInTheDocument();
      expect(screen.queryByText('Fire Trucks')).not.toBeInTheDocument();
    });
  });

  it('expands category when expand button is clicked', async () => {
    render(<CategoryBrowser {...defaultProps} />);
    
    await waitFor(() => {
      // Find the expand button for Cars category
      const carsRow = screen.getByText('Cars').closest('div');
      const expandButton = carsRow?.querySelector('button');
      
      if (expandButton) {
        fireEvent.click(expandButton);
      }
    });
    
    await waitFor(() => {
      // Child categories should now be visible
      expect(screen.getByText('Sports Cars')).toBeInTheDocument();
      expect(screen.getByText('Sedans')).toBeInTheDocument();
    });
  });

  it('collapses category when collapse button is clicked', async () => {
    render(<CategoryBrowser {...defaultProps} />);
    
    await waitFor(() => {
      // First expand Cars category
      const carsRow = screen.getByText('Cars').closest('div');
      const expandButton = carsRow?.querySelector('button');
      
      if (expandButton) {
        fireEvent.click(expandButton);
      }
    });
    
    await waitFor(() => {
      expect(screen.getByText('Sports Cars')).toBeInTheDocument();
    });
    
    // Now collapse it
    await waitFor(() => {
      const carsRow = screen.getByText('Cars').closest('div');
      const collapseButton = carsRow?.querySelector('button');
      
      if (collapseButton) {
        fireEvent.click(collapseButton);
      }
    });
    
    await waitFor(() => {
      expect(screen.queryByText('Sports Cars')).not.toBeInTheDocument();
    });
  });

  it('shows expand/collapse icons correctly', async () => {
    render(<CategoryBrowser {...defaultProps} />);
    
    await waitFor(() => {
      // Should show right chevron for collapsed categories with children
      const carsRow = screen.getByText('Cars').closest('div');
      const rightChevron = carsRow?.querySelector('svg');
      expect(rightChevron).toBeInTheDocument();
    });
  });

  it('does not show expand button for categories without children', async () => {
    render(<CategoryBrowser {...defaultProps} />);
    
    await waitFor(() => {
      // Motorcycles has no children, so no expand button
      const motorcyclesRow = screen.getByText('Motorcycles').closest('div');
      const expandButton = motorcyclesRow?.querySelector('button');
      expect(expandButton).toBeNull();
    });
  });

  it('shows clear button when category is selected', async () => {
    render(<CategoryBrowser {...defaultProps} selectedCategoryId={1} />);
    
    await waitFor(() => {
      expect(screen.getByText('Clear')).toBeInTheDocument();
    });
  });

  it('calls onCategorySelect with undefined when clear button is clicked', async () => {
    const mockOnCategorySelect = jest.fn();
    render(<CategoryBrowser {...defaultProps} selectedCategoryId={1} onCategorySelect={mockOnCategorySelect} />);
    
    await waitFor(() => {
      const clearButton = screen.getByText('Clear');
      fireEvent.click(clearButton);
    });
    
    expect(mockOnCategorySelect).toHaveBeenCalledWith(undefined);
  });

  it('handles API error gracefully', async () => {
    (productApi.getCategories as jest.Mock).mockRejectedValue(new Error('API Error'));
    
    render(<CategoryBrowser {...defaultProps} />);
    
    await waitFor(() => {
      expect(screen.getByText('Failed to load categories')).toBeInTheDocument();
      expect(screen.getByText('Try again')).toBeInTheDocument();
    });
  });

  it('retries loading categories when try again is clicked', async () => {
    (productApi.getCategories as jest.Mock).mockRejectedValueOnce(new Error('API Error'));
    (productApi.getCategories as jest.Mock).mockResolvedValueOnce({ data: mockCategories });
    
    render(<CategoryBrowser {...defaultProps} />);
    
    await waitFor(() => {
      const tryAgainButton = screen.getByText('Try again');
      fireEvent.click(tryAgainButton);
    });
    
    await waitFor(() => {
      expect(screen.getByText('Cars')).toBeInTheDocument();
    });
  });

  it('shows no categories message when no categories are available', async () => {
    (productApi.getCategories as jest.Mock).mockResolvedValue({ data: [] });
    
    render(<CategoryBrowser {...defaultProps} />);
    
    await waitFor(() => {
      expect(screen.getByText('No categories available')).toBeInTheDocument();
    });
  });

  it('toggles category selection when same category is clicked', async () => {
    const mockOnCategorySelect = jest.fn();
    render(<CategoryBrowser {...defaultProps} selectedCategoryId={1} onCategorySelect={mockOnCategorySelect} />);
    
    await waitFor(() => {
      const carsOption = screen.getByText('Cars');
      fireEvent.click(carsOption);
    });
    
    expect(mockOnCategorySelect).toHaveBeenCalledWith(undefined);
  });

  it('applies custom className', async () => {
    render(<CategoryBrowser {...defaultProps} className="custom-class" />);
    
    await waitFor(() => {
      const container = document.querySelector('.custom-class');
      expect(container).toBeInTheDocument();
    });
  });

  it('handles scroll for many categories', async () => {
    render(<CategoryBrowser {...defaultProps} />);
    
    await waitFor(() => {
      const categoryList = document.querySelector('.max-h-64.overflow-y-auto');
      expect(categoryList).toBeInTheDocument();
    });
  });

  it('prevents event propagation when expand button is clicked', async () => {
    const mockOnCategorySelect = jest.fn();
    render(<CategoryBrowser {...defaultProps} onCategorySelect={mockOnCategorySelect} />);
    
    await waitFor(() => {
      const carsRow = screen.getByText('Cars').closest('div');
      const expandButton = carsRow?.querySelector('button');
      
      if (expandButton) {
        fireEvent.click(expandButton);
      }
    });
    
    // onCategorySelect should not be called when expand button is clicked
    expect(mockOnCategorySelect).not.toHaveBeenCalled();
  });

  it('shows proper indentation for nested categories', async () => {
    render(<CategoryBrowser {...defaultProps} />);
    
    await waitFor(() => {
      // Expand Cars category
      const carsRow = screen.getByText('Cars').closest('div');
      const expandButton = carsRow?.querySelector('button');
      
      if (expandButton) {
        fireEvent.click(expandButton);
      }
    });
    
    await waitFor(() => {
      // Child categories should have proper indentation
      const sportsCarRow = screen.getByText('Sports Cars').closest('div');
      expect(sportsCarRow).toHaveStyle('padding-left: 32px'); // 12 + 1 * 20
    });
  });

  it('handles categories with multiple nesting levels', async () => {
    const nestedCategories = [
      ...mockCategories,
      { id: 8, name: 'Luxury Sports Cars', slug: 'luxury-sports-cars', parentId: 4, isActive: true },
    ];
    
    (productApi.getCategories as jest.Mock).mockResolvedValue({ data: nestedCategories });
    
    render(<CategoryBrowser {...defaultProps} />);
    
    await waitFor(() => {
      // Expand Cars
      const carsRow = screen.getByText('Cars').closest('div');
      const expandButton = carsRow?.querySelector('button');
      if (expandButton) fireEvent.click(expandButton);
    });
    
    await waitFor(() => {
      // Expand Sports Cars
      const sportsCarRow = screen.getByText('Sports Cars').closest('div');
      const expandButton = sportsCarRow?.querySelector('button');
      if (expandButton) fireEvent.click(expandButton);
    });
    
    await waitFor(() => {
      expect(screen.getByText('Luxury Sports Cars')).toBeInTheDocument();
    });
  });
});