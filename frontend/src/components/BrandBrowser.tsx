import React, { useState, useEffect } from 'react';
import { MagnifyingGlassIcon } from '@heroicons/react/24/outline';
import { Brand } from '../types';
import { productApi } from '../services/api';

interface BrandBrowserProps {
  selectedBrandId?: number;
  onBrandSelect: (brandId: number | undefined) => void;
  className?: string;
  showSearch?: boolean;
}

const BrandBrowser: React.FC<BrandBrowserProps> = ({
  selectedBrandId,
  onBrandSelect,
  className = "",
  showSearch = true,
}) => {
  const [brands, setBrands] = useState<Brand[]>([]);
  const [filteredBrands, setFilteredBrands] = useState<Brand[]>([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    loadBrands();
  }, []);

  useEffect(() => {
    if (searchQuery.trim()) {
      const filtered = brands.filter(brand =>
        brand.name.toLowerCase().includes(searchQuery.toLowerCase())
      );
      setFilteredBrands(filtered);
    } else {
      setFilteredBrands(brands);
    }
  }, [brands, searchQuery]);

  const loadBrands = async () => {
    try {
      setIsLoading(true);
      const response = await productApi.getBrands();
      const activeBrands = response.data.filter(brand => brand.isActive);
      setBrands(activeBrands);
      setError(null);
    } catch (err) {
      setError('Failed to load brands');
      console.error('Failed to load brands:', err);
    } finally {
      setIsLoading(false);
    }
  };

  const getSelectedBrandName = () => {
    const selectedBrand = brands.find(brand => brand.id === selectedBrandId);
    return selectedBrand?.name;
  };

  if (isLoading) {
    return (
      <div className={`space-y-3 ${className}`}>
        <div className="text-sm font-medium text-gray-900">Brands</div>
        {showSearch && (
          <div className="animate-pulse">
            <div className="h-8 bg-gray-200 rounded-lg"></div>
          </div>
        )}
        <div className="space-y-2">
          {Array.from({ length: 8 }, (_, index) => (
            <div key={index} className="animate-pulse">
              <div className="h-6 bg-gray-200 rounded"></div>
            </div>
          ))}
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className={`${className}`}>
        <div className="text-sm font-medium text-gray-900 mb-3">Brands</div>
        <div className="text-center py-4">
          <p className="text-sm text-red-600 mb-2">{error}</p>
          <button
            onClick={loadBrands}
            className="text-xs text-blue-600 hover:text-blue-800"
          >
            Try again
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className={`${className}`}>
      <div className="flex items-center justify-between mb-3">
        <h3 className="text-sm font-medium text-gray-900">Brands</h3>
        {selectedBrandId && (
          <button
            onClick={() => onBrandSelect(undefined)}
            className="text-xs text-blue-600 hover:text-blue-800"
          >
            Clear
          </button>
        )}
      </div>

      {/* Search */}
      {showSearch && brands.length > 10 && (
        <div className="relative mb-3">
          <MagnifyingGlassIcon className="absolute left-2 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input
            type="text"
            placeholder="Search brands..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
      )}

      <div className="space-y-1 max-h-64 overflow-y-auto">
        {/* All Brands option */}
        <div
          className={`flex items-center py-2 px-3 rounded-lg cursor-pointer transition-colors duration-150 ${
            !selectedBrandId
              ? 'bg-blue-100 text-blue-800 font-medium'
              : 'hover:bg-gray-50 text-gray-700'
          }`}
          onClick={() => onBrandSelect(undefined)}
        >
          <span className="text-sm">All Brands</span>
        </div>

        {/* Brand list */}
        {filteredBrands.map(brand => (
          <div
            key={brand.id}
            className={`flex items-center justify-between py-2 px-3 rounded-lg cursor-pointer transition-colors duration-150 ${
              selectedBrandId === brand.id
                ? 'bg-blue-100 text-blue-800 font-medium'
                : 'hover:bg-gray-50 text-gray-700'
            }`}
            onClick={() => onBrandSelect(selectedBrandId === brand.id ? undefined : brand.id)}
          >
            <div className="flex items-center space-x-3 flex-1 min-w-0">
              {brand.logo && (
                <img
                  src={brand.logo}
                  alt={brand.name}
                  className="w-6 h-6 object-contain flex-shrink-0"
                />
              )}
              <span className="text-sm truncate">{brand.name}</span>
            </div>
          </div>
        ))}
      </div>

      {/* No results */}
      {filteredBrands.length === 0 && searchQuery && (
        <div className="text-center py-4">
          <p className="text-sm text-gray-500">No brands found for "{searchQuery}"</p>
        </div>
      )}

      {/* No brands available */}
      {brands.length === 0 && !isLoading && (
        <div className="text-center py-4">
          <p className="text-sm text-gray-500">No brands available</p>
        </div>
      )}

      {/* Selected brand indicator */}
      {selectedBrandId && (
        <div className="mt-3 p-2 bg-blue-50 rounded-lg">
          <div className="text-xs text-blue-600 font-medium">Selected Brand:</div>
          <div className="text-sm text-blue-800">{getSelectedBrandName()}</div>
        </div>
      )}
    </div>
  );
};

export default BrandBrowser;