import api from './api';

// Analytics API
export interface AnalyticsFilters {
  date_from?: string;
  date_to?: string;
  period?: 'daily' | 'weekly' | 'monthly';
}

export interface SalesMetrics {
  total_revenue: number;
  total_orders: number;
  average_order_value: number;
  conversion_rate: number;
  growth_rate: number;
  revenue_by_period: Array<{
    date: string;
    revenue: number;
    orders: number;
  }>;
}

export interface ProductPerformance {
  best_sellers: Array<{
    id: number;
    name: string;
    sku: string;
    total_sold: number;
    revenue: number;
  }>;
  slow_movers: Array<{
    id: number;
    name: string;
    sku: string;
    stock_quantity: number;
    days_since_last_sale: number;
  }>;
  inventory_turnover: number;
}

export interface CustomerAnalytics {
  total_customers: number;
  new_customers: number;
  returning_customers: number;
  customer_retention_rate: number;
  loyalty_tier_distribution: Record<string, number>;
  top_customers: Array<{
    id: number;
    name: string;
    email: string;
    total_spent: number;
    order_count: number;
  }>;
}

export interface TrafficAnalysis {
  summary: {
    estimated_visitors: number;
    conversion_rate: number;
    cart_abandonment_rate: number;
    bounce_rate: number;
    avg_session_duration: string;
  };
  popular_products: Array<{
    id: number;
    name: string;
    sku: string;
    view_count: number;
  }>;
  device_types: Record<string, number>;
  peak_hours: Array<{
    hour: number;
    count: number;
  }>;
}

export interface LoyaltyMetrics {
  summary: {
    credits_earned: number;
    credits_redeemed: number;
    active_members: number;
    utilization_rate: number;
    tier_progressions: number;
    expiring_credits: number;
  };
  top_earners: Array<{
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    loyalty_transactions_sum_amount: number;
  }>;
}

export interface InventoryInsights {
  low_stock_products: Array<{
    id: number;
    name: string;
    sku: string;
    stock_quantity: number;
    brand: { name: string };
    category: { name: string };
  }>;
  out_of_stock_count: number;
  total_inventory_value: number;
  preorder_stats: {
    total_preorders: number;
    pending_arrivals: number;
    overdue_arrivals: number;
  };
}

export interface DashboardData {
  sales_analytics: SalesMetrics;
  product_analytics: ProductPerformance;
  customer_analytics: CustomerAnalytics;
  traffic_analysis: TrafficAnalysis;
  loyalty_metrics: LoyaltyMetrics;
  inventory_insights: InventoryInsights;
  date_range: {
    from: string;
    to: string;
    period: string;
  };
}

export interface RealTimeSummary {
  today_orders: number;
  today_revenue: number;
  pending_orders: number;
  low_stock_products: number;
  active_users_today: number;
  conversion_rate_today: number;
}

export const analyticsApi = {
  // Get comprehensive dashboard data
  async getDashboard(filters: AnalyticsFilters = {}) {
    const params = new URLSearchParams();
    
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== '') {
        params.append(key, value.toString());
      }
    });

    const response = await api.get(`/admin/analytics?${params.toString()}`);
    return response.data;
  },

  // Get sales metrics
  async getSalesMetrics(filters: AnalyticsFilters = {}) {
    const params = new URLSearchParams();
    
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== '') {
        params.append(key, value.toString());
      }
    });

    const response = await api.get(`/admin/analytics/sales?${params.toString()}`);
    return response.data;
  },

  // Get product performance
  async getProductPerformance(filters: AnalyticsFilters & { limit?: number } = {}) {
    const params = new URLSearchParams();
    
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== '') {
        params.append(key, value.toString());
      }
    });

    const response = await api.get(`/admin/analytics/products?${params.toString()}`);
    return response.data;
  },

  // Get customer analytics
  async getCustomerAnalytics(filters: AnalyticsFilters = {}) {
    const params = new URLSearchParams();
    
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== '') {
        params.append(key, value.toString());
      }
    });

    const response = await api.get(`/admin/analytics/customers?${params.toString()}`);
    return response.data;
  },

  // Get traffic analysis
  async getTrafficAnalysis(filters: AnalyticsFilters = {}) {
    const params = new URLSearchParams();
    
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== '') {
        params.append(key, value.toString());
      }
    });

    const response = await api.get(`/admin/analytics/traffic?${params.toString()}`);
    return response.data;
  },

  // Get real-time summary
  async getRealTimeSummary() {
    const response = await api.get('/admin/analytics/real-time-summary');
    return response.data;
  },
};

// Order Management API
export interface OrderFilters {
  status?: string;
  payment_status?: string;
  search?: string;
  date_from?: string;
  date_to?: string;
  user_id?: number;
  sort_by?: string;
  sort_direction?: 'asc' | 'desc';
  per_page?: number;
  include_items?: boolean;
}

