export interface Brand {
  id: number;
  name: string;
  slug: string;
  description?: string;
  logo?: string;
  website?: string;
  created_at: string;
  updated_at: string;
}

export interface Category {
  id: number;
  name: string;
  slug: string;
  description?: string;
  image?: string;
  parent_id?: number;
  created_at: string;
  updated_at: string;
}

export interface InventoryMovement {
  id: number;
  product_id: number;
  movement_type: 'purchase' | 'sale' | 'return' | 'adjustment' | 'damage' | 'reservation' | 'release';
  quantity_change: number;
  quantity_before: number;
  quantity_after: number;
  reference_type?: string;
  reference_id?: string;
  reason?: string;
  created_by?: number;
  created_at: string;
  updated_at: string;
}

export interface Product {
  id: number;
  sku: string;
  name: string;
  description?: string;
  brand_id: number;
  category_id: number;
  scale?: string;
  material?: string;
  features?: string[];
  is_chase_variant: boolean;
  base_price: number;
  current_price: number;
  stock_quantity: number;
  is_preorder: boolean;
  preorder_date?: string;
  estimated_arrival_date?: string;
  status: 'active' | 'inactive' | 'discontinued';
  images?: string[];
  specifications?: Record<string, any>;
  weight?: number;
  dimensions?: Record<string, number>;
  minimum_age?: number;
  created_at: string;
  updated_at: string;
  
  // Relationships
  brand?: Brand;
  category?: Category;
  inventory_movements?: InventoryMovement[];
  
  // Computed attributes
  main_image?: string;
  formatted_price?: string;
  discount_percentage?: number;
  average_rating?: number;
  review_count?: number;
}

export interface User {
  id: number;
  email: string;
  first_name?: string;
  last_name?: string;
  phone?: string;
  date_of_birth?: string;
  loyalty_tier: 'bronze' | 'silver' | 'gold' | 'platinum';
  loyalty_credits: number;
  total_spent: number;
  email_verified_at?: string;
  phone_verified_at?: string;
  status: 'active' | 'inactive' | 'suspended';
  preferences?: Record<string, any>;
  created_at: string;
  updated_at: string;
}

export interface PreOrder {
  id: number;
  preorder_number: string;
  product_id: number;
  user_id: number;
  quantity: number;
  deposit_amount: number;
  remaining_amount: number;
  total_amount: number;
  deposit_paid_at?: string;
  full_payment_due_date?: string;
  status: 'deposit_pending' | 'deposit_paid' | 'ready_for_payment' | 'payment_completed' | 'shipped' | 'delivered' | 'cancelled' | 'expired';
  estimated_arrival_date?: string;
  actual_arrival_date?: string;
  payment_method?: string;
  shipping_address?: Record<string, any>;
  notes?: string;
  admin_notes?: string;
  notification_sent: boolean;
  payment_reminder_sent_at?: string;
  created_at: string;
  updated_at: string;
  
  // Relationships
  product?: Product;
  user?: User;
  
  // Computed attributes
  status_label?: string;
  formatted_total?: string;
  formatted_deposit?: string;
  formatted_remaining?: string;
  days_until_due?: number;
}

export interface InventorySummary {
  total_products: number;
  in_stock: number;
  low_stock: number;
  out_of_stock: number;
  preorders: number;
  chase_variants: number;
}

export interface LowStockSummary {
  threshold: number;
  count: number;
}

export interface ChaseVariantSummary {
  total_chase_variants: number;
  available: number;
  sold_out: number;
  average_price: number;
}

export interface InventoryReport {
  period: {
    from: string;
    to: string;
  };
  movements: Array<{
    movement_type: string;
    count: number;
    total_quantity: number;
  }>;
  top_selling: Array<{
    product_id: number;
    total_sold: number;
    product?: Product;
  }>;
  slow_moving: Product[];
  stock_value: {
    in_stock_value: number;
    preorder_value: number;
    low_stock_count: number;
    out_of_stock_count: number;
  };
  summary: {
    total_movements: number;
    total_quantity_moved: number;
    stock_value: number;
    preorder_value: number;
    low_stock_alerts: number;
    out_of_stock_items: number;
  };
}

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
}

export interface ApiResponse<T> {
  success: boolean;
  message?: string;
  data: T;
  errors?: Record<string, string[]>;
}