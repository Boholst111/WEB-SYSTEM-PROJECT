import React from 'react';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import UserManagementPage from '../../../pages/admin/UserManagementPage';
import { userManagementApi } from '../../../services/adminApi';

// Mock the user management API
jest.mock('../../../services/adminApi', () => ({
  userManagementApi: {
    getUsers: jest.fn(),
    getUser: jest.fn(),
    updateUser: jest.fn(),
    getUserOrders: jest.fn(),
    getUserLoyaltyTransactions: jest.fn(),
  },
}));

const mockUsers = {
  data: [
    {
      id: 1,
      email: 'john.doe@example.com',
      firstName: 'John',
      lastName: 'Doe',
      phone: '+639123456789',
      dateOfBirth: '1990-01-15',
      loyaltyTier: 'gold',
      loyaltyCredits: 2500,
      totalSpent: 15000,
      emailVerifiedAt: '2024-01-01T10:00:00Z',
      phoneVerifiedAt: '2024-01-01T10:30:00Z',
      status: 'active',
      preferences: {},
      createdAt: '2023-06-15T08:00:00Z',
      updatedAt: '2024-01-15T12:00:00Z',
    },
    {
      id: 2,
      email: 'jane.smith@example.com',
      firstName: 'Jane',
      lastName: 'Smith',
      phone: '+639987654321',
      dateOfBirth: '1985-03-22',
      loyaltyTier: 'platinum',
      loyaltyCredits: 5000,
      totalSpent: 35000,
      emailVerifiedAt: '2023-12-01T09:00:00Z',
      phoneVerifiedAt: null,
      status: 'active',
      preferences: {},
      createdAt: '2023-05-10T14:30:00Z',
      updatedAt: '2024-01-14T16:45:00Z',
    },
    {
      id: 3,
      email: 'inactive.user@example.com',
      firstName: 'Inactive',
      lastName: 'User',
      phone: null,
      dateOfBirth: null,
      loyaltyTier: 'bronze',
      loyaltyCredits: 100,
      totalSpent: 500,
      emailVerifiedAt: '2023-08-01T11:00:00Z',
      phoneVerifiedAt: null,
      status: 'inactive',
      preferences: {},
      createdAt: '2023-08-01T10:00:00Z',
      updatedAt: '2023-08-01T10:00:00Z',
    },
  ],
  meta: {
    currentPage: 1,
    lastPage: 1,
    perPage: 20,
    total: 3,
    from: 1,
    to: 3,
  },
  links: {
    first: '/api/admin/users?page=1',
    last: '/api/admin/users?page=1',
    prev: null,
    next: null,
  },
};

const mockUserDetail = {
  user: {
    ...mockUsers.data[0],
    orders: [
      {
        id: 1,
        orderNumber: 'ORD-001',
        totalAmount: 1500,
        status: 'delivered',
        createdAt: '2024-01-10T10:00:00Z',
      },
    ],
    loyaltyTransactions: [
      {
        id: 1,
        transactionType: 'earned',
        amount: 150,
        description: 'Purchase reward',
        createdAt: '2024-01-10T10:30:00Z',
      },
    ],
  },
};

const renderUserManagementPage = () => {
  return render(
    <BrowserRouter>
      <UserManagementPage />
    </BrowserRouter>
  );
};

