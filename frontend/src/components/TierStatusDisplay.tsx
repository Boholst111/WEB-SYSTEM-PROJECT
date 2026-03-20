import React, { useState, useEffect } from 'react';
import { loyaltyApi, TierStatus } from '../services/loyaltyApi';
import { useAppSelector } from '../store';

interface TierStatusDisplayProps {
  className?: string;
  compact?: boolean;
}

const TierStatusDisplay: React.FC<TierStatusDisplayProps> = ({ 
  className = '', 
  compact = false 
}) => {
  const [tierStatus, setTierStatus] = useState<TierStatus | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const { isAuthenticated } = useAppSelector(state => state.auth);

  useEffect(() => {
    if (isAuthenticated) {
      loadTierStatus();
    }
  }, [isAuthenticated]);

  const loadTierStatus = async () => {
    try {
      setLoading(true);
      setError(null);

      const response = await loyaltyApi.getTierStatus();
      if (response.success) {
        setTierStatus(response.data);
      }
    } catch (err) {
      setError('Failed to load tier status');
      console.error('Error loading tier status:', err);
    } finally {
      setLoading(false);
    }
  };

  const getTierColor = (tier: string) => {
    switch (tier) {
      case 'bronze':
        return 'from-amber-600 to-amber-700';
      case 'silver':
        return 'from-gray-400 to-gray-500';
      case 'gold':
        return 'from-yellow-400 to-yellow-500';
      case 'platinum':
        return 'from-purple-500 to-purple-600';
      default:
        return 'from-gray-400 to-gray-500';
    }
  };

  const getTierIcon = (tier: string) => {
    switch (tier) {
      case 'bronze':
        return '🥉';
      case 'silver':
        return '🥈';
      case 'gold':
        return '🥇';
      case 'platinum':
        return '💎';
      default:
        return '🏆';
    }
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(amount);
  };

  if (!isAuthenticated) {
    return null;
  }

  if (loading) {
    return (
      <div className={`bg-white rounded-lg shadow-md p-4 ${className}`}>
        <div className="animate-pulse">
          <div className="h-4 bg-gray-200 rounded w-1/3 mb-2"></div>
          <div className="h-6 bg-gray-200 rounded w-1/2 mb-4"></div>
          <div className="h-2 bg-gray-200 rounded"></div>
        </div>
      </div>
    );
  }

  if (error || !tierStatus) {
    return (
      <div className={`bg-white rounded-lg shadow-md p-4 ${className}`}>
        <p className="text-red-600 text-sm">{error || 'Unable to load tier status'}</p>
      </div>
    );
  }

  if (compact) {
    return (
      <div className={`bg-gradient-to-r ${getTierColor(tierStatus.current_tier)} rounded-lg p-4 text-white ${className}`}>
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-2">
            <span className="text-2xl">{getTierIcon(tierStatus.current_tier)}</span>
            <div>
              <h3 className="font-semibold capitalize">{tierStatus.current_tier} Tier</h3>
              <p className="text-sm opacity-90">
                {tierStatus.next_tier ? (
                  `${tierStatus.progress_percentage.toFixed(0)}% to ${tierStatus.next_tier}`
                ) : (
                  'Highest tier achieved!'
                )}
              </p>
            </div>
          </div>
          {tierStatus.next_tier && (
            <div className="text-right">
              <p className="text-sm opacity-90">
                {formatCurrency(tierStatus.spending_to_next_tier || 0)} to go
              </p>
            </div>
          )}
        </div>
        
        {tierStatus.next_tier && (
          <div className="mt-3">
            <div className="bg-white bg-opacity-20 rounded-full h-2">
              <div
                className="bg-white rounded-full h-2 transition-all duration-300"
                style={{ width: `${Math.min(tierStatus.progress_percentage, 100)}%` }}
              ></div>
            </div>
          </div>
        )}
      </div>
    );
  }

  return (
    <div className={`bg-white rounded-lg shadow-md ${className}`}>
      {/* Header */}
      <div className={`bg-gradient-to-r ${getTierColor(tierStatus.current_tier)} rounded-t-lg p-6 text-white`}>
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-3">
            <span className="text-4xl">{getTierIcon(tierStatus.current_tier)}</span>
            <div>
              <h2 className="text-2xl font-bold capitalize">{tierStatus.current_tier} Tier</h2>
              <p className="opacity-90">Your current loyalty status</p>
            </div>
          </div>
          <div className="text-right">
            <p className="text-sm opacity-90">Total Spent</p>
            <p className="text-xl font-semibold">{formatCurrency(tierStatus.total_spent)}</p>
          </div>
        </div>
      </div>

      <div className="p-6">
        {/* Progress to Next Tier */}
        {tierStatus.next_tier ? (
          <div className="mb-6">
            <div className="flex justify-between items-center mb-2">
              <h3 className="font-semibold text-gray-900">
                Progress to {tierStatus.next_tier} Tier
              </h3>
              <span className="text-sm text-gray-600">
                {tierStatus.progress_percentage.toFixed(1)}%
              </span>
            </div>
            
            <div className="bg-gray-200 rounded-full h-3 mb-2">
              <div
                className={`bg-gradient-to-r ${getTierColor(tierStatus.next_tier)} rounded-full h-3 transition-all duration-500`}
                style={{ width: `${Math.min(tierStatus.progress_percentage, 100)}%` }}
              ></div>
            </div>
            
            <div className="flex justify-between text-sm text-gray-600">
              <span>{formatCurrency(tierStatus.total_spent)}</span>
              <span>{formatCurrency(tierStatus.tier_thresholds[tierStatus.next_tier] || 0)}</span>
            </div>
            
            {tierStatus.spending_to_next_tier && tierStatus.spending_to_next_tier > 0 && (
              <p className="text-center text-sm text-gray-600 mt-2">
                Spend {formatCurrency(tierStatus.spending_to_next_tier)} more to reach {tierStatus.next_tier} tier
              </p>
            )}
          </div>
        ) : (
          <div className="mb-6 text-center">
            <div className="bg-purple-50 rounded-lg p-4">
              <h3 className="font-semibold text-purple-900 mb-2">🎉 Congratulations!</h3>
              <p className="text-purple-700">
                You've reached the highest tier available. Enjoy all the premium benefits!
              </p>
            </div>
          </div>
        )}

        {/* Current Tier Benefits */}
        <div className="mb-6">
          <h3 className="font-semibold text-gray-900 mb-3">Your Current Benefits</h3>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="bg-blue-50 rounded-lg p-4">
              <div className="flex items-center space-x-2 mb-2">
                <span className="text-blue-600">💰</span>
                <h4 className="font-medium text-blue-900">Credits Multiplier</h4>
              </div>
              <p className="text-blue-700">
                Earn {tierStatus.current_tier_benefits.credits_multiplier}x credits on every purchase
              </p>
            </div>

            <div className="bg-green-50 rounded-lg p-4">
              <div className="flex items-center space-x-2 mb-2">
                <span className="text-green-600">🎁</span>
                <h4 className="font-medium text-green-900">Bonus Rate</h4>
              </div>
              <p className="text-green-700">
                Extra {(tierStatus.current_tier_benefits.bonus_rate * 100).toFixed(1)}% bonus credits
              </p>
            </div>

            <div className="bg-purple-50 rounded-lg p-4">
              <div className="flex items-center space-x-2 mb-2">
                <span className="text-purple-600">🚚</span>
                <h4 className="font-medium text-purple-900">Free Shipping</h4>
              </div>
              <p className="text-purple-700">
                Free shipping on orders over {formatCurrency(tierStatus.current_tier_benefits.free_shipping_threshold)}
              </p>
            </div>

            <div className="bg-yellow-50 rounded-lg p-4">
              <div className="flex items-center space-x-2 mb-2">
                <span className="text-yellow-600">⭐</span>
                <h4 className="font-medium text-yellow-900">Special Access</h4>
              </div>
              <p className="text-yellow-700">
                {tierStatus.current_tier_benefits.early_access ? 'Early access to new releases' : 'Standard access'}
                {tierStatus.current_tier_benefits.priority_support && ' • Priority customer support'}
              </p>
            </div>
          </div>
        </div>

        {/* Next Tier Benefits Preview */}
        {tierStatus.next_tier && tierStatus.next_tier_benefits && (
          <div>
            <h3 className="font-semibold text-gray-900 mb-3">
              Unlock at {tierStatus.next_tier} Tier
            </h3>
            <div className="bg-gray-50 rounded-lg p-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div className="flex items-center space-x-2">
                  <span>💰</span>
                  <span>{tierStatus.next_tier_benefits.credits_multiplier}x Credits Multiplier</span>
                </div>
                <div className="flex items-center space-x-2">
                  <span>🎁</span>
                  <span>{(tierStatus.next_tier_benefits.bonus_rate * 100).toFixed(1)}% Bonus Rate</span>
                </div>
                <div className="flex items-center space-x-2">
                  <span>🚚</span>
                  <span>Free shipping over {formatCurrency(tierStatus.next_tier_benefits.free_shipping_threshold)}</span>
                </div>
                <div className="flex items-center space-x-2">
                  <span>⭐</span>
                  <span>
                    {tierStatus.next_tier_benefits.early_access ? 'Early access' : 'Standard access'}
                    {tierStatus.next_tier_benefits.priority_support && ' + Priority support'}
                  </span>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Tier History */}
        {tierStatus.tier_history.length > 1 && (
          <div className="mt-6 pt-6 border-t border-gray-200">
            <h3 className="font-semibold text-gray-900 mb-3">Tier History</h3>
            <div className="space-y-2">
              {tierStatus.tier_history.map((history, index) => (
                <div key={index} className="flex items-center justify-between text-sm">
                  <div className="flex items-center space-x-2">
                    <span>{getTierIcon(history.tier)}</span>
                    <span className="capitalize font-medium">{history.tier}</span>
                  </div>
                  <div className="text-gray-600">
                    {new Date(history.achieved_at).toLocaleDateString()}
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default TierStatusDisplay;