import { createSlice, PayloadAction } from '@reduxjs/toolkit';
import { Product, ProductFilters, Brand, Category } from '../../types';

interface ProductState {
  products: Product[];
  currentProduct: Product | null;
  brands: Brand[];
  categories: Category[];
  filters: ProductFilters;
  isLoading: boolean;
  error: string | null;
  pagination: {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
  };
}

const initialState: ProductState = {
  products: [],
  currentProduct: null,
  brands: [],
  categories: [],
  filters: {
    page: 1,
    limit: 20,
    sortBy: 'name',
    sortOrder: 'asc',
  },
  isLoading: false,
  error: null,
  pagination: {
    currentPage: 1,
    lastPage: 1,
    perPage: 20,
    total: 0,
  },
};

const productSlice = createSlice({
  name: 'products',
  initialState,
  reducers: {
    setLoading: (state, action: PayloadAction<boolean>) => {
      state.isLoading = action.payload;
    },
    setError: (state, action: PayloadAction<string | null>) => {
      state.error = action.payload;
    },
    setProducts: (state, action: PayloadAction<Product[]>) => {
      state.products = action.payload;
    },
    setCurrentProduct: (state, action: PayloadAction<Product | null>) => {
      state.currentProduct = action.payload;
    },
    setBrands: (state, action: PayloadAction<Brand[]>) => {
      state.brands = action.payload;
    },
    setCategories: (state, action: PayloadAction<Category[]>) => {
      state.categories = action.payload;
    },
    setFilters: (state, action: PayloadAction<Partial<ProductFilters>>) => {
      state.filters = { ...state.filters, ...action.payload };
    },
    clearFilters: (state) => {
      state.filters = {
        page: 1,
        limit: 20,
        sortBy: 'name',
        sortOrder: 'asc',
      };
    },
    setPagination: (state, action: PayloadAction<{
      currentPage: number;
      lastPage: number;
      perPage: number;
      total: number;
    }>) => {
      state.pagination = action.payload;
    },
    addProduct: (state, action: PayloadAction<Product>) => {
      state.products.push(action.payload);
    },
    updateProduct: (state, action: PayloadAction<Product>) => {
      const index = state.products.findIndex(p => p.id === action.payload.id);
      if (index !== -1) {
        state.products[index] = action.payload;
      }
      if (state.currentProduct?.id === action.payload.id) {
        state.currentProduct = action.payload;
      }
    },
    removeProduct: (state, action: PayloadAction<number>) => {
      state.products = state.products.filter(p => p.id !== action.payload);
      if (state.currentProduct?.id === action.payload) {
        state.currentProduct = null;
      }
    },
  },
});

export const {
  setLoading,
  setError,
  setProducts,
  setCurrentProduct,
  setBrands,
  setCategories,
  setFilters,
  clearFilters,
  setPagination,
  addProduct,
  updateProduct,
  removeProduct,
} = productSlice.actions;

export default productSlice.reducer;