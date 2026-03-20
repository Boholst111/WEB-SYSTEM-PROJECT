import React, { useState } from 'react';
import LoyaltyDashboard from '../components/LoyaltyDashboard';
import TierStatusDisplay from '../components/TierStatusDisplay';
import EarningTracker from '../components/EarningTracker';
import { useAppSelector } from '../store';

const LoyaltyPage: React.FC = () => {
  const [activeTab, setActiveTab] = useState<'dashboard' | 'tier' | 'rewards'>('dashboard');
  const { isAuthenticated, user } = useAppSelector(state => state.auth);

  if (!isAuthenticated) {
    return (
      <div className="min-h-screen bg-gray-50 py-8">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center">
            <div className="bg-white rounded-lg shadow-md p-8">
              <h1 className="text-3xl font-bold text-gray-900 mb-4">
                Diecast Credits Loyalty Program
              </h1>
              <p className="text-lg text-gray-600 mb-8">
                Earn credits on every purchase and unlock exclusive rewards.
              </p>
              <a
                href="/login"
                className="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition-colors"
              >
                Sign In to View Your Rewards
              </a>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900">Loyalty Rewards</h1>
          <p className="text-gray-600 mt-2">
            Track your Diecast Credits, tier progress, and exclusive benefits
          </p>
        </div>

        <div className="border-b border-gray-200 mb-8">
          <nav className="flex space-x-8">
            <button
              onClick={() => setActiveTab('dashboard')}
              className={`py-4 px-1 border-b-2 font-medium text-sm ${
                activeTab === 'dashboard'
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
            >
              Dashboard
            </button>
            <button
              onClick={() => setActiveTab('tier')}
              className={`py-4 px-1 border-b-2 font-medium text-sm ${
                activeTab === 'tier'
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
            >
              Tier Status
            </button>
            <button
              onClick={() => setActiveTab('rewards')}
              className={`py-4 px-1 border-b-2 font-medium text-sm ${
                activeTab === 'rewards'
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
            >
              Rewards Guide
            </button>
          </nav>
        </div>

        <div className="space-y-8">
          {activeTab === 'dashboard' && (
            <div className="space-y-8">
              <TierStatusDisplay compact className="mb-6" />
              <LoyaltyDashboard />
            </div>
          )}

          {activeTab === 'tier' && (
            <div className="space-y-8">
              <TierStatusDisplay />
            </div>
          )}

          {activeTab === 'rewards' && (
            <div className="space-y-8">
              <EarningTracker showExpiring />
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default LoyaltyPage;