export interface OrderUpdateData {
  status: string;
  tracking_number?: string;
  courier_service?: string;
  admin_notes?: string;
  notify_customer?: boolean;
}

export interface BulkOrderUpdate {
  order_ids: number[];
  action: 'update_status' | 'add_tracking' | 'cancel' | 'export';
  status?: string;
  tracking_numbers?: string[];
  courier_service?: string;
  admin_notes?: string;
  notify_customers?: boolean;
}

export interface PaymentExceptionData {
  action: 'retry_payment' | 'mark_paid' | 'refund' | 'cancel';
  reason?: string;
  refund_amount?: number;
  admin_notes?: string;
}

export interface InventoryExceptionData {
  action: 'substitute_product' | 'partial_fulfillment' | 'cancel_items' | 'wait_restock';
  items: Array<{
    order_item_id: number;
    substitute_product_id?: number;
    new_quantity?: number;
  }>;
  admin_notes?: string;
  notify_customer?: boolean;
}

export interface ShippingLabelRequest {
  order_ids: number[];
  courier_service: 'lbc' | 'jnt' | 'ninjavan' | '2go';
  service_type?: 'standard' | 'express' | 'same_day';
}

export interface OrderExportRequest {
  format: 'csv' | 'excel' | 'pdf';
  filters?: Record<string, any>;
  columns?: string[];
}

export const orderManagementApi = {
  // Get orders with filtering and pagination
  async getOrders(filters: OrderFilters = {}) {
    const params = new URLSearchParams();
    
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== '') {
        params.append(key, value.toString());
      }
    });

    const response = await api.get(`/admin/orders?${params.toString()}`);
    return response.data;
  },

  // Get single order with full details
  async getOrder(orderId: number) {
    const response = await api.get(`/admin/orders/${orderId}`);
    return response.data;
  },

  // Update order status
  async updateOrderStatus(orderId: number, data: OrderUpdateData) {
    const response = await api.put(`/admin/orders/${orderId}/status`, data);
    return response.data;
  },

  // Bulk update orders
  async bulkUpdateOrders(data: BulkOrderUpdate) {
    const response = await api.post('/admin/orders/bulk-update', data);
    return response.data;
  },

  // Handle payment exceptions
  async handlePaymentException(orderId: number, data: PaymentExceptionData) {
    const response = await api.post(`/admin/orders/${orderId}/payment-exception`, data);
    return response.data;
  },

  // Handle inventory exceptions
  async handleInventoryException(orderId: number, data: InventoryExceptionData) {
    const response = await api.post(`/admin/orders/${orderId}/inventory-exception`, data);
    return response.data;
  },

  // Generate shipping labels
  async generateShippingLabels(data: ShippingLabelRequest) {
    const response = await api.post('/admin/orders/shipping-labels', data);
    return response.data;
  },

  // Get order analytics
  async getOrderAnalytics(filters: AnalyticsFilters & { group_by?: string } = {}) {
    const params = new URLSearchParams();
    
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== '') {
        params.append(key, value.toString());
      }
    });

    const response = await api.get(`/admin/orders/analytics?${params.toString()}`);
    return response.data;
  },

  // Export orders
  async exportOrders(data: OrderExportRequest) {
    const response = await api.post('/admin/orders/export', data);
    return response.data;
  },
};

// User Management API
export interface UserFilters {
  search?: string;
  loyalty_tier?: string;
  status?: string;
  date_from?: string;
  date_to?: string;
  sort_by?: string;
  sort_direction?: 'asc' | 'desc';
  per_page?: number;
}

export interface UserUpdateData {
  first_name?: string;
  last_name?: string;
  email?: string;
  phone?: string;
  loyalty_tier?: string;
  status?: string;
  admin_notes?: string;
}

export const userManagementApi = {
  // Get users with filtering and pagination
  async getUsers(filters: UserFilters = {}) {
    const params = new URLSearchParams();
    
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== '') {
        params.append(key, value.toString());
      }
    });

    const response = await api.get(`/admin/users?${params.toString()}`);
    return response.data;
  },

  // Get single user with full details
  async getUser(userId: number) {
    const response = await api.get(`/admin/users/${userId}`);
    return response.data;
  },

  // Update user
  async updateUser(userId: number, data: UserUpdateData) {
    const response = await api.put(`/admin/users/${userId}`, data);
    return response.data;
  },

  // Get user order history
  async getUserOrders(userId: number, filters: OrderFilters = {}) {
    const params = new URLSearchParams();
    
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== '') {
        params.append(key, value.toString());
      }
    });

    const response = await api.get(`/admin/users/${userId}/orders?${params.toString()}`);
    return response.data;
  },

  // Get user loyalty transactions
  async getUserLoyaltyTransactions(userId: number) {
    const response = await api.get(`/admin/users/${userId}/loyalty-transactions`);
    return response.data;
  },
};