import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import BrandBrowser from '../BrandBrowser';
import { productApi } from '../../services/api';
import { Brand } from '../../types';

// Mock the API
jest.mock('../../services/api', () => ({
  productApi: {
    getBrands: jest.fn(),
  },
}));

const mockBrands: Brand[] = [
  { id: 1, name: 'Hot Wheels', slug: 'hot-wheels', isActive: true, logo: 'https://example.com/hw-logo.png' },
  { id: 2, name: 'Matchbox', slug: 'matchbox', isActive: true },
  { id: 3, name: 'Tomica', slug: 'tomica', isActive: true },
  { id: 4, name: 'Majorette', slug: 'majorette', isActive: true },
  { id: 5, name: 'Greenlight', slug: 'greenlight', isActive: true },
  { id: 6, name: 'Auto World', slug: 'auto-world', isActive: true },
  { id: 7, name: 'Johnny Lightning', slug: 'johnny-lightning', isActive: true },
  { id: 8, name: 'M2 Machines', slug: 'm2-machines', isActive: true },
  { id: 9, name: 'Racing Champions', slug: 'racing-champions', isActive: true },
  { id: 10, name: 'Maisto', slug: 'maisto', isActive: true },
  { id: 11, name: 'Bburago', slug: 'bburago', isActive: true },
  { id: 12, name: 'Kyosho', slug: 'kyosho', isActive: true },
];

