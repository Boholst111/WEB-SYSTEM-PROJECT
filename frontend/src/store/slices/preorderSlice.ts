import { createSlice, createAsyncThunk, PayloadAction } from '@reduxjs/toolkit';
import { PreOrder, PaymentResponse } from '../../types';
import { preorderApi, PreOrderFilters, CreatePreOrderRequest, DepositPaymentRequest } from '../../services/preorderApi';

interface PreOrderState {
  preorders: PreOrder[];
  currentPreOrder: PreOrder | null;
  isLoading: boolean;
  isCreating: boolean;
  isProcessingPayment: boolean;
  error: string | null;
  pagination: {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
  };
  filters: PreOrderFilters;
}

const initialState: PreOrderState = {
  preorders: [],
  currentPreOrder: null,
  isLoading: false,
  isCreating: false,
  isProcessingPayment: false,
  error: null,
  pagination: {
    currentPage: 1,
    lastPage: 1,
    perPage: 10,
    total: 0,
  },
  filters: {},
};

// Async thunks
export const fetchPreOrders = createAsyncThunk(
  'preorders/fetchPreOrders',
  async (filters: PreOrderFilters = {}) => {
    const response = await preorderApi.getPreOrders(filters);
    return response;
  }
);

export const fetchPreOrder = createAsyncThunk(
  'preorders/fetchPreOrder',
  async (id: number) => {
    const response = await preorderApi.getPreOrder(id);
    return response.data;
  }
);

export const createPreOrder = createAsyncThunk(
  'preorders/createPreOrder',
  async (request: CreatePreOrderRequest) => {
    const response = await preorderApi.createPreOrder(request);
    return response.data;
  }
);

export const payDeposit = createAsyncThunk(
  'preorders/payDeposit',
  async ({ id, request }: { id: number; request: DepositPaymentRequest }) => {
    const response = await preorderApi.payDeposit(id, request);
    return { preorderId: id, paymentResponse: response.data };
  }
);

export const completePayment = createAsyncThunk(
  'preorders/completePayment',
  async ({ id, paymentMethod }: { id: number; paymentMethod: string }) => {
    const response = await preorderApi.completePayment(id, paymentMethod);
    return { preorderId: id, paymentResponse: response.data };
  }
);

export const cancelPreOrder = createAsyncThunk(
  'preorders/cancelPreOrder',
  async ({ id, reason }: { id: number; reason?: string }) => {
    const response = await preorderApi.cancelPreOrder(id, reason);
    return response.data;
  }
);

const preorderSlice = createSlice({
  name: 'preorders',
  initialState,
  reducers: {
    setFilters: (state, action: PayloadAction<PreOrderFilters>) => {
      state.filters = { ...state.filters, ...action.payload };
    },
    clearFilters: (state) => {
      state.filters = {};
    },
    clearError: (state) => {
      state.error = null;
    },
    clearCurrentPreOrder: (state) => {
      state.currentPreOrder = null;
    },
    updatePreOrderStatus: (state, action: PayloadAction<{ id: number; status: string }>) => {
      const { id, status } = action.payload;
      const preorder = state.preorders.find(p => p.id === id);
      if (preorder) {
        preorder.status = status as any;
      }
      if (state.currentPreOrder && state.currentPreOrder.id === id) {
        state.currentPreOrder.status = status as any;
      }
    },
  },
  extraReducers: (builder) => {
    builder
      // Fetch pre-orders
      .addCase(fetchPreOrders.pending, (state) => {
        state.isLoading = true;
        state.error = null;
      })
      .addCase(fetchPreOrders.fulfilled, (state, action) => {
        state.isLoading = false;
        state.preorders = action.payload.data;
        state.pagination = action.payload.meta;
      })
      .addCase(fetchPreOrders.rejected, (state, action) => {
        state.isLoading = false;
        state.error = action.error.message || 'Failed to fetch pre-orders';
      })
      
      // Fetch single pre-order
      .addCase(fetchPreOrder.pending, (state) => {
        state.isLoading = true;
        state.error = null;
      })
      .addCase(fetchPreOrder.fulfilled, (state, action) => {
        state.isLoading = false;
        state.currentPreOrder = action.payload;
      })
      .addCase(fetchPreOrder.rejected, (state, action) => {
        state.isLoading = false;
        state.error = action.error.message || 'Failed to fetch pre-order';
      })
      
      // Create pre-order
      .addCase(createPreOrder.pending, (state) => {
        state.isCreating = true;
        state.error = null;
      })
      .addCase(createPreOrder.fulfilled, (state, action) => {
        state.isCreating = false;
        state.preorders.unshift(action.payload);
        state.currentPreOrder = action.payload;
      })
      .addCase(createPreOrder.rejected, (state, action) => {
        state.isCreating = false;
        state.error = action.error.message || 'Failed to create pre-order';
      })
      
      // Pay deposit
      .addCase(payDeposit.pending, (state) => {
        state.isProcessingPayment = true;
        state.error = null;
      })
      .addCase(payDeposit.fulfilled, (state, action) => {
        state.isProcessingPayment = false;
        const { preorderId } = action.payload;
        const preorder = state.preorders.find(p => p.id === preorderId);
        if (preorder) {
          preorder.status = 'deposit_paid';
          preorder.depositPaidAt = new Date().toISOString();
        }
        if (state.currentPreOrder && state.currentPreOrder.id === preorderId) {
          state.currentPreOrder.status = 'deposit_paid';
          state.currentPreOrder.depositPaidAt = new Date().toISOString();
        }
      })
      .addCase(payDeposit.rejected, (state, action) => {
        state.isProcessingPayment = false;
        state.error = action.error.message || 'Failed to process deposit payment';
      })
      
      // Complete payment
      .addCase(completePayment.pending, (state) => {
        state.isProcessingPayment = true;
        state.error = null;
      })
      .addCase(completePayment.fulfilled, (state, action) => {
        state.isProcessingPayment = false;
        const { preorderId } = action.payload;
        const preorder = state.preorders.find(p => p.id === preorderId);
        if (preorder) {
          preorder.status = 'completed';
        }
        if (state.currentPreOrder && state.currentPreOrder.id === preorderId) {
          state.currentPreOrder.status = 'completed';
        }
      })
      .addCase(completePayment.rejected, (state, action) => {
        state.isProcessingPayment = false;
        state.error = action.error.message || 'Failed to complete payment';
      })
      
      // Cancel pre-order
      .addCase(cancelPreOrder.fulfilled, (state, action) => {
        const cancelledPreOrder = action.payload;
        state.preorders = state.preorders.map(p => 
          p.id === cancelledPreOrder.id ? cancelledPreOrder : p
        );
        if (state.currentPreOrder && state.currentPreOrder.id === cancelledPreOrder.id) {
          state.currentPreOrder = cancelledPreOrder;
        }
      })
      .addCase(cancelPreOrder.rejected, (state, action) => {
        state.error = action.error.message || 'Failed to cancel pre-order';
      });
  },
});

export const { 
  setFilters, 
  clearFilters, 
  clearError, 
  clearCurrentPreOrder,
  updatePreOrderStatus 
} = preorderSlice.actions;

export default preorderSlice.reducer;