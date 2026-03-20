// User and Authentication Types
export interface User {
  id: number;
  email: string;
  firstName: string;
  lastName: string;
  phone?: string;
  dateOfBirth?: string;
  loyaltyTier: 'bronze' | 'silver' | 'gold' | 'platinum';
  loyaltyCredits: number;
  totalSpent: number;
  emailVerifiedAt?: string;
  phoneVerifiedAt?: string;
  status: 'active' | 'inactive' | 'suspended';
  role?: 'user' | 'admin';
  preferences?: Record<string, any>;
  createdAt: string;
  updatedAt: string;
}

export interface AuthState {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  error: string | null;
}

// Product Types
export interface Product {
  id: number;
  sku: string;
  name: string;
  description: string;
  brandId: number;
  categoryId: number;
  scale: string;
  material: string;
  features: string[];
  isChaseVariant: boolean;
  basePrice: number;
  currentPrice: number;
  stockQuantity: number;
  isPreorder: boolean;
  preorderDate?: string;
  status: 'active' | 'inactive' | 'discontinued';
  images: string[];
  specifications: Record<string, any>;
  brand?: Brand;
  category?: Category;
  createdAt: string;
  updatedAt: string;
}

export interface Brand {
  id: number;
  name: string;
  slug: string;
  description?: string;
  logo?: string;
  isActive: boolean;
}

export interface Category {
  id: number;
  name: string;
  slug: string;
  description?: string;
  parentId?: number;
  isActive: boolean;
  children?: Category[];
}

// Cart Types
export interface CartItem {
  id: number;
  productId: number;
  quantity: number;
  price: number;
  product: Product;
}

export interface CartState {
  items: CartItem[];
  total: number;
  itemCount: number;
  isLoading: boolean;
  error: string | null;
}

// Order Types
export interface Order {
  id: number;
  orderNumber: string;
  userId: number;
  status: 'pending' | 'confirmed' | 'processing' | 'shipped' | 'delivered' | 'cancelled';
  subtotal: number;
  creditsUsed: number;
  discountAmount: number;
  shippingFee: number;
  totalAmount: number;
  paymentMethod: string;
  paymentStatus: 'pending' | 'paid' | 'failed' | 'refunded';
  shippingAddress: ShippingAddress;
  notes?: string;
  items: OrderItem[];
  createdAt: string;
  updatedAt: string;
}

export interface OrderItem {
  id: number;
  orderId: number;
  productId: number;
  quantity: number;
  price: number;
  product: Product;
}

export interface ShippingAddress {
  firstName: string;
  lastName: string;
  company?: string;
  address1: string;
  address2?: string;
  city: string;
  province: string;
  postalCode: string;
  country: string;
  phone: string;
}

// Pre-order Types
export interface PreOrder {
  id: number;
  productId: number;
  userId: number;
  quantity: number;
  depositAmount: number;
  remainingAmount: number;
  depositPaidAt?: string;
  fullPaymentDueDate: string;
  status: 'deposit_pending' | 'deposit_paid' | 'ready_for_payment' | 'completed' | 'cancelled';
  estimatedArrivalDate: string;
  actualArrivalDate?: string;
  notes?: string;
  product: Product;
  createdAt: string;
  updatedAt: string;
}

// Loyalty Types
export interface LoyaltyTransaction {
  id: number;
  userId: number;
  orderId?: number;
  transactionType: 'earned' | 'redeemed' | 'expired' | 'bonus' | 'adjustment';
  amount: number;
  balanceAfter: number;
  description: string;
  referenceId?: string;
  expiresAt?: string;
  createdAt: string;
}

export interface LoyaltyState {
  balance: number;
  tier: 'bronze' | 'silver' | 'gold' | 'platinum';
  transactions: LoyaltyTransaction[];
  isLoading: boolean;
  error: string | null;
}

// Filter Types
export interface ProductFilters {
  search?: string;
  categoryId?: number;
  brandId?: number;
  scale?: string[];
  material?: string[];
  features?: string[];
  isChaseVariant?: boolean;
  isPreorder?: boolean;
  minPrice?: number;
  maxPrice?: number;
  inStock?: boolean;
  sortBy?: 'name' | 'price' | 'created_at' | 'popularity';
  sortOrder?: 'asc' | 'desc';
  page?: number;
  limit?: number;
}

// API Response Types
export interface ApiResponse<T> {
  data: T;
  message?: string;
  success: boolean;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
    from: number;
    to: number;
  };
  links: {
    first: string;
    last: string;
    prev?: string;
    next?: string;
  };
}

// Payment Types
export interface PaymentMethod {
  id: string;
  name: string;
  type: 'gcash' | 'maya' | 'bank_transfer';
  isActive: boolean;
  config?: Record<string, any>;
}

export interface PaymentRequest {
  amount: number;
  currency: string;
  paymentMethod: string;
  orderId: number;
  returnUrl?: string;
  cancelUrl?: string;
}

export interface PaymentResponse {
  id: string;
  status: 'pending' | 'processing' | 'completed' | 'failed' | 'cancelled';
  paymentUrl?: string;
  referenceNumber?: string;
  message?: string;
}