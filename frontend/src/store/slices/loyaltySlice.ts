import { createSlice, PayloadAction } from '@reduxjs/toolkit';
import { LoyaltyState, LoyaltyTransaction } from '../../types';

const initialState: LoyaltyState = {
  balance: 0,
  tier: 'bronze',
  transactions: [],
  isLoading: false,
  error: null,
};

const loyaltySlice = createSlice({
  name: 'loyalty',
  initialState,
  reducers: {
    setLoading: (state, action: PayloadAction<boolean>) => {
      state.isLoading = action.payload;
    },
    setError: (state, action: PayloadAction<string | null>) => {
      state.error = action.payload;
    },
    setBalance: (state, action: PayloadAction<number>) => {
      state.balance = action.payload;
    },
    setTier: (state, action: PayloadAction<'bronze' | 'silver' | 'gold' | 'platinum'>) => {
      state.tier = action.payload;
    },
    setTransactions: (state, action: PayloadAction<LoyaltyTransaction[]>) => {
      state.transactions = action.payload;
    },
    addTransaction: (state, action: PayloadAction<LoyaltyTransaction>) => {
      state.transactions.unshift(action.payload);
      state.balance = action.payload.balanceAfter;
    },
    updateBalance: (state, action: PayloadAction<{ amount: number; newBalance: number }>) => {
      state.balance = action.payload.newBalance;
    },
    clearTransactions: (state) => {
      state.transactions = [];
    },
  },
});

export const {
  setLoading,
  setError,
  setBalance,
  setTier,
  setTransactions,
  addTransaction,
  updateBalance,
  clearTransactions,
} = loyaltySlice.actions;

export default loyaltySlice.reducer;