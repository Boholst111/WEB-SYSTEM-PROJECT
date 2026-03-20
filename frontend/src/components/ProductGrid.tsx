import React, { useEffect, useCallback, useRef } from 'react';
import { useInView } from 'react-intersection-observer';
import ProductCard from './ProductCard';
import { Product } from '../types';

interface ProductGridProps {
  products: Product[];
  isLoading: boolean;
  hasMore: boolean;
  onLoadMore: () => void;
  onAddToCart?: (product: Product) => void;
  onToggleWishlist?: (product: Product) => void;
  error?: string | null;
}

const ProductGrid: React.FC<ProductGridProps> = ({
  products,
  isLoading,
  hasMore,
  onLoadMore,
  onAddToCart,
  onToggleWishlist,
  error,
}) => {
  const { ref: loadMoreRef, inView } = useInView({
    threshold: 0.1,
    rootMargin: '100px',
  });

  // Load more when the sentinel comes into view
  useEffect(() => {
    if (inView && hasMore && !isLoading) {
      onLoadMore();
    }
  }, [inView, hasMore, isLoading, onLoadMore]);

  const renderLoadingSkeletons = (count: number = 8) => {
    return Array.from({ length: count }, (_, index) => (
      <div key={`skeleton-${index}`} className="bg-white rounded-lg shadow-sm overflow-hidden animate-pulse">
        <div className="aspect-square bg-gray-200"></div>
        <div className="p-4 space-y-3">
          <div className="flex justify-between">
            <div className="h-3 bg-gray-200 rounded w-16"></div>
            <div className="h-3 bg-gray-200 rounded w-12"></div>
          </div>
          <div className="h-4 bg-gray-200 rounded w-3/4"></div>
          <div className="flex space-x-1">
            <div className="h-5 bg-gray-200 rounded w-16"></div>
            <div className="h-5 bg-gray-200 rounded w-20"></div>
          </div>
          <div className="flex justify-between items-center">
            <div className="h-6 bg-gray-200 rounded w-20"></div>
            <div className="h-8 w-8 bg-gray-200 rounded"></div>
          </div>
        </div>
      </div>
    ));
  };

  if (error) {
    return (
      <div className="col-span-full flex flex-col items-center justify-center py-12">
        <div className="text-center">
          <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg className="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h3 className="text-lg font-medium text-gray-900 mb-2">Something went wrong</h3>
          <p className="text-gray-600 mb-4">{error}</p>
          <button
            onClick={() => window.location.reload()}
            className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
          >
            Try Again
          </button>
        </div>
      </div>
    );
  }

  if (!isLoading && products.length === 0) {
    return (
      <div className="col-span-full flex flex-col items-center justify-center py-12">
        <div className="text-center">
          <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2 2v-5m16 0h-2M4 13h2m0 0V9a2 2 0 012-2h2m0 0V6a2 2 0 012-2h2a2 2 0 012 2v1m0 0v2a2 2 0 002 2h2" />
            </svg>
          </div>
          <h3 className="text-lg font-medium text-gray-900 mb-2">No products found</h3>
          <p className="text-gray-600">Try adjusting your search or filter criteria</p>
        </div>
      </div>
    );
  }

  return (
    <>
      {/* Product Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        {products.map((product, index) => (
          <ProductCard
            key={`${product.id}-${index}`}
            product={product}
            onAddToCart={onAddToCart}
            onToggleWishlist={onToggleWishlist}
          />
        ))}

        {/* Loading skeletons for initial load */}
        {isLoading && products.length === 0 && renderLoadingSkeletons()}
      </div>

      {/* Infinite scroll loading indicator */}
      {hasMore && (
        <div ref={loadMoreRef} className="col-span-full py-8">
          {isLoading && products.length > 0 && (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
              {renderLoadingSkeletons(4)}
            </div>
          )}
        </div>
      )}

      {/* End of results indicator */}
      {!hasMore && products.length > 0 && (
        <div className="col-span-full text-center py-8">
          <div className="inline-flex items-center space-x-2 text-gray-500">
            <div className="h-px bg-gray-300 w-16"></div>
            <span className="text-sm">You've reached the end</span>
            <div className="h-px bg-gray-300 w-16"></div>
          </div>
        </div>
      )}
    </>
  );
};

export default ProductGrid;