import React, { useState, useEffect } from 'react';
import { loyaltyApi, LoyaltyBalance, RedemptionResult } from '../services/loyaltyApi';
import { useAppSelector } from '../store';

interface CreditsRedemptionProps {
  orderTotal: number;
  onRedemptionChange: (redemption: {
    creditsUsed: number;
    discountAmount: number;
    remainingCredits: number;
  } | null) => void;
  className?: string;
  disabled?: boolean;
}

const CreditsRedemption: React.FC<CreditsRedemptionProps> = ({
  orderTotal,
  onRedemptionChange,
  className = '',
  disabled = false
}) => {
  const [balance, setBalance] = useState<LoyaltyBalance | null>(null);
  const [creditsToRedeem, setCreditsToRedeem] = useState<number>(0);
  const [isRedemptionEnabled, setIsRedemptionEnabled] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [redemptionResult, setRedemptionResult] = useState<RedemptionResult | null>(null);

  const { isAuthenticated } = useAppSelector(state => state.auth);

  const minimumRedemption = 100; // From config
  const maximumPercentage = 50; // From config
  const conversionRate = 1.0; // 1 credit = 1 PHP

  useEffect(() => {
    if (isAuthenticated && orderTotal > 0) {
      loadBalance();
    }
  }, [isAuthenticated, orderTotal]);

  useEffect(() => {
    if (isRedemptionEnabled && creditsToRedeem > 0) {
      calculateRedemption();
    } else {
      onRedemptionChange(null);
      setRedemptionResult(null);
    }
  }, [creditsToRedeem, isRedemptionEnabled]);

  const loadBalance = async () => {
    try {
      setLoading(true);
      const response = await loyaltyApi.getBalance();
      if (response.success) {
        setBalance(response.data);
      }
    } catch (err) {
      setError('Failed to load credits balance');
      console.error('Error loading balance:', err);
    } finally {
      setLoading(false);
    }
  };

  const calculateRedemption = () => {
    if (!balance || creditsToRedeem <= 0) return;

    const maxRedemptionAmount = (orderTotal * maximumPercentage) / 100;
    const maxCredits = Math.min(balance.available_credits, maxRedemptionAmount);
    const actualCredits = Math.min(creditsToRedeem, maxCredits);
    const discountAmount = actualCredits * conversionRate;
    const remainingCredits = balance.available_credits - actualCredits;

    const redemption = {
      creditsUsed: actualCredits,
      discountAmount,
      remainingCredits
    };

    onRedemptionChange(redemption);
  };

  const handleToggleRedemption = () => {
    setIsRedemptionEnabled(!isRedemptionEnabled);
    if (isRedemptionEnabled) {
      setCreditsToRedeem(0);
    }
  };

  const handleCreditsChange = (value: number) => {
    if (!balance) return;

    const maxRedemptionAmount = (orderTotal * maximumPercentage) / 100;
    const maxCredits = Math.min(balance.available_credits, maxRedemptionAmount);
    const clampedValue = Math.max(0, Math.min(value, maxCredits));
    
    setCreditsToRedeem(clampedValue);
  };

  const handleMaxCredits = () => {
    if (!balance) return;

    const maxRedemptionAmount = (orderTotal * maximumPercentage) / 100;
    const maxCredits = Math.min(balance.available_credits, maxRedemptionAmount);
    setCreditsToRedeem(maxCredits);
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2,
    }).format(amount);
  };

  if (!isAuthenticated) {
    return null;
  }

  if (loading) {
    return (
      <div className={`bg-gray-50 rounded-lg p-4 ${className}`}>
        <div className="animate-pulse">
          <div className="h-4 bg-gray-200 rounded w-1/3 mb-2"></div>
          <div className="h-6 bg-gray-200 rounded w-1/2"></div>
        </div>
      </div>
    );
  }

  if (error || !balance) {
    return (
      <div className={`bg-red-50 border border-red-200 rounded-lg p-4 ${className}`}>
        <p className="text-red-600 text-sm">{error || 'Unable to load credits'}</p>
      </div>
    );
  }

  const maxRedemptionAmount = (orderTotal * maximumPercentage) / 100;
  const maxCredits = Math.min(balance.available_credits, maxRedemptionAmount);
  const canRedeem = balance.available_credits >= minimumRedemption && orderTotal > 0;

  if (!canRedeem) {
    return (
      <div className={`bg-gray-50 rounded-lg p-4 ${className}`}>
        <div className="flex items-center space-x-2 text-gray-600">
          <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div>
            <p className="font-medium">Credits Redemption Not Available</p>
            <p className="text-sm">
              {balance.available_credits < minimumRedemption 
                ? `Minimum ${minimumRedemption} credits required`
                : 'Add items to your cart to redeem credits'
              }
            </p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className={`bg-blue-50 border border-blue-200 rounded-lg p-4 ${className}`}>
      {/* Header */}
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center space-x-2">
          <svg className="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
          </svg>
          <h3 className="font-semibold text-blue-900">Use Diecast Credits</h3>
        </div>
        <label className="flex items-center">
          <input
            type="checkbox"
            checked={isRedemptionEnabled}
            onChange={handleToggleRedemption}
            disabled={disabled}
            className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
          />
        </label>
      </div>

      {/* Balance Info */}
      <div className="flex justify-between items-center mb-3 text-sm">
        <span className="text-blue-700">Available Credits:</span>
        <span className="font-semibold text-blue-900">{balance.available_credits.toFixed(2)}</span>
      </div>

      {isRedemptionEnabled && (
        <div className="space-y-4">
          {/* Credits Input */}
          <div>
            <label className="block text-sm font-medium text-blue-900 mb-2">
              Credits to Redeem
            </label>
            <div className="flex space-x-2">
              <input
                type="number"
                min={minimumRedemption}
                max={maxCredits}
                step="0.01"
                value={creditsToRedeem}
                onChange={(e) => handleCreditsChange(parseFloat(e.target.value) || 0)}
                disabled={disabled}
                className="flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                placeholder={`Min: ${minimumRedemption}`}
              />
              <button
                type="button"
                onClick={handleMaxCredits}
                disabled={disabled}
                className="px-3 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 disabled:opacity-50"
              >
                Max
              </button>
            </div>
            <p className="text-xs text-blue-600 mt-1">
              Maximum: {maxCredits.toFixed(2)} credits ({maximumPercentage}% of order total)
            </p>
          </div>

          {/* Credits Slider */}
          <div>
            <input
              type="range"
              min={minimumRedemption}
              max={maxCredits}
              step="1"
              value={creditsToRedeem}
              onChange={(e) => handleCreditsChange(parseFloat(e.target.value))}
              disabled={disabled}
              className="w-full h-2 bg-blue-200 rounded-lg appearance-none cursor-pointer slider"
            />
            <div className="flex justify-between text-xs text-blue-600 mt-1">
              <span>{minimumRedemption}</span>
              <span>{maxCredits.toFixed(0)}</span>
            </div>
          </div>

          {/* Redemption Summary */}
          {creditsToRedeem > 0 && (
            <div className="bg-white rounded-lg p-3 border border-blue-200">
              <h4 className="font-medium text-blue-900 mb-2">Redemption Summary</h4>
              <div className="space-y-1 text-sm">
                <div className="flex justify-between">
                  <span className="text-blue-700">Credits Used:</span>
                  <span className="font-medium">{creditsToRedeem.toFixed(2)}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-blue-700">Discount Amount:</span>
                  <span className="font-medium text-green-600">
                    -{formatCurrency(creditsToRedeem * conversionRate)}
                  </span>
                </div>
                <div className="flex justify-between">
                  <span className="text-blue-700">Remaining Credits:</span>
                  <span className="font-medium">
                    {(balance.available_credits - creditsToRedeem).toFixed(2)}
                  </span>
                </div>
                <div className="flex justify-between border-t pt-1">
                  <span className="text-blue-900 font-semibold">New Order Total:</span>
                  <span className="font-bold text-blue-900">
                    {formatCurrency(orderTotal - (creditsToRedeem * conversionRate))}
                  </span>
                </div>
              </div>
            </div>
          )}

          {/* Conversion Rate Info */}
          <div className="text-xs text-blue-600 bg-blue-100 rounded p-2">
            <div className="flex items-center space-x-1">
              <svg className="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span>1 Diecast Credit = ₱1.00 discount</span>
            </div>
          </div>

          {/* Expiring Credits Warning */}
          {balance.expiring_soon > 0 && (
            <div className="text-xs text-yellow-700 bg-yellow-100 rounded p-2">
              <div className="flex items-center space-x-1">
                <svg className="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
                <span>
                  {balance.expiring_soon.toFixed(2)} credits expire in {balance.expiring_days} days
                </span>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export default CreditsRedemption;