import React, { useState, useEffect, useRef } from 'react';
import { MagnifyingGlassIcon, XMarkIcon } from '@heroicons/react/24/outline';
import { productApi } from '../services/api';
import { Product } from '../types';
import { useNavigate } from 'react-router-dom';

interface SearchInterfaceProps {
  onSearch: (query: string) => void;
  placeholder?: string;
  className?: string;
  showSuggestions?: boolean;
}

const SearchInterface: React.FC<SearchInterfaceProps> = ({
  onSearch,
  placeholder = "Search for diecast models, brands, scales...",
  className = "",
  showSuggestions = true,
}) => {
  const [query, setQuery] = useState('');
  const [suggestions, setSuggestions] = useState<string[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [isOpen, setIsOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [selectedIndex, setSelectedIndex] = useState(-1);
  
  const searchRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const navigate = useNavigate();

  // Debounce search suggestions
  useEffect(() => {
    if (!query.trim() || !showSuggestions) {
      setSuggestions([]);
      setProducts([]);
      setIsOpen(false);
      return;
    }

    const timeoutId = setTimeout(async () => {
      setIsLoading(true);
      try {
        const [suggestionsRes, productsRes] = await Promise.all([
          productApi.getProductSuggestions(query),
          productApi.searchProducts(query, 5),
        ]);

        setSuggestions(suggestionsRes.data);
        setProducts(productsRes.data);
        setIsOpen(true);
      } catch (error) {
        console.error('Search suggestions failed:', error);
        setSuggestions([]);
        setProducts([]);
      } finally {
        setIsLoading(false);
      }
    }, 300);

    return () => clearTimeout(timeoutId);
  }, [query, showSuggestions]);

  // Handle click outside
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (searchRef.current && !searchRef.current.contains(event.target as Node)) {
        setIsOpen(false);
        setSelectedIndex(-1);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (query.trim()) {
      onSearch(query.trim());
      setIsOpen(false);
      setSelectedIndex(-1);
    }
  };

  const handleSuggestionClick = (suggestion: string) => {
    setQuery(suggestion);
    onSearch(suggestion);
    setIsOpen(false);
    setSelectedIndex(-1);
  };

  const handleProductClick = (product: Product) => {
    navigate(`/products/${product.id}`);
    setIsOpen(false);
    setSelectedIndex(-1);
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    const totalItems = suggestions.length + products.length;
    
    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        setSelectedIndex(prev => (prev < totalItems - 1 ? prev + 1 : -1));
        break;
      case 'ArrowUp':
        e.preventDefault();
        setSelectedIndex(prev => (prev > -1 ? prev - 1 : totalItems - 1));
        break;
      case 'Enter':
        e.preventDefault();
        if (selectedIndex >= 0) {
          if (selectedIndex < suggestions.length) {
            handleSuggestionClick(suggestions[selectedIndex]);
          } else {
            const productIndex = selectedIndex - suggestions.length;
            handleProductClick(products[productIndex]);
          }
        } else {
          handleSubmit(e);
        }
        break;
      case 'Escape':
        setIsOpen(false);
        setSelectedIndex(-1);
        inputRef.current?.blur();
        break;
    }
  };

  const clearSearch = () => {
    setQuery('');
    setSuggestions([]);
    setProducts([]);
    setIsOpen(false);
    setSelectedIndex(-1);
    inputRef.current?.focus();
  };

  const formatPrice = (price: number) => {
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
    }).format(price);
  };

  return (
    <div ref={searchRef} className={`relative ${className}`}>
      <form onSubmit={handleSubmit} className="relative">
        <div className="relative">
          <MagnifyingGlassIcon className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
          <input
            ref={inputRef}
            type="text"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            onKeyDown={handleKeyDown}
            onFocus={() => query && showSuggestions && setIsOpen(true)}
            placeholder={placeholder}
            className="w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
          />
          {query && (
            <button
              type="button"
              onClick={clearSearch}
              className="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
            >
              <XMarkIcon className="w-5 h-5" />
            </button>
          )}
        </div>
      </form>

      {/* Search Suggestions Dropdown */}
      {isOpen && showSuggestions && (suggestions.length > 0 || products.length > 0 || isLoading) && (
        <div className="absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-50 max-h-96 overflow-y-auto">
          {isLoading && (
            <div className="p-4 text-center text-gray-500">
              <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600 mx-auto"></div>
              <span className="mt-2 block text-sm">Searching...</span>
            </div>
          )}

          {!isLoading && (
            <>
              {/* Search Suggestions */}
              {suggestions.length > 0 && (
                <div className="border-b border-gray-100">
                  <div className="px-4 py-2 text-xs font-medium text-gray-500 uppercase tracking-wide">
                    Suggestions
                  </div>
                  {suggestions.map((suggestion, index) => (
                    <button
                      key={suggestion}
                      onClick={() => handleSuggestionClick(suggestion)}
                      className={`w-full text-left px-4 py-2 hover:bg-gray-50 flex items-center space-x-3 ${
                        selectedIndex === index ? 'bg-blue-50 text-blue-700' : 'text-gray-700'
                      }`}
                    >
                      <MagnifyingGlassIcon className="w-4 h-4 text-gray-400" />
                      <span className="text-sm">{suggestion}</span>
                    </button>
                  ))}
                </div>
              )}

              {/* Product Results */}
              {products.length > 0 && (
                <div>
                  <div className="px-4 py-2 text-xs font-medium text-gray-500 uppercase tracking-wide">
                    Products
                  </div>
                  {products.map((product, index) => {
                    const globalIndex = suggestions.length + index;
                    return (
                      <button
                        key={product.id}
                        onClick={() => handleProductClick(product)}
                        className={`w-full text-left px-4 py-3 hover:bg-gray-50 flex items-center space-x-3 ${
                          selectedIndex === globalIndex ? 'bg-blue-50' : ''
                        }`}
                      >
                        <div className="flex-shrink-0 w-12 h-12 bg-gray-100 rounded overflow-hidden">
                          {product.images?.[0] ? (
                            <img
                              src={product.images[0]}
                              alt={product.name}
                              className="w-full h-full object-cover"
                            />
                          ) : (
                            <div className="w-full h-full bg-gray-200 flex items-center justify-center">
                              <div className="w-6 h-6 bg-gray-300 rounded"></div>
                            </div>
                          )}
                        </div>
                        <div className="flex-1 min-w-0">
                          <div className="flex items-center justify-between">
                            <p className="text-sm font-medium text-gray-900 truncate">
                              {product.name}
                            </p>
                            <p className="text-sm font-semibold text-gray-900 ml-2">
                              {formatPrice(product.currentPrice)}
                            </p>
                          </div>
                          <div className="flex items-center space-x-2 mt-1">
                            <p className="text-xs text-gray-500">{product.brand?.name}</p>
                            <span className="text-xs text-gray-400">•</span>
                            <p className="text-xs text-gray-500">{product.scale}</p>
                            {product.isChaseVariant && (
                              <>
                                <span className="text-xs text-gray-400">•</span>
                                <span className="text-xs bg-yellow-100 text-yellow-800 px-1 rounded">
                                  Chase
                                </span>
                              </>
                            )}
                          </div>
                        </div>
                      </button>
                    );
                  })}
                </div>
              )}

              {/* No Results */}
              {!isLoading && suggestions.length === 0 && products.length === 0 && query && (
                <div className="p-4 text-center text-gray-500">
                  <p className="text-sm">No results found for "{query}"</p>
                  <p className="text-xs mt-1">Try different keywords or check spelling</p>
                </div>
              )}
            </>
          )}
        </div>
      )}
    </div>
  );
};

export default SearchInterface;