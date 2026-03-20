import api from './api';
import { CartItem, ApiResponse } from '../types';

export interface CartSummary {
  items: CartItem[];
  summary: {
    subtotal: number;
    formatted_subtotal: string;
    items_count: number;
    total_quantity: number;
  };
  loyalty: {
    available_credits: number;
    max_credits_usable: number;
    formatted_available: string;
    formatted_max_usable: string;
  };
  shipping_options: ShippingOption[];
}

export interface ShippingOption {
  id: string;
  name: string;
  description: string;
  cost: number;
  formatted_cost: string;
  estimated_days: string;
}

export interface CartTotals {
  subtotal: number;
  credits_discount: number;
  shipping_fee: number;
  total: number;
  formatted_subtotal: string;
  formatted_credits_discount: string;
  formatted_shipping_fee: string;
  formatted_total: string;
}

export const cartApi = {
  // Get cart with all items and calculations
  getCart: async (): Promise<ApiResponse<CartSummary>> => {
    const response = await api.get('/cart');
    return response.data;
  },

  // Add item to cart
  addItem: async (productId: number, quantity: number): Promise<ApiResponse<CartItem>> => {
    const response = await api.post('/cart/items', {
      product_id: productId,
      quantity,
    });
    return response.data;
  },

  // Update cart item quantity
  updateItem: async (itemId: number, quantity: number): Promise<ApiResponse<CartItem>> => {
    const response = await api.put(`/cart/items/${itemId}`, {
      quantity,
    });
    return response.data;
  },

  // Remove item from cart
  removeItem: async (itemId: number): Promise<ApiResponse<void>> => {
    const response = await api.delete(`/cart/items/${itemId}`);
    return response.data;
  },

  // Clear entire cart
  clearCart: async (): Promise<ApiResponse<void>> => {
    const response = await api.delete('/cart');
    return response.data;
  },

  // Calculate totals with credits and shipping
  calculateTotals: async (
    creditsToUse?: number,
    shippingOption?: string
  ): Promise<ApiResponse<CartTotals>> => {
    const response = await api.post('/cart/calculate-totals', {
      credits_to_use: creditsToUse,
      shipping_option: shippingOption,
    });
    return response.data;
  },

  // Validate cart inventory
  validateInventory: async (): Promise<ApiResponse<{
    valid: boolean;
    errors?: Array<{ product_id: number; message: string }>;
  }>> => {
    const response = await api.get('/cart/validate-inventory');
    return response.data;
  },
};
