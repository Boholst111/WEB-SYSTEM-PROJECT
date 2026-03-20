import React, { useState, useEffect } from 'react';
import { XMarkIcon, ChevronDownIcon, ChevronUpIcon } from '@heroicons/react/24/outline';
import { ProductFilters, Brand, Category } from '../types';
import { productApi } from '../services/api';

interface FilterSidebarProps {
  filters: ProductFilters;
  onFiltersChange: (filters: Partial<ProductFilters>) => void;
  onClearFilters: () => void;
  isOpen: boolean;
  onClose: () => void;
}

interface FilterSection {
  id: string;
  title: string;
  isOpen: boolean;
}

const FilterSidebar: React.FC<FilterSidebarProps> = ({
  filters,
  onFiltersChange,
  onClearFilters,
  isOpen,
  onClose,
}) => {
  const [brands, setBrands] = useState<Brand[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [filterOptions, setFilterOptions] = useState<{
    scales: string[];
    materials: string[];
    features: string[];
  }>({ scales: [], materials: [], features: [] });
  const [sections, setSections] = useState<FilterSection[]>([
    { id: 'category', title: 'Category', isOpen: true },
    { id: 'brand', title: 'Brand', isOpen: true },
    { id: 'scale', title: 'Scale', isOpen: true },
    { id: 'material', title: 'Material', isOpen: false },
    { id: 'features', title: 'Features', isOpen: false },
    { id: 'price', title: 'Price Range', isOpen: false },
    { id: 'availability', title: 'Availability', isOpen: false },
  ]);

  useEffect(() => {
    loadFilterData();
  }, []);

  const loadFilterData = async () => {
    try {
      const [brandsRes, categoriesRes, optionsRes] = await Promise.all([
        productApi.getBrands(),
        productApi.getCategories(),
        productApi.getFilterOptions(),
      ]);

      setBrands(brandsRes.data);
      setCategories(categoriesRes.data);
      setFilterOptions(optionsRes.data);
    } catch (error) {
      console.error('Failed to load filter data:', error);
    }
  };

  const toggleSection = (sectionId: string) => {
    setSections(prev =>
      prev.map(section =>
        section.id === sectionId
          ? { ...section, isOpen: !section.isOpen }
          : section
      )
    );
  };

  const handleArrayFilter = (key: keyof ProductFilters, value: string) => {
    const currentValues = (filters[key] as string[]) || [];
    const newValues = currentValues.includes(value)
      ? currentValues.filter(v => v !== value)
      : [...currentValues, value];
    
    onFiltersChange({ [key]: newValues.length > 0 ? newValues : undefined });
  };

  const handlePriceChange = (type: 'min' | 'max', value: string) => {
    const numValue = value === '' ? undefined : parseFloat(value);
    if (type === 'min') {
      onFiltersChange({ minPrice: numValue });
    } else {
      onFiltersChange({ maxPrice: numValue });
    }
  };

  const getActiveFiltersCount = () => {
    let count = 0;
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

  const renderCheckboxGroup = (
    items: { id?: number; name: string; slug?: string }[],
    selectedValues: string[] | undefined,
    onChange: (value: string) => void,
    valueKey: 'id' | 'name' | 'slug' = 'name'
  ) => (
    <div className="space-y-2 max-h-48 overflow-y-auto">
      {items.map((item) => {
        const value = valueKey === 'id' ? item.id?.toString() : item[valueKey];
        const isSelected = selectedValues?.includes(value || '') || false;
        
        return (
          <label key={item.id || item.name} className="flex items-center space-x-2 cursor-pointer">
            <input
              type="checkbox"
              checked={isSelected}
              onChange={() => onChange(value || '')}
              className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            />
            <span className="text-sm text-gray-700">{item.name}</span>
          </label>
        );
      })}
    </div>
  );

  return (
    <>
      {/* Mobile Overlay */}
      {isOpen && (
        <div
          className="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"
          onClick={onClose}
        />
      )}

      {/* Sidebar */}
      <div
        className={`fixed lg:static inset-y-0 left-0 z-50 w-80 bg-white shadow-lg lg:shadow-none transform transition-transform duration-300 ease-in-out ${
          isOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
        }`}
      >
        <div className="h-full flex flex-col">
          {/* Header */}
          <div className="flex items-center justify-between p-4 border-b">
            <div className="flex items-center space-x-2">
              <h2 className="text-lg font-semibold text-gray-900">Filters</h2>
              {getActiveFiltersCount() > 0 && (
                <span className="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
                  {getActiveFiltersCount()}
                </span>
              )}
            </div>
            <div className="flex items-center space-x-2">
              <button
                onClick={onClearFilters}
                className="text-sm text-blue-600 hover:text-blue-800"
              >
                Clear All
              </button>
              <button
                onClick={onClose}
                className="lg:hidden p-1 text-gray-400 hover:text-gray-600"
              >
                <XMarkIcon className="w-5 h-5" />
              </button>
            </div>
          </div>

          {/* Filters Content */}
          <div className="flex-1 overflow-y-auto p-4 space-y-6">
            {sections.map((section) => (
              <div key={section.id} className="border-b border-gray-200 pb-4">
                <button
                  onClick={() => toggleSection(section.id)}
                  className="flex items-center justify-between w-full text-left"
                >
                  <h3 className="text-sm font-medium text-gray-900">{section.title}</h3>
                  {section.isOpen ? (
                    <ChevronUpIcon className="w-4 h-4 text-gray-500" />
                  ) : (
                    <ChevronDownIcon className="w-4 h-4 text-gray-500" />
                  )}
                </button>

                {section.isOpen && (
                  <div className="mt-3">
                    {section.id === 'category' && (
                      <div className="space-y-2">
                        <label className="flex items-center space-x-2 cursor-pointer">
                          <input
                            type="radio"
                            name="category"
                            checked={!filters.categoryId}
                            onChange={() => onFiltersChange({ categoryId: undefined })}
                            className="text-blue-600 focus:ring-blue-500"
                          />
                          <span className="text-sm text-gray-700">All Categories</span>
                        </label>
                        {categories.map((category) => (
                          <label key={category.id} className="flex items-center space-x-2 cursor-pointer">
                            <input
                              type="radio"
                              name="category"
                              checked={filters.categoryId === category.id}
                              onChange={() => onFiltersChange({ categoryId: category.id })}
                              className="text-blue-600 focus:ring-blue-500"
                            />
                            <span className="text-sm text-gray-700">{category.name}</span>
                          </label>
                        ))}
                      </div>
                    )}

                    {section.id === 'brand' && (
                      <div className="space-y-2">
                        <label className="flex items-center space-x-2 cursor-pointer">
                          <input
                            type="radio"
                            name="brand"
                            checked={!filters.brandId}
                            onChange={() => onFiltersChange({ brandId: undefined })}
                            className="text-blue-600 focus:ring-blue-500"
                          />
                          <span className="text-sm text-gray-700">All Brands</span>
                        </label>
                        {brands.map((brand) => (
                          <label key={brand.id} className="flex items-center space-x-2 cursor-pointer">
                            <input
                              type="radio"
                              name="brand"
                              checked={filters.brandId === brand.id}
                              onChange={() => onFiltersChange({ brandId: brand.id })}
                              className="text-blue-600 focus:ring-blue-500"
                            />
                            <span className="text-sm text-gray-700">{brand.name}</span>
                          </label>
                        ))}
                      </div>
                    )}

                    {section.id === 'scale' && (
                      renderCheckboxGroup(
                        filterOptions.scales.map(scale => ({ name: scale })),
                        filters.scale,
                        (value) => handleArrayFilter('scale', value)
                      )
                    )}

                    {section.id === 'material' && (
                      renderCheckboxGroup(
                        filterOptions.materials.map(material => ({ name: material })),
                        filters.material,
                        (value) => handleArrayFilter('material', value)
                      )
                    )}

                    {section.id === 'features' && (
                      renderCheckboxGroup(
                        filterOptions.features.map(feature => ({ name: feature })),
                        filters.features,
                        (value) => handleArrayFilter('features', value)
                      )
                    )}

                    {section.id === 'price' && (
                      <div className="space-y-3">
                        <div>
                          <label className="block text-xs text-gray-600 mb-1">Min Price</label>
                          <input
                            type="number"
                            placeholder="₱0"
                            value={filters.minPrice || ''}
                            onChange={(e) => handlePriceChange('min', e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500"
                          />
                        </div>
                        <div>
                          <label className="block text-xs text-gray-600 mb-1">Max Price</label>
                          <input
                            type="number"
                            placeholder="₱10,000"
                            value={filters.maxPrice || ''}
                            onChange={(e) => handlePriceChange('max', e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500"
                          />
                        </div>
                      </div>
                    )}

                    {section.id === 'availability' && (
                      <div className="space-y-2">
                        <label className="flex items-center space-x-2 cursor-pointer">
                          <input
                            type="checkbox"
                            checked={filters.inStock === true}
                            onChange={(e) => onFiltersChange({ inStock: e.target.checked ? true : undefined })}
                            className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                          />
                          <span className="text-sm text-gray-700">In Stock Only</span>
                        </label>
                        <label className="flex items-center space-x-2 cursor-pointer">
                          <input
                            type="checkbox"
                            checked={filters.isPreorder === true}
                            onChange={(e) => onFiltersChange({ isPreorder: e.target.checked ? true : undefined })}
                            className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                          />
                          <span className="text-sm text-gray-700">Pre-orders</span>
                        </label>
                        <label className="flex items-center space-x-2 cursor-pointer">
                          <input
                            type="checkbox"
                            checked={filters.isChaseVariant === true}
                            onChange={(e) => onFiltersChange({ isChaseVariant: e.target.checked ? true : undefined })}
                            className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                          />
                          <span className="text-sm text-gray-700">Chase Variants Only</span>
                        </label>
                      </div>
                    )}
                  </div>
                )}
              </div>
            ))}
          </div>
        </div>
      </div>
    </>
  );
};

export default FilterSidebar;