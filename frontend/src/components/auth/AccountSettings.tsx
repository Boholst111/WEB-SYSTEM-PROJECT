import React, { useState } from 'react';
import { useAppDispatch, useAppSelector } from '../../store';
import { logout } from '../../store/slices/authSlice';
import { authService } from '../../services/authApi';
import ProfileForm from './ProfileForm';
import PasswordChangeForm from './PasswordChangeForm';

type TabType = 'profile' | 'security' | 'preferences';

const AccountSettings: React.FC = () => {
  const dispatch = useAppDispatch();
  const { user } = useAppSelector((state) => state.auth);
  const [activeTab, setActiveTab] = useState<TabType>('profile');
  const [isLoggingOut, setIsLoggingOut] = useState(false);

  const handleLogout = async () => {
    setIsLoggingOut(true);
    
    try {
      await authService.logout();
    } catch (error) {
      // Even if logout fails on server, clear local state
      console.error('Logout error:', error);
    } finally {
      dispatch(logout());
      localStorage.removeItem('auth_token');
      window.location.href = '/';
    }
  };

  const handleLogoutAll = async () => {
    if (!window.confirm('This will log you out from all devices. Continue?')) {
      return;
    }

    setIsLoggingOut(true);
    
    try {
      await authService.logoutAll();
    } catch (error) {
      console.error('Logout all error:', error);
    } finally {
      dispatch(logout());
      localStorage.removeItem('auth_token');
      window.location.href = '/';
    }
  };

  if (!user) {
    return (
      <div className="text-center py-8">
        <p className="text-gray-500">Please log in to access account settings.</p>
      </div>
    );
  }

  const tabs = [
    { id: 'profile' as TabType, name: 'Profile', icon: '👤' },
    { id: 'security' as TabType, name: 'Security', icon: '🔒' },
    { id: 'preferences' as TabType, name: 'Preferences', icon: '⚙️' },
  ];

  return (
    <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-900">Account Settings</h1>
        <p className="mt-2 text-gray-600">
          Manage your account information, security settings, and preferences.
        </p>
      </div>

      <div className="bg-white shadow rounded-lg">
        {/* Tab Navigation */}
        <div className="border-b border-gray-200">
          <nav className="-mb-px flex space-x-8 px-6">
            {tabs.map((tab) => (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`py-4 px-1 border-b-2 font-medium text-sm ${
                  activeTab === tab.id
                    ? 'border-primary-500 text-primary-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                }`}
              >
                <span className="mr-2">{tab.icon}</span>
                {tab.name}
              </button>
            ))}
          </nav>
        </div>

        {/* Tab Content */}
        <div className="p-6">
          {activeTab === 'profile' && (
            <div>
              <div className="mb-6">
                <h2 className="text-lg font-medium text-gray-900">Profile Information</h2>
                <p className="mt-1 text-sm text-gray-500">
                  Update your personal information and contact details.
                </p>
              </div>
              <ProfileForm />
            </div>
          )}

          {activeTab === 'security' && (
            <div className="space-y-8">
              <div>
                <h2 className="text-lg font-medium text-gray-900">Security Settings</h2>
                <p className="mt-1 text-sm text-gray-500">
                  Manage your password and account security.
                </p>
              </div>

              {/* Email Verification Status */}
              <div className="bg-gray-50 rounded-lg p-4">
                <div className="flex items-center justify-between">
                  <div>
                    <h3 className="text-sm font-medium text-gray-900">Email Verification</h3>
                    <p className="text-sm text-gray-500">
                      {user.emailVerifiedAt 
                        ? `Verified on ${new Date(user.emailVerifiedAt).toLocaleDateString()}`
                        : 'Your email address is not verified'
                      }
                    </p>
                  </div>
                  <div className="flex items-center">
                    {user.emailVerifiedAt ? (
                      <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        ✓ Verified
                      </span>
                    ) : (
                      <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                        ⚠ Unverified
                      </span>
                    )}
                  </div>
                </div>
              </div>

              {/* Password Change */}
              <PasswordChangeForm />

              {/* Session Management */}
              <div className="bg-gray-50 rounded-lg p-4">
                <h3 className="text-sm font-medium text-gray-900 mb-4">Session Management</h3>
                <div className="space-y-3">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-sm text-gray-900">Current Session</p>
                      <p className="text-xs text-gray-500">This device</p>
                    </div>
                    <button
                      onClick={handleLogout}
                      disabled={isLoggingOut}
                      className="text-sm text-red-600 hover:text-red-500 disabled:opacity-50"
                    >
                      {isLoggingOut ? 'Logging out...' : 'Sign out'}
                    </button>
                  </div>
                  <div className="border-t border-gray-200 pt-3">
                    <button
                      onClick={handleLogoutAll}
                      disabled={isLoggingOut}
                      className="text-sm text-red-600 hover:text-red-500 disabled:opacity-50"
                    >
                      {isLoggingOut ? 'Logging out...' : 'Sign out from all devices'}
                    </button>
                    <p className="text-xs text-gray-500 mt-1">
                      This will sign you out from all devices and browsers
                    </p>
                  </div>
                </div>
              </div>
            </div>
          )}

          {activeTab === 'preferences' && (
            <div>
              <div className="mb-6">
                <h2 className="text-lg font-medium text-gray-900">Preferences</h2>
                <p className="mt-1 text-sm text-gray-500">
                  Customize your experience and notification settings.
                </p>
              </div>

              {/* Loyalty Information */}
              <div className="bg-gradient-to-r from-primary-50 to-primary-100 rounded-lg p-6 mb-6">
                <div className="flex items-center justify-between">
                  <div>
                    <h3 className="text-lg font-medium text-primary-900">Loyalty Status</h3>
                    <p className="text-primary-700">
                      Current Tier: <span className="font-semibold capitalize">{user.loyaltyTier || 'bronze'}</span>
                    </p>
                    <p className="text-primary-700">
                      Available Credits: <span className="font-semibold">{(user.loyaltyCredits || 0).toFixed(2)}</span>
                    </p>
                    <p className="text-sm text-primary-600 mt-1">
                      Total Spent: ₱{(user.totalSpent || 0).toFixed(2)}
                    </p>
                  </div>
                  <div className="text-4xl">
                    {user.loyaltyTier === 'bronze' && '🥉'}
                    {user.loyaltyTier === 'silver' && '🥈'}
                    {user.loyaltyTier === 'gold' && '🥇'}
                    {user.loyaltyTier === 'platinum' && '💎'}
                    {!user.loyaltyTier && '🥉'}
                  </div>
                </div>
              </div>

              {/* Notification Preferences */}
              <div className="space-y-4">
                <h3 className="text-sm font-medium text-gray-900">Notification Preferences</h3>
                <div className="space-y-3">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-sm text-gray-900">Email Notifications</p>
                      <p className="text-xs text-gray-500">Receive updates about orders and promotions</p>
                    </div>
                    <input
                      type="checkbox"
                      defaultChecked
                      className="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                    />
                  </div>
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-sm text-gray-900">Pre-order Notifications</p>
                      <p className="text-xs text-gray-500">Get notified when pre-ordered items arrive</p>
                    </div>
                    <input
                      type="checkbox"
                      defaultChecked
                      className="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                    />
                  </div>
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-sm text-gray-900">Marketing Communications</p>
                      <p className="text-xs text-gray-500">Receive promotional offers and new product announcements</p>
                    </div>
                    <input
                      type="checkbox"
                      className="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                    />
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default AccountSettings;