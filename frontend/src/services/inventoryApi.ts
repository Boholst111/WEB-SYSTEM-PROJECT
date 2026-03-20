import api from './api';

export interface InventoryFilters {
  search?: string;
  stock_status?: string;
  category_id?: string;
  brand_id?: string;
  chase_variants?: boolean;
  sort_by?: string;
  sort_order?: string;
  page?: number;
  per_page?: number;
}

export interface LowStockFilters {
  threshold?: number;
}

export interface PreOrderArrivalFilters {
  arrival_status?: string;
  estimated_from?: string;
  estimated_to?: string;
  page?: number;
  per_page?: number;
}

export interface ChaseVariantFilters {
  availability?: string;
  page?: number;
  per_page?: number;
}

export interface StockUpdateData {
  quantity: number;
  type: 'restock' | 'adjustment' | 'damage' | 'return';
  reason: string;
}

export interface PreOrderArrivalUpdate {
  actual_arrival_date: string;
  notes?: string;
}

export interface PurchaseOrderData {
  supplier_name: string;
  supplier_email: string;
  expected_delivery_date: string;
  notes?: string;
  products: Array<{
    product_id: number;
    quantity: number;
    unit_cost: number;
  }>;
}

export const inventoryApi = {
  // Get inventory overview with filtering
  async getInventory(filters: InventoryFilters = {}) {
    const params = new URLSearchParams();
    
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== '') {
        params.append(key, value.toString());
      }
    });

    const response = await api.get(`/admin/inventory?${params.toString()}`);
    return response.data;
  },

  // Get low stock products
  async getLowStock(filters: LowStockFilters = {}) {
    const params = new URLSearchParams();
    
    if (filters.threshold) {
      params.append('threshold', filters.threshold.toString());
    }

    const response = await api.get(`/admin/inventory/low-stock?${params.toString()}`);
    return response.data;
  },

  // Update product stock
  async updateStock(productId: number, data: StockUpdateData) {
    const response = await api.put(`/admin/inventory/${productId}/stock`, data);
    return response.data;
  },

  // Get inventory reports
  async getReports(dateFrom?: string, dateTo?: string) {
    const params = new URLSearchParams();
    
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);

    const response = await api.get(`/admin/inventory/reports?${params.toString()}`);
    return response.data;
  },

  // Get pre-order arrivals
  async getPreOrderArrivals(filters: PreOrderArrivalFilters = {}) {
    const params = new URLSearchParams();
    
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== '') {
        params.append(key, value.toString());
      }
    });

    const response = await api.get(`/admin/inventory/preorder-arrivals?${params.toString()}`);
    return response.data;
  },

  // Update pre-order arrival
  async updatePreOrderArrival(preorderId: number, data: PreOrderArrivalUpdate) {
    const response = await api.put(`/admin/inventory/preorder-arrivals/${preorderId}`, data);
    return response.data;
  },

  // Get chase variants
  async getChaseVariants(filters: ChaseVariantFilters = {}) {
    const params = new URLSearchParams();
    
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== '') {
        params.append(key, value.toString());
      }
    });

    const response = await api.get(`/admin/inventory/chase-variants?${params.toString()}`);
    return response.data;
  },

  // Create purchase order
  async createPurchaseOrder(data: PurchaseOrderData) {
    const response = await api.post('/admin/inventory/purchase-orders', data);
    return response.data;
  }
};