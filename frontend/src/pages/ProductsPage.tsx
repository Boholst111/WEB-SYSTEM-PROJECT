import React, { useState, useEffect, useCallback } from 'react';
import { FunnelIcon, Squares2X2Icon, ListBulletIcon } from '@heroicons/react/24/outline';
import { useAppDispatch, useAppSelector } from '../store';
import { 
  setProducts, 
  setFilters, 
  clearFilters, 
  setLoading, 
  setError, 
  setPagination 
} from '../store/slices/productSlice';
import { productApi } from '../services/api';
import { Product, ProductFilters } from '../types';

// Components
import SearchInterface from '../components/SearchInterface';
import FilterSidebar from '../components/FilterSidebar';
import ProductGrid from '../components/ProductGrid';
import CategoryBrowser from '../components/CategoryBrowser';
import BrandBrowser from '../components/BrandBrowser';

const ProductsPage: React.FC = () => {
  const dispatch = useAppDispatch();
  const { 
    products, 
    filters, 
    isLoading, 
    error, 
    pagination = { currentPage: 1, lastPage: 1, perPage: 20, total: 0 }
  } = useAppSelector(state => state.products);

  const [isFilterSidebarOpen, setIsFilterSidebarOpen] = useState(false);
  const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');
  const [allProducts, setAllProducts] = useState<Product[]>([]);
  const [hasMore, setHasMore] = useState(true);

  // Load initial products
  useEffect(() => {
    loadProducts(true);
  }, [filters]);

  const loadProducts = useCallback(async (reset = false) => {
    try {
      dispatch(setLoading(true));
      dispatch(setError(null));

      const currentFilters = reset ? { ...filters, page: 1 } : filters;
      const response = await productApi.getProducts(currentFilters);

      if (reset) {
        setAllProducts(response.data);
        dispatch(setProducts(response.data));
      } else {
        const newProducts = [...allProducts, ...response.data];
        setAllProducts(newProducts);
        dispatch(setProducts(newProducts));
      }

      dispatch(setPagination(response.meta));
      setHasMore(response.meta?.currentPage < response.meta?.lastPage);
    } catch (err) {
      dispatch(setError('Failed to load products. Please try again.'));
      console.error('Failed to load products:', err);
    } finally {
      dispatch(setLoading(false));
    }
  }, [dispatch, filters, allProducts]);

  const handleLoadMore = useCallback(() => {
    if (!isLoading && hasMore && pagination) {
      const nextPage = pagination.currentPage + 1;
      dispatch(setFilters({ page: nextPage }));
    }
  }, [dispatch, isLoading, hasMore, pagination]);

  const handleSearch = (query: string) => {
    dispatch(setFilters({ search: query, page: 1 }));
  };

  const handleFiltersChange = (newFilters: Partial<ProductFilters>) => {
    dispatch(setFilters({ ...newFilters, page: 1 }));
  };

  const handleClearFilters = () => {
    dispatch(clearFilters());
  };

  const handleCategorySelect = (categoryId: number | undefined) => {
    dispatch(setFilters({ categoryId, page: 1 }));
  };

  const handleBrandSelect = (brandId: number | undefined) => {
    dispatch(setFilters({ brandId, page: 1 }));
  };

  const handleAddToCart = (product: Product) => {
    // TODO: Implement add to cart functionality
    console.log('Add to cart:', product);
  };

  const handleToggleWishlist = (product: Product) => {
    // TODO: Implement wishlist functionality
    console.log('Toggle wishlist:', product);
  };

  const getSortOptions = () => [
    { value: 'name:asc', label: 'Name A-Z' },
    { value: 'name:desc', label: 'Name Z-A' },
    { value: 'price:asc', label: 'Price Low to High' },
    { value: 'price:desc', label: 'Price High to Low' },
    { value: 'created_at:desc', label: 'Newest First' },
    { value: 'created_at:asc', label: 'Oldest First' },
  ];

  const handleSortChange = (sortValue: string) => {
    const [sortBy, sortOrder] = sortValue.split(':') as [
      'name' | 'price' | 'created_at' | 'popularity', 
      'asc' | 'desc'
    ];
    dispatch(setFilters({ sortBy, sortOrder, page: 1 }));
  };

  const getActiveFiltersCount = () => {
    let count = 0;
    if (filters.search) count++;
    if (filters.categoryId) count++;
    if (filters.brandId) count++;
    if (filters.scale?.length) count += filters.scale.length;
    if (filters.material?.length) count += filters.material.length;
    if (filters.features?.length) count += filters.features.length;
    if (filters.minPrice || filters.maxPrice) count++;
    if (filters.isChaseVariant !== undefined) count++;
    if (filters.isPreorder !== undefined) count++;
    if (filters.inStock !== undefined) count++;
    return count;
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900 mb-4">Diecast Models</h1>
          
          {/* Search Bar */}
          <div className="max-w-2xl">
            <SearchInterface
              onSearch={handleSearch}
              placeholder="Search for diecast models, brands, scales..."
              className="w-full"
            />
          </div>
        </div>

        <div className="flex flex-col lg:flex-row gap-8">
          {/* Sidebar */}
          <div className="lg:w-80 flex-shrink-0">
            {/* Mobile Filter Toggle */}
            <div className="lg:hidden mb-4">
              <button
                onClick={() => setIsFilterSidebarOpen(true)}
                className="flex items-center space-x-2 px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
              >
                <FunnelIcon className="w-5 h-5" />
                <span>Filters</span>
                {getActiveFiltersCount() > 0 && (
                  <span className="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
                    {getActiveFiltersCount()}
                  </span>
                )}
              </button>
            </div>

            {/* Desktop Sidebar */}
            <div className="hidden lg:block space-y-6">
              <div className="bg-white rounded-lg shadow-sm p-6">
                <CategoryBrowser
                  selectedCategoryId={filters.categoryId}
                  onCategorySelect={handleCategorySelect}
                />
              </div>

              <div className="bg-white rounded-lg shadow-sm p-6">
                <BrandBrowser
                  selectedBrandId={filters.brandId}
                  onBrandSelect={handleBrandSelect}
                />
              </div>
            </div>

            {/* Mobile Filter Sidebar */}
            <FilterSidebar
              filters={filters}
              onFiltersChange={handleFiltersChange}
              onClearFilters={handleClearFilters}
              isOpen={isFilterSidebarOpen}
              onClose={() => setIsFilterSidebarOpen(false)}
            />
          </div>

          {/* Main Content */}
          <div className="flex-1">
            {/* Toolbar */}
            <div className="bg-white rounded-lg shadow-sm p-4 mb-6">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
                {/* Results Info */}
                <div className="text-sm text-gray-600">
                  {isLoading && products.length === 0 ? (
                    'Loading products...'
                  ) : (
                    <>
                      Showing {products.length} of {pagination?.total || 0} products
                      {filters.search && (
                        <span> for "{filters.search}"</span>
                      )}
                    </>
                  )}
                </div>

                {/* Controls */}
                <div className="flex items-center space-x-4">
                  {/* Sort */}
                  <select
                    value={`${filters.sortBy}:${filters.sortOrder}`}
                    onChange={(e) => handleSortChange(e.target.value)}
                    className="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  >
                    {getSortOptions().map(option => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </select>

                  {/* View Mode Toggle */}
                  <div className="flex border border-gray-300 rounded-lg overflow-hidden">
                    <button
                      onClick={() => setViewMode('grid')}
                      className={`p-2 ${
                        viewMode === 'grid'
                          ? 'bg-blue-600 text-white'
                          : 'bg-white text-gray-600 hover:bg-gray-50'
                      }`}
                    >
                      <Squares2X2Icon className="w-5 h-5" />
                    </button>
                    <button
                      onClick={() => setViewMode('list')}
                      className={`p-2 ${
                        viewMode === 'list'
                          ? 'bg-blue-600 text-white'
                          : 'bg-white text-gray-600 hover:bg-gray-50'
                      }`}
                    >
                      <ListBulletIcon className="w-5 h-5" />
                    </button>
                  </div>
                </div>
              </div>
            </div>

            {/* Products Grid */}
            <div className="bg-white rounded-lg shadow-sm p-6">
              <ProductGrid
                products={products}
                isLoading={isLoading}
                hasMore={hasMore}
                onLoadMore={handleLoadMore}
                onAddToCart={handleAddToCart}
                onToggleWishlist={handleToggleWishlist}
                error={error}
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ProductsPage;