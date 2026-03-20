import React from 'react';
import { GiftIcon, TrophyIcon, ExclamationTriangleIcon } from '@heroicons/react/24/outline';
import type { LoyaltyMetrics } from '../../services/adminApi';

interface LoyaltyMetricsCardProps {
  data: LoyaltyMetrics;
}

const LoyaltyMetricsCard: React.FC<LoyaltyMetricsCardProps> = ({ data }) => {
  const formatCurrency = (value: number) => `₱${value.toLocaleString()}`;

  return (
    <div className="bg-white rounded-lg shadow">
      <div className="px-6 py-4 border-b border-gray-200">
        <h2 className="text-lg font-semibold text-gray-900 flex items-center">
          <GiftIcon className="h-5 w-5 mr-2 text-purple-600" />
          Loyalty Metrics
        </h2>
      </div>
      <div className="p-6 space-y-6">
        {/* Summary Stats */}
        <div className="grid grid-cols-2 gap-4">
          <div className="bg-green-50 rounded-lg p-4">
            <div className="flex items-center">
              <TrophyIcon className="h-8 w-8 text-green-600 mr-3" />
              <div>
                <p className="text-sm font-medium text-green-600">Credits Earned</p>
                <p className="text-xl font-bold text-green-900">
                  {formatCurrency(data.summary.credits_earned)}
                </p>
              </div>
            </div>
          </div>

          <div className="bg-blue-50 rounded-lg p-4">
            <div className="flex items-center">
              <GiftIcon className="h-8 w-8 text-blue-600 mr-3" />
              <div>
                <p className="text-sm font-medium text-blue-600">Credits Redeemed</p>
                <p className="text-xl font-bold text-blue-900">
                  {formatCurrency(data.summary.credits_redeemed)}
                </p>
              </div>
            </div>
          </div>

          <div className="bg-purple-50 rounded-lg p-4">
            <div>
              <p className="text-sm font-medium text-purple-600">Active Members</p>
              <p className="text-xl font-bold text-purple-900">
                {data.summary.active_members.toLocaleString()}
              </p>
            </div>
          </div>

          <div className="bg-orange-50 rounded-lg p-4">
            <div>
              <p className="text-sm font-medium text-orange-600">Utilization Rate</p>
              <p className="text-xl font-bold text-orange-900">
                {data.summary.utilization_rate.toFixed(1)}%
              </p>
            </div>
          </div>
        </div>

        {/* Tier Progressions and Expiring Credits */}
        <div className="grid grid-cols-2 gap-4">
          <div className="bg-indigo-50 rounded-lg p-4">
            <div>
              <p className="text-sm font-medium text-indigo-600">Tier Progressions</p>
              <p className="text-xl font-bold text-indigo-900">
                {data.summary.tier_progressions}
              </p>
            </div>
          </div>

          <div className="bg-red-50 rounded-lg p-4">
            <div className="flex items-center">
              <ExclamationTriangleIcon className="h-6 w-6 text-red-600 mr-2" />
              <div>
                <p className="text-sm font-medium text-red-600">Expiring Credits</p>
                <p className="text-xl font-bold text-red-900">
                  {formatCurrency(data.summary.expiring_credits)}
                </p>
              </div>
            </div>
          </div>
        </div>

        {/* Top Earners */}
        <div>
          <h3 className="text-md font-medium text-gray-900 mb-4">Top Credit Earners</h3>
          <div className="space-y-3">
            {data.top_earners.slice(0, 5).map((earner, index) => (
              <div key={earner.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <div className="flex items-center">
                  <div className="flex-shrink-0 w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                    <span className="text-sm font-medium text-purple-600">
                      {index + 1}
                    </span>
                  </div>
                  <div className="ml-3">
                    <p className="text-sm font-medium text-gray-900">
                      {earner.first_name} {earner.last_name}
                    </p>
                    <p className="text-xs text-gray-500">{earner.email}</p>
                  </div>
                </div>
                <div className="text-right">
                  <p className="text-sm font-medium text-gray-900">
                    {formatCurrency(earner.loyalty_transactions_sum_amount || 0)}
                  </p>
                  <p className="text-xs text-gray-500">earned</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};

export default LoyaltyMetricsCard;