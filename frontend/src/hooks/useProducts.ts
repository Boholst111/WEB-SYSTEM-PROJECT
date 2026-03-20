import { useState, useEffect, useCallback } from 'react';
import { useAppDispatch, useAppSelector } from '../store';
import { 
  setProducts, 
  setCurrentProduct, 
  setBrands, 
  setCategories, 
  setLoading, 
  setError, 
  setPagination 
} from '../store/slices/productSlice';
import { productApi } from '../services/api';
import { Product, ProductFilters, Brand, Category } from '../types';

export const useProducts = () => {
  const dispatch = useAppDispatch();
  const productState = useAppSelector(state => state.products);
  const [allProducts, setAllProducts] = useState<Product[]>([]);
  const [hasMore, setHasMore] = useState(true);

  const loadProducts = useCallback(async (filters: ProductFilters, reset = false) => {
    try {
      dispatch(setLoading(true));
      dispatch(setError(null));

      const response = await productApi.getProducts(filters);

      if (reset) {
        setAllProducts(response.data);
        dispatch(setProducts(response.data));
      } else {
        const newProducts = [...allProducts, ...response.data];
        setAllProducts(newProducts);
        dispatch(setProducts(newProducts));
      }

      dispatch(setPagination(response.meta));
      setHasMore(response.meta.currentPage < response.meta.lastPage);

      return response;
    } catch (error) {
      dispatch(setError('Failed to load products'));
      throw error;
    } finally {
      dispatch(setLoading(false));
    }
  }, [dispatch, allProducts]);

  const loadProduct = useCallback(async (id: number) => {
    try {
      dispatch(setLoading(true));
      dispatch(setError(null));

      const response = await productApi.getProduct(id);
      dispatch(setCurrentProduct(response.data));

      return response.data;
    } catch (error) {
      dispatch(setError('Failed to load product'));
      throw error;
    } finally {
      dispatch(setLoading(false));
    }
  }, [dispatch]);

  const searchProducts = useCallback(async (query: string, limit = 10) => {
    try {
      const response = await productApi.searchProducts(query, limit);
      return response.data;
    } catch (error) {
      console.error('Search failed:', error);
      return [];
    }
  }, []);

  const getProductSuggestions = useCallback(async (query: string) => {
    try {
      const response = await productApi.getProductSuggestions(query);
      return response.data;
    } catch (error) {
      console.error('Suggestions failed:', error);
      return [];
    }
  }, []);

  const loadBrands = useCallback(async () => {
    try {
      const response = await productApi.getBrands();
      dispatch(setBrands(response.data));
      return response.data;
    } catch (error) {
      console.error('Failed to load brands:', error);
      return [];
    }
  }, [dispatch]);

  const loadCategories = useCallback(async () => {
    try {
      const response = await productApi.getCategories();
      dispatch(setCategories(response.data));
      return response.data;
    } catch (error) {
      console.error('Failed to load categories:', error);
      return [];
    }
  }, [dispatch]);

  const loadFilterOptions = useCallback(async () => {
    try {
      const response = await productApi.getFilterOptions();
      return response.data;
    } catch (error) {
      console.error('Failed to load filter options:', error);
      return { scales: [], materials: [], features: [] };
    }
  }, []);

  return {
    ...productState,
    allProducts,
    hasMore,
    loadProducts,
    loadProduct,
    searchProducts,
    getProductSuggestions,
    loadBrands,
    loadCategories,
    loadFilterOptions,
  };
};

export default useProducts;