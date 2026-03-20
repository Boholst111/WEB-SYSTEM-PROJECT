import React, { useState, useEffect } from 'react';
import { loyaltyApi, EarningsCalculation, ExpiringCredits } from '../services/loyaltyApi';
import { useAppSelector } from '../store';

interface EarningTrackerProps {
  className?: string;
  purchaseAmount?: number;
  showExpiring?: boolean;
}

const EarningTracker: React.FC<EarningTrackerProps> = ({
  className = '',
  purchaseAmount = 0,
  showExpiring = true
}) => {
  const [earnings, setEarnings] = useState<EarningsCalculation | null>(null);
  const [expiringCredits, setExpiringCredits] = useState<ExpiringCredits | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const { isAuthenticated, user } = useAppSelector(state => state.auth);

  useEffect(() => {
    if (isAuthenticated && purchaseAmount > 0) {
      calculateEarnings();
    }
  }, [isAuthenticated, purchaseAmount]);

  useEffect(() => {
    if (isAuthenticated && showExpiring) {
      loadExpiringCredits();
    }
  }, [isAuthenticated, showExpiring]);

  const calculateEarnings = async () => {
    try {
      setLoading(true);
      setError(null);

      const response = await loyaltyApi.calculateEarnings(purchaseAmount);
      if (response.success) {
        setEarnings(response.data);
      }
    } catch (err) {
      setError('Failed to calculate earnings');
      console.error('Error calculating earnings:', err);
    } finally {
      setLoading(false);
    }
  };

  const loadExpiringCredits = async () => {
    try {
      const response = await loyaltyApi.getExpiringCredits();
      if (response.success) {
        setExpiringCredits(response.data);
      }
    } catch (err) {
      console.error('Error loading expiring credits:', err);
    }
  };

  const getTierColor = (tier: string) => {
    switch (tier) {
      case 'bronze':
        return 'text-amber-600';
      case 'silver':
        return 'text-gray-500';
      case 'gold':
        return 'text-yellow-500';
      case 'platinum':
        return 'text-purple-600';
      default:
        return 'text-gray-500';
    }
  };

  const getTierBadgeColor = (tier: string) => {
    switch (tier) {
      case 'bronze':
        return 'bg-amber-100 text-amber-800';
      case 'silver':
        return 'bg-gray-100 text-gray-800';
      case 'gold':
        return 'bg-yellow-100 text-yellow-800';
      case 'platinum':
        return 'bg-purple-100 text-purple-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2,
    }).format(amount);
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    });
  };

  if (!isAuthenticated) {
    return (
      <div className={`bg-gray-50 rounded-lg p-4 ${className}`}>
        <p className="text-gray-600 text-center">Log in to track your earnings and rewards</p>
      </div>
    );
  }

  return (
    <div className={`space-y-6 ${className}`}>
      {/* Earnings Calculator */}
      {purchaseAmount > 0 && (
        <div className="bg-white rounded-lg shadow-md p-6">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">
            Credits You'll Earn
          </h3>

          {loading ? (
            <div className="animate-pulse space-y-3">
              <div className="h-4 bg-gray-200 rounded w-3/4"></div>
              <div className="h-4 bg-gray-200 rounded w-1/2"></div>
              <div className="h-4 bg-gray-200 rounded w-2/3"></div>
            </div>
          ) : error ? (
            <p className="text-red-600">{error}</p>
          ) : earnings ? (
            <div className="space-y-4">
              {/* Total Earnings Highlight */}
              <div className="bg-gradient-to-r from-green-500 to-green-600 rounded-lg p-4 text-white">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-green-100 text-sm">Total Credits Earned</p>
                    <p className="text-2xl font-bold">{earnings.total_credits.toFixed(2)}</p>
                  </div>
                  <div className="text-right">
                    <p className="text-green-100 text-sm">From Purchase</p>
                    <p className="text-lg font-semibold">{formatCurrency(earnings.purchase_amount)}</p>
                  </div>
                </div>
              </div>

              {/* Earnings Breakdown */}
              <div className="bg-gray-50 rounded-lg p-4">
                <h4 className="font-medium text-gray-900 mb-3">Earnings Breakdown</h4>
                <div className="space-y-2 text-sm">
                  <div className="flex justify-between items-center">
                    <div className="flex items-center space-x-2">
                      <span className="w-3 h-3 bg-blue-500 rounded-full"></span>
                      <span className="text-gray-700">Base Credits ({(earnings.base_rate * 100).toFixed(1)}%)</span>
                    </div>
                    <span className="font-medium">{earnings.base_credits.toFixed(2)}</span>
                  </div>

                  {earnings.tier_multiplier > 1 && (
                    <div className="flex justify-between items-center">
                      <div className="flex items-center space-x-2">
                        <span className="w-3 h-3 bg-purple-500 rounded-full"></span>
                        <span className="text-gray-700">
                          Tier Multiplier ({earnings.tier_multiplier}x)
                        </span>
                      </div>
                      <span className="font-medium text-purple-600">
                        +{(earnings.tier_credits - earnings.base_credits).toFixed(2)}
                      </span>
                    </div>
                  )}

                  {earnings.bonus_credits > 0 && (
                    <div className="flex justify-between items-center">
                      <div className="flex items-center space-x-2">
                        <span className="w-3 h-3 bg-yellow-500 rounded-full"></span>
                        <span className="text-gray-700">
                          Tier Bonus ({(earnings.bonus_rate * 100).toFixed(1)}%)
                        </span>
                      </div>
                      <span className="font-medium text-yellow-600">
                        +{earnings.bonus_credits.toFixed(2)}
                      </span>
                    </div>
                  )}

                  <div className="border-t pt-2 flex justify-between items-center font-semibold">
                    <span className="text-gray-900">Total Credits</span>
                    <span className="text-green-600">{earnings.total_credits.toFixed(2)}</span>
                  </div>
                </div>
              </div>

              {/* Current Tier Badge */}
              <div className="flex items-center justify-center">
                <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${getTierBadgeColor(earnings.current_tier)}`}>
                  <span className="mr-1">
                    {earnings.current_tier === 'bronze' && '🥉'}
                    {earnings.current_tier === 'silver' && '🥈'}
                    {earnings.current_tier === 'gold' && '🥇'}
                    {earnings.current_tier === 'platinum' && '💎'}
                  </span>
                  {earnings.current_tier.charAt(0).toUpperCase() + earnings.current_tier.slice(1)} Tier
                </span>
              </div>
            </div>
          ) : null}
        </div>
      )}

      {/* Expiring Credits Alert */}
      {showExpiring && expiringCredits && expiringCredits.total_expiring > 0 && (
        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
          <div className="flex items-start space-x-3">
            <div className="flex-shrink-0">
              <svg className="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
              </svg>
            </div>
            <div className="flex-1">
              <h3 className="text-sm font-medium text-yellow-800 mb-2">
                Credits Expiring Soon
              </h3>
              <p className="text-sm text-yellow-700 mb-3">
                {expiringCredits.total_expiring.toFixed(2)} credits will expire in the next {expiringCredits.warning_days} days.
              </p>

              {/* Expiring Credits Timeline */}
              <div className="space-y-2">
                {expiringCredits.expiring_by_date.slice(0, 3).map((expiry, index) => (
                  <div key={index} className="flex justify-between items-center text-sm">
                    <span className="text-yellow-700">
                      {formatDate(expiry.date)} ({expiry.days_until_expiration} days)
                    </span>
                    <span className="font-medium text-yellow-800">
                      {expiry.amount.toFixed(2)} credits
                    </span>
                  </div>
                ))}
                {expiringCredits.expiring_by_date.length > 3 && (
                  <p className="text-xs text-yellow-600">
                    +{expiringCredits.expiring_by_date.length - 3} more expiration dates
                  </p>
                )}
              </div>

              <div className="mt-3">
                <button className="text-sm text-yellow-800 hover:text-yellow-900 font-medium underline">
                  Use credits now →
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Rewards Visualization */}
      <div className="bg-white rounded-lg shadow-md p-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">
          Rewards & Benefits
        </h3>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {/* Credits Value */}
          <div className="bg-blue-50 rounded-lg p-4">
            <div className="flex items-center space-x-2 mb-2">
              <svg className="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
              </svg>
              <h4 className="font-medium text-blue-900">Credits Value</h4>
            </div>
            <p className="text-blue-700 text-sm">
              Every credit you earn is worth ₱1.00 in discounts on future purchases.
            </p>
          </div>

          {/* Tier Benefits */}
          <div className="bg-purple-50 rounded-lg p-4">
            <div className="flex items-center space-x-2 mb-2">
              <svg className="h-5 w-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
              </svg>
              <h4 className="font-medium text-purple-900">Tier Rewards</h4>
            </div>
            <p className="text-purple-700 text-sm">
              Higher tiers unlock better multipliers, bonus rates, and exclusive perks.
            </p>
          </div>

          {/* Free Shipping */}
          <div className="bg-green-50 rounded-lg p-4">
            <div className="flex items-center space-x-2 mb-2">
              <svg className="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
              </svg>
              <h4 className="font-medium text-green-900">Free Shipping</h4>
            </div>
            <p className="text-green-700 text-sm">
              Enjoy free shipping on qualifying orders based on your tier level.
            </p>
          </div>

          {/* Early Access */}
          <div className="bg-yellow-50 rounded-lg p-4">
            <div className="flex items-center space-x-2 mb-2">
              <svg className="h-5 w-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
              <h4 className="font-medium text-yellow-900">Early Access</h4>
            </div>
            <p className="text-yellow-700 text-sm">
              Get first access to new releases and limited edition models.
            </p>
          </div>
        </div>

        {/* Call to Action */}
        <div className="mt-6 text-center">
          <div className="bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg p-4 text-white">
            <h4 className="font-semibold mb-2">Start Earning More Rewards!</h4>
            <p className="text-sm opacity-90 mb-3">
              Every purchase brings you closer to the next tier and better benefits.
            </p>
            <button className="bg-white text-blue-600 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-100 transition-colors">
              Shop Now
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default EarningTracker;