describe('UserManagementPage', () => {
  beforeEach(() => {
    (userManagementApi.getUsers as jest.Mock).mockResolvedValue({
      success: true,
      data: mockUsers,
    });
    (userManagementApi.getUser as jest.Mock).mockResolvedValue({
      success: true,
      data: mockUserDetail,
    });
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  it('renders user management header correctly', async () => {
    renderUserManagementPage();

    expect(screen.getByText('User Management')).toBeInTheDocument();
    expect(screen.getByText('Manage customer accounts and loyalty programs')).toBeInTheDocument();
  });

  it('displays loading state initially', () => {
    renderUserManagementPage();

    expect(screen.getByText('Loading users...')).toBeInTheDocument();
  });

  it('displays summary cards after loading', async () => {
    renderUserManagementPage();

    await waitFor(() => {
      expect(screen.getByText('Total Users')).toBeInTheDocument();
      expect(screen.getByText('3')).toBeInTheDocument();
      expect(screen.getByText('Active Users')).toBeInTheDocument();
      expect(screen.getByText('2')).toBeInTheDocument(); // John and Jane are active
      expect(screen.getByText('Platinum Members')).toBeInTheDocument();
      expect(screen.getByText('1')).toBeInTheDocument(); // Jane is platinum
      expect(screen.getByText('Gold Members')).toBeInTheDocument();
      expect(screen.getByText('1')).toBeInTheDocument(); // John is gold
    });
  });

  it('displays users table after loading', async () => {
    renderUserManagementPage();

    await waitFor(() => {
      expect(screen.getByText('John Doe')).toBeInTheDocument();
      expect(screen.getByText('Jane Smith')).toBeInTheDocument();
      expect(screen.getByText('Inactive User')).toBeInTheDocument();
    });
  });

  it('displays user information correctly', async () => {
    renderUserManagementPage();

    await waitFor(() => {
      // Check emails
      expect(screen.getByText('john.doe@example.com')).toBeInTheDocument();
      expect(screen.getByText('jane.smith@example.com')).toBeInTheDocument();
      
      // Check phone numbers
      expect(screen.getByText('+639123456789')).toBeInTheDocument();
      expect(screen.getByText('+639987654321')).toBeInTheDocument();
      
      // Check loyalty tiers
      expect(screen.getByText('Gold')).toBeInTheDocument();
      expect(screen.getByText('Platinum')).toBeInTheDocument();
      expect(screen.getByText('Bronze')).toBeInTheDocument();
      
      // Check credits and spending
      expect(screen.getByText('₱2,500')).toBeInTheDocument(); // John's credits
      expect(screen.getByText('₱5,000')).toBeInTheDocument(); // Jane's credits
      expect(screen.getByText('₱15,000')).toBeInTheDocument(); // John's total spent
      expect(screen.getByText('₱35,000')).toBeInTheDocument(); // Jane's total spent
    });
  });

  it('displays user status badges correctly', async () => {
    renderUserManagementPage();

    await waitFor(() => {
      const activeStatuses = screen.getAllByText('Active');
      expect(activeStatuses).toHaveLength(2); // John and Jane
      expect(screen.getByText('Inactive')).toBeInTheDocument(); // Inactive user
    });
  });

  it('displays loyalty tier badges with correct colors', async () => {
    renderUserManagementPage();

    await waitFor(() => {
      const goldBadge = screen.getByText('Gold');
      const platinumBadge = screen.getByText('Platinum');
      const bronzeBadge = screen.getByText('Bronze');
      
      expect(goldBadge).toHaveClass('bg-yellow-100', 'text-yellow-800');
      expect(platinumBadge).toHaveClass('bg-purple-100', 'text-purple-800');
      expect(bronzeBadge).toHaveClass('bg-orange-100', 'text-orange-800');
    });
  });

  it('formats join dates correctly', async () => {
    renderUserManagementPage();

    await waitFor(() => {
      expect(screen.getByText('Jun 15, 2023')).toBeInTheDocument(); // John's join date
      expect(screen.getByText('May 10, 2023')).toBeInTheDocument(); // Jane's join date
      expect(screen.getByText('Aug 1, 2023')).toBeInTheDocument(); // Inactive user's join date
    });
  });

  it('opens filters panel when filters button is clicked', async () => {
    renderUserManagementPage();

    await waitFor(() => {
      expect(screen.getByText('John Doe')).toBeInTheDocument();
    });

    const filtersButton = screen.getByText('Filters');
    fireEvent.click(filtersButton);

    expect(screen.getByText('Filter Users')).toBeInTheDocument();
  });

  it('opens user detail modal when user is clicked', async () => {
    renderUserManagementPage();

    await waitFor(() => {
      expect(screen.getByText('John Doe')).toBeInTheDocument();
    });

    const userRow = screen.getByText('John Doe').closest('tr');
    fireEvent.click(userRow!);

    await waitFor(() => {
      expect(userManagementApi.getUser).toHaveBeenCalledWith(1);
    });
  });

  it('handles API errors gracefully', async () => {
    (userManagementApi.getUsers as jest.Mock).mockRejectedValue(new Error('API Error'));

    renderUserManagementPage();

    await waitFor(() => {
      expect(screen.getByText('Error Loading Users')).toBeInTheDocument();
      expect(screen.getByText('Failed to load users')).toBeInTheDocument();
    });
  });

  it('allows retry when error occurs', async () => {
    (userManagementApi.getUsers as jest.Mock).mockRejectedValueOnce(new Error('API Error'));

    renderUserManagementPage();

    await waitFor(() => {
      expect(screen.getByText('Error Loading Users')).toBeInTheDocument();
    });

    // Mock successful retry
    (userManagementApi.getUsers as jest.Mock).mockResolvedValue({
      success: true,
      data: mockUsers,
    });

    const retryButton = screen.getByText('Retry');
    fireEvent.click(retryButton);

    await waitFor(() => {
      expect(screen.getByText('User Management')).toBeInTheDocument();
    });
  });

  it('displays correct user counts and pagination info', async () => {
    renderUserManagementPage();

    await waitFor(() => {
      expect(screen.getByText('Showing 1 to 3 of 3 results')).toBeInTheDocument();
    });
  });

  it('displays user initials in avatar circles', async () => {
    renderUserManagementPage();

    await waitFor(() => {
      expect(screen.getByText('JD')).toBeInTheDocument(); // John Doe
      expect(screen.getByText('JS')).toBeInTheDocument(); // Jane Smith
      expect(screen.getByText('IU')).toBeInTheDocument(); // Inactive User
    });
  });

  it('handles empty user list', async () => {
    (userManagementApi.getUsers as jest.Mock).mockResolvedValue({
      success: true,
      data: {
        data: [],
        meta: {
          currentPage: 1,
          lastPage: 1,
          perPage: 20,
          total: 0,
          from: 0,
          to: 0,
        },
        links: {
          first: '/api/admin/users?page=1',
          last: '/api/admin/users?page=1',
          prev: null,
          next: null,
        },
      },
    });

    renderUserManagementPage();

    await waitFor(() => {
      expect(screen.getByText('No users found')).toBeInTheDocument();
    });
  });

  it('calls users API with correct default parameters', async () => {
    renderUserManagementPage();

    await waitFor(() => {
      expect(userManagementApi.getUsers).toHaveBeenCalledWith({
        per_page: 20,
        sort_by: 'created_at',
        sort_direction: 'desc',
      });
    });
  });

  it('displays user ID correctly', async () => {
    renderUserManagementPage();

    await waitFor(() => {
      expect(screen.getByText('ID: 1')).toBeInTheDocument();
      expect(screen.getByText('ID: 2')).toBeInTheDocument();
      expect(screen.getByText('ID: 3')).toBeInTheDocument();
    });
  });

  it('handles users with missing phone numbers', async () => {
    renderUserManagementPage();

    await waitFor(() => {
      // John and Jane have phone numbers, Inactive User doesn't
      expect(screen.getByText('+639123456789')).toBeInTheDocument();
      expect(screen.getByText('+639987654321')).toBeInTheDocument();
      // Inactive User row should not have a phone number displayed
      const inactiveUserRow = screen.getByText('Inactive User').closest('tr');
      expect(inactiveUserRow).not.toHaveTextContent('+63');
    });
  });
});