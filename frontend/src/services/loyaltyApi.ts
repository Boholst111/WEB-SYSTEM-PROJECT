import api from './api';
import { 
  LoyaltyTransaction, 
  ApiResponse, 
  PaginatedResponse 
} from '../types';

export interface LoyaltyBalance {
  available_credits: number;
  total_earned: number;
  total_redeemed: number;
  expiring_soon: number;
  expiring_days: number;
  current_tier: 'bronze' | 'silver' | 'gold' | 'platinum';
  tier_benefits: {
    credits_multiplier: number;
    bonus_rate: number;
    free_shipping_threshold: number;
    early_access: boolean;
    priority_support: boolean;
  };
  next_tier: string | null;
  progress_to_next_tier: number;
  total_spent: number;
}

export interface TierStatus {
  current_tier: 'bronze' | 'silver' | 'gold' | 'platinum';
  current_tier_benefits: {
    credits_multiplier: number;
    bonus_rate: number;
    free_shipping_threshold: number;
    early_access: boolean;
    priority_support: boolean;
  };
  next_tier: string | null;
  next_tier_benefits: any;
  total_spent: number;
  progress_percentage: number;
  spending_to_next_tier: number | null;
  tier_thresholds: Record<string, number>;
  tier_history: Array<{
    tier: string;
    achieved_at: string;
    threshold_met: number;
  }>;
}

export interface RedemptionResult {
  transaction_id: number;
  redeemed_credits: number;
  discount_amount: number;
  remaining_credits: number;
  conversion_rate: number;
  created_at: string;
}

export interface EarningsCalculation {
  purchase_amount: number;
  base_rate: number;
  tier_multiplier: number;
  bonus_rate: number;
  base_credits: number;
  tier_credits: number;
  bonus_credits: number;
  total_credits: number;
  current_tier: string;
}

export interface ExpiringCredits {
  total_expiring: number;
  warning_days: number;
  expiring_by_date: Array<{
    date: string;
    amount: number;
    transaction_count: number;
    days_until_expiration: number;
  }>;
  transactions: Array<{
    id: number;
    amount: number;
    expires_at: string;
    days_until_expiration: number;
    description: string;
    created_at: string;
  }>;
}

export const loyaltyApi = {
  // Get user's loyalty balance and tier information
  getBalance: async (): Promise<ApiResponse<LoyaltyBalance>> => {
    const response = await api.get('/loyalty/balance');
    return response.data;
  },

  // Get user's loyalty transaction history
  getTransactions: async (params?: {
    per_page?: number;
    type?: 'earned' | 'redeemed' | 'expired' | 'bonus' | 'adjustment';
    start_date?: string;
    end_date?: string;
    page?: number;
  }): Promise<PaginatedResponse<LoyaltyTransaction>> => {
    const queryParams = new URLSearchParams();
    
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null) {
          queryParams.append(key, value.toString());
        }
      });
    }

    const response = await api.get(`/loyalty/transactions?${queryParams.toString()}`);
    return response.data;
  },

  // Redeem loyalty credits
  redeemCredits: async (params: {
    amount: number;
    order_total: number;
    order_id?: number;
    description?: string;
  }): Promise<ApiResponse<RedemptionResult>> => {
    const response = await api.post('/loyalty/redeem', params);
    return response.data;
  },

  // Get tier status and progression
  getTierStatus: async (): Promise<ApiResponse<TierStatus>> => {
    const response = await api.get('/loyalty/tier-status');
    return response.data;
  },

  // Calculate credits that would be earned from a purchase
  calculateEarnings: async (purchaseAmount: number): Promise<ApiResponse<EarningsCalculation>> => {
    const response = await api.post('/loyalty/calculate-earnings', {
      purchase_amount: purchaseAmount
    });
    return response.data;
  },

  // Get credits expiring soon
  getExpiringCredits: async (days?: number): Promise<ApiResponse<ExpiringCredits>> => {
    const params = days ? `?days=${days}` : '';
    const response = await api.get(`/loyalty/expiring-credits${params}`);
    return response.data;
  },
};

export default loyaltyApi;