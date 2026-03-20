import React, { useState, useEffect } from 'react';
import { ChevronRightIcon, ChevronDownIcon } from '@heroicons/react/24/outline';
import { Category } from '../types';
import { productApi } from '../services/api';

interface CategoryBrowserProps {
  selectedCategoryId?: number;
  onCategorySelect: (categoryId: number | undefined) => void;
  className?: string;
}

const CategoryBrowser: React.FC<CategoryBrowserProps> = ({
  selectedCategoryId,
  onCategorySelect,
  className = "",
}) => {
  const [categories, setCategories] = useState<Category[]>([]);
  const [expandedCategories, setExpandedCategories] = useState<Set<number>>(new Set());
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    loadCategories();
  }, []);

  const loadCategories = async () => {
    try {
      setIsLoading(true);
      const response = await productApi.getCategories();
      setCategories(response.data);
      setError(null);
    } catch (err) {
      setError('Failed to load categories');
      console.error('Failed to load categories:', err);
    } finally {
      setIsLoading(false);
    }
  };

  const toggleCategory = (categoryId: number) => {
    setExpandedCategories(prev => {
      const newSet = new Set(prev);
      if (newSet.has(categoryId)) {
        newSet.delete(categoryId);
      } else {
        newSet.add(categoryId);
      }
      return newSet;
    });
  };

  const buildCategoryTree = (categories: Category[]): Category[] => {
    const categoryMap = new Map<number, Category>();
    const rootCategories: Category[] = [];

    // First pass: create map and initialize children arrays
    categories.forEach(category => {
      categoryMap.set(category.id, { ...category, children: [] });
    });

    // Second pass: build tree structure
    categories.forEach(category => {
      const categoryWithChildren = categoryMap.get(category.id)!;
      
      if (category.parentId) {
        const parent = categoryMap.get(category.parentId);
        if (parent) {
          parent.children = parent.children || [];
          parent.children.push(categoryWithChildren);
        }
      } else {
        rootCategories.push(categoryWithChildren);
      }
    });

    return rootCategories;
  };

  const renderCategory = (category: Category, level: number = 0) => {
    const hasChildren = category.children && category.children.length > 0;
    const isExpanded = expandedCategories.has(category.id);
    const isSelected = selectedCategoryId === category.id;

    return (
      <div key={category.id} className="select-none">
        <div
          className={`flex items-center space-x-2 py-2 px-3 rounded-lg cursor-pointer transition-colors duration-150 ${
            isSelected
              ? 'bg-blue-100 text-blue-800 font-medium'
              : 'hover:bg-gray-50 text-gray-700'
          }`}
          style={{ paddingLeft: `${12 + level * 20}px` }}
          onClick={() => onCategorySelect(isSelected ? undefined : category.id)}
        >
          {hasChildren && (
            <button
              onClick={(e) => {
                e.stopPropagation();
                toggleCategory(category.id);
              }}
              className="flex-shrink-0 p-0.5 hover:bg-gray-200 rounded transition-colors duration-150"
            >
              {isExpanded ? (
                <ChevronDownIcon className="w-4 h-4" />
              ) : (
                <ChevronRightIcon className="w-4 h-4" />
              )}
            </button>
          )}
          
          {!hasChildren && (
            <div className="w-5 h-5 flex-shrink-0"></div>
          )}

          <span className="flex-1 text-sm truncate">{category.name}</span>
        </div>

        {hasChildren && isExpanded && (
          <div className="mt-1">
            {category.children!.map(child => renderCategory(child, level + 1))}
          </div>
        )}
      </div>
    );
  };

  if (isLoading) {
    return (
      <div className={`space-y-2 ${className}`}>
        <div className="text-sm font-medium text-gray-900 mb-3">Categories</div>
        {Array.from({ length: 6 }, (_, index) => (
          <div key={index} className="animate-pulse">
            <div className="h-8 bg-gray-200 rounded-lg"></div>
          </div>
        ))}
      </div>
    );
  }

  if (error) {
    return (
      <div className={`${className}`}>
        <div className="text-sm font-medium text-gray-900 mb-3">Categories</div>
        <div className="text-center py-4">
          <p className="text-sm text-red-600 mb-2">{error}</p>
          <button
            onClick={loadCategories}
            className="text-xs text-blue-600 hover:text-blue-800"
          >
            Try again
          </button>
        </div>
      </div>
    );
  }

  const categoryTree = buildCategoryTree(categories);

  return (
    <div className={`${className}`}>
      <div className="flex items-center justify-between mb-3">
        <h3 className="text-sm font-medium text-gray-900">Categories</h3>
        {selectedCategoryId && (
          <button
            onClick={() => onCategorySelect(undefined)}
            className="text-xs text-blue-600 hover:text-blue-800"
          >
            Clear
          </button>
        )}
      </div>

      <div className="space-y-1 max-h-64 overflow-y-auto">
        {/* All Categories option */}
        <div
          className={`flex items-center space-x-2 py-2 px-3 rounded-lg cursor-pointer transition-colors duration-150 ${
            !selectedCategoryId
              ? 'bg-blue-100 text-blue-800 font-medium'
              : 'hover:bg-gray-50 text-gray-700'
          }`}
          onClick={() => onCategorySelect(undefined)}
        >
          <div className="w-5 h-5 flex-shrink-0"></div>
          <span className="flex-1 text-sm">All Categories</span>
        </div>

        {/* Category tree */}
        {categoryTree.map(category => renderCategory(category))}
      </div>

      {categories.length === 0 && (
        <div className="text-center py-4">
          <p className="text-sm text-gray-500">No categories available</p>
        </div>
      )}
    </div>
  );
};

export default CategoryBrowser;