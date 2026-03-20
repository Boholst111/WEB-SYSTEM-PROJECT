import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import RecommendationCarousel from '../RecommendationCarousel';
import { Product } from '../../types';

const mockProducts: Product[] = [
  {
    id: 1,
    name: 'Hot Wheels Ferrari F40',
    sku: 'HW-001',
    description: 'Classic Ferrari model',
    brandId: 1,
    categoryId: 1,
    scale: '1:64',
    material: 'diecast',
    features: ['opening doors'],
    isChaseVariant: false,
    basePrice: 100.00,
    currentPrice: 100.00,
    stockQuantity: 10,
    isPreorder: false,
    status: 'active',
    images: ['image1.jpg'],
    specifications: {},
    createdAt: '2024-01-01',
    updatedAt: '2024-01-01',
    brand: { id: 1, name: 'Hot Wheels', slug: 'hot-wheels' },
    category: { id: 1, name: 'Cars', slug: 'cars', parentId: null },
  },
  {
    id: 2,
    name: 'Hot Wheels Lamborghini',
    sku: 'HW-002',
    description: 'Lamborghini model',
    brandId: 1,
    categoryId: 1,
    scale: '1:64',
    material: 'diecast',
    features: [],
    isChaseVariant: false,
    basePrice: 120.00,
    currentPrice: 120.00,
    stockQuantity: 5,
    isPreorder: false,
    status: 'active',
    images: ['image2.jpg'],
    specifications: {},
    createdAt: '2024-01-01',
    updatedAt: '2024-01-01',
    brand: { id: 1, name: 'Hot Wheels', slug: 'hot-wheels' },
    category: { id: 1, name: 'Cars', slug: 'cars', parentId: null },
  },
];

describe('RecommendationCarousel', () => {
  const renderComponent = (props = {}) => {
    return render(
      <BrowserRouter>
        <RecommendationCarousel
          title="Recommended Products"
          products={mockProducts}
          {...props}
        />
      </BrowserRouter>
    );
  };

  it('renders the carousel with title', () => {
    renderComponent();
    expect(screen.getByText('Recommended Products')).toBeInTheDocument();
  });

  it('renders all products', () => {
    renderComponent();
    expect(screen.getByText('Hot Wheels Ferrari F40')).toBeInTheDocument();
    expect(screen.getByText('Hot Wheels Lamborghini')).toBeInTheDocument();
  });

  it('shows loading state', () => {
    renderComponent({ loading: true });
    expect(screen.getByText('Recommended Products')).toBeInTheDocument();
    // Loading skeleton should be visible
    const skeletons = document.querySelectorAll('.animate-pulse');
    expect(skeletons.length).toBeGreaterThan(0);
  });

  it('renders nothing when no products', () => {
    const { container } = renderComponent({ products: [] });
    expect(container.firstChild).toBeNull();
  });

  it('has scroll navigation buttons', () => {
    renderComponent();
    const buttons = screen.getAllByRole('button');
    expect(buttons.length).toBeGreaterThanOrEqual(2);
  });

  it('disables left scroll button at start', () => {
    renderComponent();
    const leftButton = screen.getByLabelText('Scroll left');
    expect(leftButton).toBeDisabled();
  });
});