describe('BrandBrowser', () => {
  const defaultProps = {
    selectedBrandId: undefined,
    onBrandSelect: jest.fn(),
  };

  beforeEach(() => {
    jest.clearAllMocks();
    (productApi.getBrands as jest.Mock).mockResolvedValue({ data: mockBrands });
  });

  it('renders brand browser with title', async () => {
    render(<BrandBrowser {...defaultProps} />);
    
    await waitFor(() => {
      expect(screen.getByText('Brands')).toBeInTheDocument();
    });
  });

  it('loads and displays brands correctly', async () => {
    render(<BrandBrowser {...defaultProps} />);
    
    await waitFor(() => {
      expect(screen.getByText('Hot Wheels')).toBeInTheDocument();
      expect(screen.getByText('Matchbox')).toBeInTheDocument();
      expect(screen.getByText('Tomica')).toBeInTheDocument();
    });
  });

  it('shows loading state initially', () => {
    render(<BrandBrowser {...defaultProps} />);
    
    // Should show loading skeletons
    const loadingElements = document.querySelectorAll('.animate-pulse');
    expect(loadingElements.length).toBeGreaterThan(0);
  });

  it('calls onBrandSelect when brand is clicked', async () => {
    const mockOnBrandSelect = jest.fn();
    render(<BrandBrowser {...defaultProps} onBrandSelect={mockOnBrandSelect} />);
    
    await waitFor(() => {
      const hotWheelsOption = screen.getByText('Hot Wheels');
      fireEvent.click(hotWheelsOption);
    });
    
    expect(mockOnBrandSelect).toHaveBeenCalledWith(1);
  });

  it('calls onBrandSelect with undefined when "All Brands" is clicked', async () => {
    const mockOnBrandSelect = jest.fn();
    render(<BrandBrowser {...defaultProps} onBrandSelect={mockOnBrandSelect} />);
    
    await waitFor(() => {
      const allBrandsOption = screen.getByText('All Brands');
      fireEvent.click(allBrandsOption);
    });
    
    expect(mockOnBrandSelect).toHaveBeenCalledWith(undefined);
  });

  it('highlights selected brand', async () => {
    render(<BrandBrowser {...defaultProps} selectedBrandId={1} />);
    
    await waitFor(() => {
      const hotWheelsOption = screen.getByText('Hot Wheels').closest('div');
      expect(hotWheelsOption).toHaveClass('bg-blue-100', 'text-blue-800');
    });
  });

  it('highlights "All Brands" when no brand is selected', async () => {
    render(<BrandBrowser {...defaultProps} selectedBrandId={undefined} />);
    
    await waitFor(() => {
      const allBrandsOption = screen.getByText('All Brands').closest('div');
      expect(allBrandsOption).toHaveClass('bg-blue-100', 'text-blue-800');
    });
  });

  it('shows search input when there are many brands', async () => {
    render(<BrandBrowser {...defaultProps} showSearch={true} />);
    
    await waitFor(() => {
      expect(screen.getByPlaceholderText('Search brands...')).toBeInTheDocument();
    });
  });

  it('does not show search input when showSearch is false', async () => {
    render(<BrandBrowser {...defaultProps} showSearch={false} />);
    
    await waitFor(() => {
      expect(screen.queryByPlaceholderText('Search brands...')).not.toBeInTheDocument();
    });
  });

  it('filters brands based on search query', async () => {
    render(<BrandBrowser {...defaultProps} showSearch={true} />);
    
    await waitFor(() => {
      const searchInput = screen.getByPlaceholderText('Search brands...');
      fireEvent.change(searchInput, { target: { value: 'hot' } });
    });
    
    await waitFor(() => {
      expect(screen.getByText('Hot Wheels')).toBeInTheDocument();
      expect(screen.queryByText('Matchbox')).not.toBeInTheDocument();
    });
  });

  it('shows no results message when search yields no results', async () => {
    render(<BrandBrowser {...defaultProps} showSearch={true} />);
    
    await waitFor(() => {
      const searchInput = screen.getByPlaceholderText('Search brands...');
      fireEvent.change(searchInput, { target: { value: 'nonexistent' } });
    });
    
    await waitFor(() => {
      expect(screen.getByText('No brands found for "nonexistent"')).toBeInTheDocument();
    });
  });

  it('shows clear button when brand is selected', async () => {
    render(<BrandBrowser {...defaultProps} selectedBrandId={1} />);
    
    await waitFor(() => {
      expect(screen.getByText('Clear')).toBeInTheDocument();
    });
  });

  it('calls onBrandSelect with undefined when clear button is clicked', async () => {
    const mockOnBrandSelect = jest.fn();
    render(<BrandBrowser {...defaultProps} selectedBrandId={1} onBrandSelect={mockOnBrandSelect} />);
    
    await waitFor(() => {
      const clearButton = screen.getByText('Clear');
      fireEvent.click(clearButton);
    });
    
    expect(mockOnBrandSelect).toHaveBeenCalledWith(undefined);
  });

  it('shows selected brand indicator', async () => {
    render(<BrandBrowser {...defaultProps} selectedBrandId={1} />);
    
    await waitFor(() => {
      expect(screen.getByText('Selected Brand:')).toBeInTheDocument();
      expect(screen.getByText('Hot Wheels')).toBeInTheDocument();
    });
  });

  it('displays brand logos when available', async () => {
    render(<BrandBrowser {...defaultProps} />);
    
    await waitFor(() => {
      const hotWheelsLogo = screen.getByAltText('Hot Wheels');
      expect(hotWheelsLogo).toBeInTheDocument();
      expect(hotWheelsLogo).toHaveAttribute('src', 'https://example.com/hw-logo.png');
    });
  });

  it('handles API error gracefully', async () => {
    (productApi.getBrands as jest.Mock).mockRejectedValue(new Error('API Error'));
    
    render(<BrandBrowser {...defaultProps} />);
    
    await waitFor(() => {
      expect(screen.getByText('Failed to load brands')).toBeInTheDocument();
      expect(screen.getByText('Try again')).toBeInTheDocument();
    });
  });

  it('retries loading brands when try again is clicked', async () => {
    (productApi.getBrands as jest.Mock).mockRejectedValueOnce(new Error('API Error'));
    (productApi.getBrands as jest.Mock).mockResolvedValueOnce({ data: mockBrands });
    
    render(<BrandBrowser {...defaultProps} />);
    
    await waitFor(() => {
      const tryAgainButton = screen.getByText('Try again');
      fireEvent.click(tryAgainButton);
    });
    
    await waitFor(() => {
      expect(screen.getByText('Hot Wheels')).toBeInTheDocument();
    });
  });

  it('shows no brands message when no brands are available', async () => {
    (productApi.getBrands as jest.Mock).mockResolvedValue({ data: [] });
    
    render(<BrandBrowser {...defaultProps} />);
    
    await waitFor(() => {
      expect(screen.getByText('No brands available')).toBeInTheDocument();
    });
  });

  it('filters out inactive brands', async () => {
    const brandsWithInactive = [
      ...mockBrands,
      { id: 13, name: 'Inactive Brand', slug: 'inactive', isActive: false },
    ];
    
    (productApi.getBrands as jest.Mock).mockResolvedValue({ data: brandsWithInactive });
    
    render(<BrandBrowser {...defaultProps} />);
    
    await waitFor(() => {
      expect(screen.getByText('Hot Wheels')).toBeInTheDocument();
      expect(screen.queryByText('Inactive Brand')).not.toBeInTheDocument();
    });
  });

  it('toggles brand selection when same brand is clicked', async () => {
    const mockOnBrandSelect = jest.fn();
    render(<BrandBrowser {...defaultProps} selectedBrandId={1} onBrandSelect={mockOnBrandSelect} />);
    
    await waitFor(() => {
      const hotWheelsOption = screen.getByText('Hot Wheels');
      fireEvent.click(hotWheelsOption);
    });
    
    expect(mockOnBrandSelect).toHaveBeenCalledWith(undefined);
  });

  it('applies custom className', async () => {
    render(<BrandBrowser {...defaultProps} className="custom-class" />);
    
    await waitFor(() => {
      const container = document.querySelector('.custom-class');
      expect(container).toBeInTheDocument();
    });
  });

  it('handles scroll for many brands', async () => {
    render(<BrandBrowser {...defaultProps} />);
    
    await waitFor(() => {
      const brandList = document.querySelector('.max-h-64.overflow-y-auto');
      expect(brandList).toBeInTheDocument();
    });
  });

  it('case-insensitive search filtering', async () => {
    render(<BrandBrowser {...defaultProps} showSearch={true} />);
    
    await waitFor(() => {
      const searchInput = screen.getByPlaceholderText('Search brands...');
      fireEvent.change(searchInput, { target: { value: 'HOT WHEELS' } });
    });
    
    await waitFor(() => {
      expect(screen.getByText('Hot Wheels')).toBeInTheDocument();
    });
  });
});