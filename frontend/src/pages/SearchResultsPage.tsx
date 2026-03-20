import React, { useEffect, useState } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import Layout from '../components/Layout';
import ProductGrid from '../components/ProductGrid';
import FilterSidebar from '../components/FilterSidebar';
import SearchInterface from '../components/SearchInterface';
import { searchApi } from '../services/api';
import { Product, ProductFilters } from '../types';
import { AdjustmentsHorizontalIcon } from '@heroicons/react/24/outline';

const SearchResultsPage: React.FC = () => {
  const [searchParams, setSearchParams] = useSearchParams();
  const navigate = useNavigate();
  
  const [products, setProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [totalResults, setTotalResults] = useState(0);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [showFilters, setShowFilters] = useState(false);
  const [filters, setFilters] = useState<ProductFilters>({});
  const [sortBy, setSortBy] = useState('relevance');

  const query = searchParams.get('q') || '';

  useEffect(() => {
    if (query) {
      performSearch();
    }
  }, [query, filters, sortBy, currentPage]);

  const performSearch = async () => {
    setLoading(true);
    try {
      const response = await searchApi.search(
        query,
        filters,
        sortBy,
        'desc',
        20
      );

      if (response.success && response.data) {
        setProducts(response.data.products || []);
        setTotalResults(response.data.pagination?.total || 0);
        setTotalPages(response.data.pagination?.last_page || 1);
        
        // Log search for analytics
        if (response.data.products?.length > 0) {
          searchApi.logSearch(query, response.data.products.length).catch(console.error);
        }
      }
    } catch (error) {
      console.error('Search failed:', error);
      setProducts([]);
      setTotalResults(0);
    } finally {
      setLoading(false);
    }
  };

  const handleSearch = (newQuery: string) => {
    setSearchParams({ q: newQuery });
    setCurrentPage(1);
  };

  const handleFilterChange = (newFilters: ProductFilters) => {
    setFilters(newFilters);
    setCurrentPage(1);
  };

  const handleSortChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    setSortBy(e.target.value);
    setCurrentPage(1);
  };

  const handlePageChange = (page: number) => {
    setCurrentPage(page);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  return (
    <Layout>
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Search Bar */}
        <div className="mb-8">
          <SearchInterface
            onSearch={handleSearch}
            placeholder="Search for diecast models, brands, scales..."
            className="max-w-3xl mx-auto"
          />
        </div>

        {/* Results Header */}
        <div className="flex items-center justify-between mb-6">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">
              Search Results
            </h1>
            {query && (
              <p className="text-gray-600 mt-1">
                {loading ? 'Searching...' : `${totalResults} results for "${query}"`}
              </p>
            )}
          </div>

          <div className="flex items-center space-x-4">
            {/* Sort Dropdown */}
            <select
              value={sortBy}
              onChange={handleSortChange}
              className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="relevance">Most Relevant</option>
              <option value="popularity">Most Popular</option>
              <option value="price">Price: Low to High</option>
              <option value="name">Name: A to Z</option>
              <option value="created_at">Newest First</option>
            </select>

            {/* Mobile Filter Toggle */}
            <button
              onClick={() => setShowFilters(!showFilters)}
              className="lg:hidden flex items-center space-x-2 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50"
            >
              <AdjustmentsHorizontalIcon className="w-5 h-5" />
              <span>Filters</span>
            </button>
          </div>
        </div>

        {/* Main Content */}
        <div className="flex flex-col lg:flex-row gap-8">
          {/* Filters Sidebar */}
          <aside className={`lg:block ${showFilters ? 'block' : 'hidden'} lg:w-64 flex-shrink-0`}>
            <div className="sticky top-4">
              <FilterSidebar
                filters={filters}
                onFilterChange={handleFilterChange}
              />
            </div>
          </aside>

          {/* Products Grid */}
          <main className="flex-1">
            {loading ? (
              <div className="flex justify-center items-center py-20">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
              </div>
            ) : products.length > 0 ? (
              <>
                <ProductGrid products={products} />
                
                {/* Pagination */}
                {totalPages > 1 && (
                  <div className="mt-8 flex justify-center">
                    <nav className="flex items-center space-x-2">
                      <button
                        onClick={() => handlePageChange(currentPage - 1)}
                        disabled={currentPage === 1}
                        className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                      >
                        Previous
                      </button>
                      
                      {[...Array(Math.min(5, totalPages))].map((_, i) => {
                        const page = i + 1;
                        return (
                          <button
                            key={page}
                            onClick={() => handlePageChange(page)}
                            className={`px-4 py-2 border rounded-lg ${
                              currentPage === page
                                ? 'bg-blue-600 text-white border-blue-600'
                                : 'border-gray-300 hover:bg-gray-50'
                            }`}
                          >
                            {page}
                          </button>
                        );
                      })}
                      
                      <button
                        onClick={() => handlePageChange(currentPage + 1)}
                        disabled={currentPage === totalPages}
                        className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                      >
                        Next
                      </button>
                    </nav>
                  </div>
                )}
              </>
            ) : (
              <div className="text-center py-20">
                <p className="text-xl text-gray-600 mb-4">
                  No results found for "{query}"
                </p>
                <p className="text-gray-500">
                  Try different keywords or check your spelling
                </p>
              </div>
            )}
          </main>
        </div>
      </div>
    </Layout>
  );
};

export default SearchResultsPage;
