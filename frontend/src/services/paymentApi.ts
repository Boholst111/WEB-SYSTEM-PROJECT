import api from './api';
import { PaymentMethod, PaymentRequest, PaymentResponse } from '../types';

export interface PaymentFormData {
  paymentMethod: 'gcash' | 'maya' | 'bank_transfer';
  amount: number;
  orderId?: number;
  preorderId?: number;
  phone?: string;
  bank?: 'bpi' | 'bdo' | 'metrobank';
  successUrl?: string;
  failureUrl?: string;
  cancelUrl?: string;
  items?: Array<{
    name: string;
    quantity: number;
    amount: {
      value: number;
      currency: string;
    };
  }>;
}

export interface PaymentStatusResponse {
  id: string;
  status: 'pending' | 'processing' | 'completed' | 'failed' | 'cancelled';
  amount: number;
  currency: string;
  paymentMethod: string;
  referenceNumber?: string;
  paymentUrl?: string;
  message?: string;
  createdAt: string;
  updatedAt: string;
}

export const paymentApi = {
  // Get available payment methods
  getPaymentMethods: async (): Promise<{ success: boolean; payment_methods: PaymentMethod[] }> => {
    const response = await api.get('/payments/methods');
    return response.data;
  },

  // Process GCash payment
  processGCashPayment: async (data: PaymentFormData): Promise<PaymentResponse> => {
    const response = await api.post('/payments/gcash', {
      order_id: data.orderId,
      preorder_id: data.preorderId,
      amount: data.amount,
      success_url: data.successUrl || `${window.location.origin}/payment/success`,
      failure_url: data.failureUrl || `${window.location.origin}/payment/failed`,
      cancel_url: data.cancelUrl || `${window.location.origin}/payment/cancelled`,
    });
    return response.data;
  },

  // Process Maya payment
  processMayaPayment: async (data: PaymentFormData): Promise<PaymentResponse> => {
    const response = await api.post('/payments/maya', {
      order_id: data.orderId,
      preorder_id: data.preorderId,
      amount: data.amount,
      success_url: data.successUrl || `${window.location.origin}/payment/success`,
      failure_url: data.failureUrl || `${window.location.origin}/payment/failed`,
      cancel_url: data.cancelUrl || `${window.location.origin}/payment/cancelled`,
      items: data.items,
    });
    return response.data;
  },

  // Process Bank Transfer payment
  processBankTransferPayment: async (data: PaymentFormData): Promise<PaymentResponse> => {
    const response = await api.post('/payments/bank-transfer', {
      order_id: data.orderId,
      preorder_id: data.preorderId,
      amount: data.amount,
      bank: data.bank,
    });
    return response.data;
  },

  // Get payment status
  getPaymentStatus: async (paymentId: string): Promise<PaymentStatusResponse> => {
    const response = await api.get(`/payments/${paymentId}/status`);
    return response.data;
  },

  // Verify payment manually
  verifyPayment: async (paymentId: string): Promise<PaymentStatusResponse> => {
    const response = await api.post(`/payments/${paymentId}/verify`);
    return response.data;
  },

  // Refund payment
  refundPayment: async (paymentId: string, amount?: number, reason?: string): Promise<{ success: boolean; message: string }> => {
    const response = await api.post(`/payments/${paymentId}/refund`, {
      amount,
      reason,
    });
    return response.data;
  },
};

export default paymentApi;