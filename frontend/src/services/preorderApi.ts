import axios from 'axios';
import { 
  PreOrder, 
  ApiResponse, 
  PaginatedResponse,
  PaymentRequest,
  PaymentResponse
} from '../types';
import api from './api';

export interface PreOrderFilters {
  status?: 'deposit_pending' | 'deposit_paid' | 'ready_for_payment' | 'completed' | 'cancelled';
  productId?: number;
  sortBy?: 'created_at' | 'estimated_arrival_date' | 'full_payment_due_date';
  sortOrder?: 'asc' | 'desc';
  page?: number;
  perPage?: number;
}

export interface CreatePreOrderRequest {
  productId: number;
  quantity: number;
  depositPercentage?: number;
}

export interface DepositPaymentRequest {
  paymentMethod: 'gcash' | 'maya' | 'bank_transfer';
  gatewayData?: {
    phone?: string;
    accountNumber?: string;
    [key: string]: any;
  };
}

export interface PreOrderNotification {
  id: number;
  type: 'arrival' | 'payment_reminder' | 'status_update';
  title: string;
  message: string;
  isRead: boolean;
  createdAt: string;
}

export const preorderApi = {
  // Get user's pre-orders with filtering
  getPreOrders: async (filters: PreOrderFilters = {}): Promise<PaginatedResponse<PreOrder>> => {
    const params = new URLSearchParams();
    
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        params.append(key, value.toString());
      }
    });

    const response = await api.get(`/preorders?${params.toString()}`);
    return response.data;
  },

  // Create a new pre-order
  createPreOrder: async (request: CreatePreOrderRequest): Promise<ApiResponse<PreOrder>> => {
    const response = await api.post('/preorders', {
      product_id: request.productId,
      quantity: request.quantity,
      deposit_percentage: request.depositPercentage || 0.3
    });
    return response.data;
  },

  // Get single pre-order details
  getPreOrder: async (id: number): Promise<ApiResponse<PreOrder>> => {
    const response = await api.get(`/preorders/${id}`);
    return response.data;
  },

  // Process deposit payment
  payDeposit: async (id: number, request: DepositPaymentRequest): Promise<ApiResponse<PaymentResponse>> => {
    const response = await api.post(`/preorders/${id}/deposit`, {
      payment_method: request.paymentMethod,
      gateway_data: request.gatewayData || {}
    });
    return response.data;
  },

  // Complete final payment
  completePayment: async (id: number, paymentMethod: string): Promise<ApiResponse<PaymentResponse>> => {
    const response = await api.post(`/preorders/${id}/complete-payment`, {
      payment_method: paymentMethod
    });
    return response.data;
  },

  // Get pre-order status
  getPreOrderStatus: async (id: number): Promise<ApiResponse<{
    status: string;
    statusLabel: string;
    depositPaid: boolean;
    paymentCompleted: boolean;
    paymentOverdue: boolean;
    daysUntilDue: number | null;
    canBeCancelled: boolean;
  }>> => {
    const response = await api.get(`/preorders/${id}/status`);
    return response.data;
  },

  // Get pre-order notifications
  getPreOrderNotifications: async (id: number): Promise<ApiResponse<PreOrderNotification[]>> => {
    const response = await api.get(`/preorders/${id}/notifications`);
    return response.data;
  },

  // Cancel pre-order (if allowed)
  cancelPreOrder: async (id: number, reason?: string): Promise<ApiResponse<PreOrder>> => {
    const response = await api.delete(`/preorders/${id}`, {
      data: { reason }
    });
    return response.data;
  }
};

export default preorderApi;