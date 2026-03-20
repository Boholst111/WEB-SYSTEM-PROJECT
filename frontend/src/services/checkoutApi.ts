import api from './api';
import { ApiResponse, Order, ShippingAddress } from '../types';

export interface UserAddress {
  id: number;
  user_id: number;
  type: 'shipping' | 'billing';
  first_name: string;
  last_name: string;
  company?: string;
  address_line_1: string;
  address_line_2?: string;
  city: string;
  province: string;
  postal_code: string;
  country: string;
  phone: string;
  is_default: boolean;
  created_at: string;
  updated_at: string;
}

export interface CheckoutSession {
  cart_items: any[];
  summary: {
    subtotal: number;
    items_count: number;
    total_quantity: number;
  };
  loyalty: {
    available_credits: number;
    max_credits_usable: number;
  };
  shipping_options: any[];
  addresses: UserAddress[];
}

export interface CreateOrderRequest {
  shipping_address_id: number;
  payment_method: string;
  shipping_option: string;
  credits_to_use?: number;
  notes?: string;
}

export interface CreateOrderResponse {
  order: Order;
  payment_url?: string;
  reference_number?: string;
}

export const checkoutApi = {
  // Initialize checkout session
  initializeCheckout: async (): Promise<ApiResponse<CheckoutSession>> => {
    const response = await api.post('/checkout/initialize');
    return response.data;
  },

  // Calculate checkout totals
  calculateTotals: async (
    creditsToUse?: number,
    shippingOption?: string
  ): Promise<ApiResponse<any>> => {
    const response = await api.post('/checkout/calculate-totals', {
      credits_to_use: creditsToUse,
      shipping_option: shippingOption,
    });
    return response.data;
  },

  // Get user addresses
  getAddresses: async (): Promise<ApiResponse<UserAddress[]>> => {
    const response = await api.get('/checkout/addresses');
    return response.data;
  },

  // Create new address
  createAddress: async (address: Partial<UserAddress>): Promise<ApiResponse<UserAddress>> => {
    const response = await api.post('/checkout/addresses', address);
    return response.data;
  },

  // Update address
  updateAddress: async (
    addressId: number,
    address: Partial<UserAddress>
  ): Promise<ApiResponse<UserAddress>> => {
    const response = await api.put(`/checkout/addresses/${addressId}`, address);
    return response.data;
  },

  // Delete address
  deleteAddress: async (addressId: number): Promise<ApiResponse<void>> => {
    const response = await api.delete(`/checkout/addresses/${addressId}`);
    return response.data;
  },

  // Create order
  createOrder: async (orderData: CreateOrderRequest): Promise<ApiResponse<CreateOrderResponse>> => {
    const response = await api.post('/checkout/orders', orderData);
    return response.data;
  },

  // Process payment for order
  processPayment: async (
    orderId: number,
    gateway: string
  ): Promise<ApiResponse<any>> => {
    const response = await api.post(`/checkout/orders/${orderId}/payment`, {
      gateway,
    });
    return response.data;
  },

  // Get order details
  getOrder: async (orderId: number): Promise<ApiResponse<any>> => {
    const response = await api.get(`/checkout/orders/${orderId}`);
    return response.data;
  },
};
