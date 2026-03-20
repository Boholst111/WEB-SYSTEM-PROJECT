import axios from 'axios';
import { 
  Product, 
  ProductFilters, 
  PaginatedResponse, 
  Brand, 
  Category, 
  ApiResponse 
} from '../types';

const API_BASE_URL = process.env.REACT_APP_API_URL || 'http://localhost:8080/api';

const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Add auth token to requests if available
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export const productApi = {
  // Get products with filtering and pagination
  getProducts: async (filters: ProductFilters): Promise<PaginatedResponse<Product>> => {
    const params = new URLSearchParams();
    
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        if (Array.isArray(value)) {
          value.forEach(v => params.append(`${key}[]`, v.toString()));
        } else {
          params.append(key, value.toString());
        }
      }
    });

    const response = await api.get(`/products?${params.toString()}`);
    return response.data;
  },

  // Get single product by ID
  getProduct: async (id: number): Promise<ApiResponse<Product>> => {
    const response = await api.get(`/products/${id}`);
    return response.data;
  },

  // Search products with autocomplete
  searchProducts: async (query: string, limit = 10): Promise<ApiResponse<Product[]>> => {
    const response = await api.get(`/products/search?q=${encodeURIComponent(query)}&limit=${limit}`);
    return response.data;
  },

  // Get product suggestions for autocomplete
  getProductSuggestions: async (query: string): Promise<ApiResponse<string[]>> => {
    const response = await api.get(`/products/suggestions?q=${encodeURIComponent(query)}`);
    return response.data;
  },

  // Get brands
  getBrands: async (): Promise<ApiResponse<Brand[]>> => {
    const response = await api.get('/brands');
    return response.data;
  },

  // Get categories
  getCategories: async (): Promise<ApiResponse<Category[]>> => {
    const response = await api.get('/categories');
    return response.data;
  },

  // Get filter options
  getFilterOptions: async (): Promise<ApiResponse<{
    scales: string[];
    materials: string[];
    features: string[];
  }>> => {
    const response = await api.get('/filters');
    return response.data;
  },
};

// Search API
export const searchApi = {
  // Advanced search with filters
  search: async (query: string, filters?: any, sortBy?: string, sortOrder?: string, perPage?: number): Promise<ApiResponse<any>> => {
    const response = await api.post('/search', {
      query,
      filters,
      sort_by: sortBy,
      sort_order: sortOrder,
      per_page: perPage,
    });
    return response.data;
  },

  // Get autocomplete suggestions
  getAutocomplete: async (query: string, limit = 10): Promise<ApiResponse<string[]>> => {
    const response = await api.get(`/search/autocomplete?query=${encodeURIComponent(query)}&limit=${limit}`);
    return response.data;
  },

  // Get search suggestions with products
  getSuggestions: async (query: string): Promise<ApiResponse<any>> => {
    const response = await api.get(`/search/suggestions?query=${encodeURIComponent(query)}`);
    return response.data;
  },

  // Log search query
  logSearch: async (query: string, resultsCount: number, clickedProductId?: number): Promise<ApiResponse<any>> => {
    const response = await api.post('/search/log', {
      query,
      results_count: resultsCount,
      clicked_product_id: clickedProductId,
    });
    return response.data;
  },

  // Get popular searches
  getPopularSearches: async (limit = 10): Promise<ApiResponse<any>> => {
    const response = await api.get(`/search/popular?limit=${limit}`);
    return response.data;
  },
};

// Recommendation API
export const recommendationApi = {
  // Get personalized recommendations
  getPersonalizedRecommendations: async (limit = 10): Promise<ApiResponse<Product[]>> => {
    const response = await api.get(`/recommendations/personalized?limit=${limit}`);
    return response.data;
  },

  // Get similar products
  getSimilarProducts: async (productId: number, limit = 10): Promise<ApiResponse<Product[]>> => {
    const response = await api.get(`/recommendations/products/${productId}/similar?limit=${limit}`);
    return response.data;
  },

  // Get cross-sell products (frequently bought together)
  getCrossSellProducts: async (productId: number, limit = 6): Promise<ApiResponse<Product[]>> => {
    const response = await api.get(`/recommendations/products/${productId}/cross-sell?limit=${limit}`);
    return response.data;
  },

  // Get upsell products
  getUpsellProducts: async (productId: number, limit = 6): Promise<ApiResponse<Product[]>> => {
    const response = await api.get(`/recommendations/products/${productId}/upsell?limit=${limit}`);
    return response.data;
  },

  // Get trending products
  getTrendingProducts: async (limit = 10): Promise<ApiResponse<Product[]>> => {
    const response = await api.get(`/recommendations/trending?limit=${limit}`);
    return response.data;
  },

  // Get new arrivals
  getNewArrivals: async (limit = 10): Promise<ApiResponse<Product[]>> => {
    const response = await api.get(`/recommendations/new-arrivals?limit=${limit}`);
    return response.data;
  },
};

export default api;