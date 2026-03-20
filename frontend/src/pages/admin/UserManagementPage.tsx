import React, { useState, useEffect } from 'react';
import { 
  MagnifyingGlassIcon, 
  FunnelIcon, 
  UserIcon,
  StarIcon,
  ExclamationTriangleIcon
} from '@heroicons/react/24/outline';
import { userManagementApi, type UserFilters } from '../../services/adminApi';
import { User, PaginatedResponse } from '../../types';
import UserTable from '../../components/admin/UserTable';
import UserFiltersPanel from '../../components/admin/UserFiltersPanel';
import UserDetailModal from '../../components/admin/UserDetailModal';

const UserManagementPage: React.FC = () => {
  const [users, setUsers] = useState<PaginatedResponse<User> | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showFilters, setShowFilters] = useState(false);
  const [selectedUser, setSelectedUser] = useState<User | null>(null);
  const [showUserDetail, setShowUserDetail] = useState(false);
  
  const [filters, setFilters] = useState<UserFilters>({
    per_page: 20,
    sort_by: 'created_at',
    sort_direction: 'desc'
  });

  const fetchUsers = async () => {
    try {
      setIsLoading(true);
      setError(null);
      
      const response = await userManagementApi.getUsers(filters);
      
      if (response.success) {
        setUsers(response.data);
      } else {
        setError('Failed to load users');
      }
    } catch (err) {
      setError('Failed to load users');
      console.error('Users fetch error:', err);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchUsers();
  }, [filters]);

  const handleFilterChange = (newFilters: Partial<UserFilters>) => {
    setFilters(prev => ({ ...prev, ...newFilters, page: 1 }));
  };

  const handlePageChange = (page: number) => {
    setFilters(prev => ({ ...prev, page }));
  };

  const handleUserClick = async (user: User) => {
    try {
      const response = await userManagementApi.getUser(user.id);
      if (response.success) {
        setSelectedUser(response.data.user);
        setShowUserDetail(true);
      }
    } catch (err) {
      console.error('Failed to load user details:', err);
    }
  };

  const getLoyaltyTierColor = (tier: string) => {
    const colors = {
      bronze: 'bg-orange-100 text-orange-800',
      silver: 'bg-gray-100 text-gray-800',
      gold: 'bg-yellow-100 text-yellow-800',
      platinum: 'bg-purple-100 text-purple-800'
    };
    return colors[tier as keyof typeof colors] || 'bg-gray-100 text-gray-800';
  };

  const getStatusColor = (status: string) => {
    const colors = {
      active: 'bg-green-100 text-green-800',
      inactive: 'bg-gray-100 text-gray-800',
      suspended: 'bg-red-100 text-red-800'
    };
    return colors[status as keyof typeof colors] || 'bg-gray-100 text-gray-800';
  };

  if (error) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <ExclamationTriangleIcon className="h-12 w-12 text-red-500 mx-auto mb-4" />
          <h2 className="text-xl font-semibold text-gray-900 mb-2">Error Loading Users</h2>
          <p className="text-gray-600 mb-4">{error}</p>
          <button
            onClick={fetchUsers}
            className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700"
          >
            Retry
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Header */}
        <div className="mb-8">
          <div className="flex justify-between items-center">
            <div>
              <h1 className="text-3xl font-bold text-gray-900">User Management</h1>
              <p className="text-gray-600 mt-1">
                Manage customer accounts and loyalty programs
              </p>
            </div>
            <div className="flex space-x-3">
              <button
                onClick={() => setShowFilters(!showFilters)}
                className="flex items-center space-x-2 px-4 py-2 border border-gray-300 rounded-md bg-white text-sm font-medium text-gray-700 hover:bg-gray-50"
              >
                <FunnelIcon className="h-4 w-4" />
                <span>Filters</span>
              </button>
            </div>
          </div>
        </div>

        {/* Summary Cards */}
        {users && (
          <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div className="bg-white rounded-lg shadow p-6">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <UserIcon className="h-8 w-8 text-blue-600" />
                </div>
                <div className="ml-4">
                  <p className="text-sm font-medium text-gray-500">Total Users</p>
                  <p className="text-2xl font-semibold text-gray-900">
                    {users.meta.total.toLocaleString()}
                  </p>
                </div>
              </div>
            </div>

            <div className="bg-white rounded-lg shadow p-6">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <StarIcon className="h-8 w-8 text-yellow-600" />
                </div>
                <div className="ml-4">
                  <p className="text-sm font-medium text-gray-500">Active Users</p>
                  <p className="text-2xl font-semibold text-gray-900">
                    {users.data.filter(u => u.status === 'active').length}
                  </p>
                </div>
              </div>
            </div>

            <div className="bg-white rounded-lg shadow p-6">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <div className="h-8 w-8 bg-purple-100 rounded-full flex items-center justify-center">
                    <span className="text-purple-600 font-semibold text-sm">P</span>
                  </div>
                </div>
                <div className="ml-4">
                  <p className="text-sm font-medium text-gray-500">Platinum Members</p>
                  <p className="text-2xl font-semibold text-gray-900">
                    {users.data.filter(u => u.loyaltyTier === 'platinum').length}
                  </p>
                </div>
              </div>
            </div>

            <div className="bg-white rounded-lg shadow p-6">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <div className="h-8 w-8 bg-yellow-100 rounded-full flex items-center justify-center">
                    <span className="text-yellow-600 font-semibold text-sm">G</span>
                  </div>
                </div>
                <div className="ml-4">
                  <p className="text-sm font-medium text-gray-500">Gold Members</p>
                  <p className="text-2xl font-semibold text-gray-900">
                    {users.data.filter(u => u.loyaltyTier === 'gold').length}
                  </p>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Filters Panel */}
        {showFilters && (
          <div className="mb-6">
            <UserFiltersPanel
              filters={filters}
              onChange={handleFilterChange}
              onClose={() => setShowFilters(false)}
            />
          </div>
        )}

        {/* Users Table */}
        <div className="bg-white rounded-lg shadow">
          <UserTable
            users={users}
            isLoading={isLoading}
            onUserClick={handleUserClick}
            onPageChange={handlePageChange}
            getLoyaltyTierColor={getLoyaltyTierColor}
            getStatusColor={getStatusColor}
          />
        </div>

        {/* User Detail Modal */}
        {showUserDetail && selectedUser && (
          <UserDetailModal
            user={selectedUser}
            isOpen={showUserDetail}
            onClose={() => {
              setShowUserDetail(false);
              setSelectedUser(null);
            }}
            onUserUpdate={fetchUsers}
          />
        )}
      </div>
    </div>
  );
};

export default UserManagementPage;