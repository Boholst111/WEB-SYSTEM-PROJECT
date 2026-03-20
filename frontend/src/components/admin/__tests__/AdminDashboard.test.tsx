import React from 'react';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import AdminDashboard from '../../../pages/admin/AdminDashboard';
import { analyticsApi } from '../../../services/adminApi';

// Mock the analytics API
jest.mock('../../../services/adminApi', () => ({
  analyticsApi: {
    getDashboard: jest.fn(),
    getRealTimeSummary: jest.fn(),
  },
}));

// Mock recharts components
jest.mock('recharts', () => ({
  LineChart: ({ children }: any) => <div data-testid="line-chart">{children}</div>,
  Line: () => <div data-testid="line" />,
  XAxis: () => <div data-testid="x-axis" />,
  YAxis: () => <div data-testid="y-axis" />,
  CartesianGrid: () => <div data-testid="cartesian-grid" />,
  Tooltip: () => <div data-testid="tooltip" />,
  ResponsiveContainer: ({ children }: any) => <div data-testid="responsive-container">{children}</div>,
  BarChart: ({ children }: any) => <div data-testid="bar-chart">{children}</div>,
  Bar: () => <div data-testid="bar" />,
  PieChart: ({ children }: any) => <div data-testid="pie-chart">{children}</div>,
  Pie: () => <div data-testid="pie" />,
  Cell: () => <div data-testid="cell" />,
}));

const mockDashboardData = {
  sales_analytics: {
    total_revenue: 150000,
    total_orders: 250,
    average_order_value: 600,
    conversion_rate: 3.5,
    growth_rate: 12.5,
    revenue_by_period: [
      { date: '2024-01-01', revenue: 5000, orders: 10 },
      { date: '2024-01-02', revenue: 7500, orders: 15 },
    ],
  },
  product_analytics: {
    best_sellers: [
      { id: 1, name: 'Hot Wheels Car', sku: 'HW001', total_sold: 50, revenue: 2500 },
    ],
    slow_movers: [
      { id: 2, name: 'Rare Diecast', sku: 'RD001', stock_quantity: 5, days_since_last_sale: 30 },
    ],
    inventory_turnover: 4.2,
  },
  customer_analytics: {
    total_customers: 1000,
    new_customers: 50,
    returning_customers: 200,
    customer_retention_rate: 75.5,
    loyalty_tier_distribution: { bronze: 500, silver: 300, gold: 150, platinum: 50 },
    top_customers: [
      { id: 1, name: 'John Doe', email: 'john@example.com', total_spent: 5000, order_count: 10 },
    ],
  },
  traffic_analysis: {
    summary: {
      estimated_visitors: 5000,
      conversion_rate: 3.5,
      cart_abandonment_rate: 65.2,
      bounce_rate: 45.2,
      avg_session_duration: '3:24',
    },
    popular_products: [
      { id: 1, name: 'Popular Car', sku: 'PC001', view_count: 100 },
    ],
    device_types: { desktop: 3000, mobile: 1750, tablet: 250 },
    peak_hours: [
      { hour: 14, count: 25 },
      { hour: 20, count: 30 },
    ],
  },
  loyalty_metrics: {
    summary: {
      credits_earned: 25000,
      credits_redeemed: 15000,
      active_members: 800,
      utilization_rate: 60.0,
      tier_progressions: 25,
      expiring_credits: 2500,
    },
    top_earners: [
      { id: 1, first_name: 'Jane', last_name: 'Smith', email: 'jane@example.com', loyalty_transactions_sum_amount: 1000 },
    ],
  },
  inventory_insights: {
    low_stock_products: [
      { id: 1, name: 'Low Stock Item', sku: 'LSI001', stock_quantity: 2, brand: { name: 'Brand A' }, category: { name: 'Category A' } },
    ],
    out_of_stock_count: 5,
    total_inventory_value: 500000,
    preorder_stats: {
      total_preorders: 100,
      pending_arrivals: 25,
      overdue_arrivals: 5,
    },
  },
  date_range: {
    from: '2024-01-01',
    to: '2024-01-31',
    period: 'daily',
  },
};

const mockRealTimeSummary = {
  today_orders: 15,
  today_revenue: 7500,
  pending_orders: 8,
  low_stock_products: 3,
  active_users_today: 45,
  conversion_rate_today: 4.2,
};

const renderAdminDashboard = () => {
  return render(
    <BrowserRouter>
      <AdminDashboard />
    </BrowserRouter>
  );
};

describe('AdminDashboard', () => {
  beforeEach(() => {
    (analyticsApi.getDashboard as jest.Mock).mockResolvedValue({
      success: true,
      data: mockDashboardData,
    });
    (analyticsApi.getRealTimeSummary as jest.Mock).mockResolvedValue({
      success: true,
      data: mockRealTimeSummary,
    });
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  it('renders dashboard header correctly', async () => {
    renderAdminDashboard();

    expect(screen.getByText('Admin Dashboard')).toBeInTheDocument();
    expect(screen.getByText('Overview of your Diecast Empire platform performance')).toBeInTheDocument();
  });

  it('displays loading state initially', () => {
    renderAdminDashboard();

    expect(screen.getByRole('status')).toBeInTheDocument();
  });

  it('displays real-time summary cards after loading', async () => {
    renderAdminDashboard();

    await waitFor(() => {
      expect(screen.getByText("Today's Orders")).toBeInTheDocument();
      expect(screen.getByText('15')).toBeInTheDocument();
      expect(screen.getByText("Today's Revenue")).toBeInTheDocument();
      expect(screen.getByText('₱7,500')).toBeInTheDocument();
      expect(screen.getByText('Pending Orders')).toBeInTheDocument();
      expect(screen.getByText('8')).toBeInTheDocument();
    });
  });

  it('displays analytics sections after loading', async () => {
    renderAdminDashboard();

    await waitFor(() => {
      expect(screen.getByText('Sales Analytics')).toBeInTheDocument();
      expect(screen.getByText('Product Performance')).toBeInTheDocument();
      expect(screen.getByText('Customer Analytics')).toBeInTheDocument();
      expect(screen.getByText('Traffic Analysis')).toBeInTheDocument();
    });
  });

  it('handles date range changes', async () => {
    renderAdminDashboard();

    await waitFor(() => {
      expect(screen.getByText('Sales Analytics')).toBeInTheDocument();
    });

    // Find and click the date range picker
    const dateRangePicker = screen.getByRole('button', { name: /Jan 1 - Jan 31/i });
    fireEvent.click(dateRangePicker);

    // Verify the date range picker opens
    await waitFor(() => {
      expect(screen.getByText('Quick Select')).toBeInTheDocument();
    });
  });

  it('displays error state when API fails', async () => {
    (analyticsApi.getDashboard as jest.Mock).mockRejectedValue(new Error('API Error'));
    (analyticsApi.getRealTimeSummary as jest.Mock).mockRejectedValue(new Error('API Error'));

    renderAdminDashboard();

    await waitFor(() => {
      expect(screen.getByText('Error Loading Dashboard')).toBeInTheDocument();
      expect(screen.getByText('Failed to load dashboard data')).toBeInTheDocument();
    });
  });

  it('allows retry when error occurs', async () => {
    (analyticsApi.getDashboard as jest.Mock).mockRejectedValueOnce(new Error('API Error'));
    (analyticsApi.getRealTimeSummary as jest.Mock).mockRejectedValueOnce(new Error('API Error'));

    renderAdminDashboard();

    await waitFor(() => {
      expect(screen.getByText('Error Loading Dashboard')).toBeInTheDocument();
    });

    // Mock successful retry
    (analyticsApi.getDashboard as jest.Mock).mockResolvedValue({
      success: true,
      data: mockDashboardData,
    });
    (analyticsApi.getRealTimeSummary as jest.Mock).mockResolvedValue({
      success: true,
      data: mockRealTimeSummary,
    });

    const retryButton = screen.getByText('Retry');
    fireEvent.click(retryButton);

    await waitFor(() => {
      expect(screen.getByText('Admin Dashboard')).toBeInTheDocument();
    });
  });

  it('calls analytics APIs with correct parameters', async () => {
    renderAdminDashboard();

    await waitFor(() => {
      expect(analyticsApi.getDashboard).toHaveBeenCalledWith({
        date_from: expect.any(String),
        date_to: expect.any(String),
        period: 'daily',
      });
      expect(analyticsApi.getRealTimeSummary).toHaveBeenCalled();
    });
  });

  it('auto-refreshes real-time data', async () => {
    jest.useFakeTimers();
    
    renderAdminDashboard();

    await waitFor(() => {
      expect(analyticsApi.getRealTimeSummary).toHaveBeenCalledTimes(1);
    });

    // Fast-forward 30 seconds
    jest.advanceTimersByTime(30000);

    await waitFor(() => {
      expect(analyticsApi.getRealTimeSummary).toHaveBeenCalledTimes(2);
    });

    jest.useRealTimers();
  });

  it('renders chart components', async () => {
    renderAdminDashboard();

    await waitFor(() => {
      expect(screen.getAllByTestId('responsive-container')).toHaveLength(4); // Sales, Product, Customer, Traffic charts
      expect(screen.getAllByTestId('line-chart')).toHaveLength(2); // Sales revenue and orders trend
      expect(screen.getAllByTestId('bar-chart')).toHaveLength(2); // Product performance and traffic analysis
    });
  });

  it('displays loyalty metrics card', async () => {
    renderAdminDashboard();

    await waitFor(() => {
      expect(screen.getByText('Loyalty Metrics')).toBeInTheDocument();
      expect(screen.getByText('₱25,000')).toBeInTheDocument(); // Credits earned
      expect(screen.getByText('₱15,000')).toBeInTheDocument(); // Credits redeemed
    });
  });

  it('displays inventory insights card', async () => {
    renderAdminDashboard();

    await waitFor(() => {
      expect(screen.getByText('Inventory Insights')).toBeInTheDocument();
      expect(screen.getByText('₱500,000')).toBeInTheDocument(); // Inventory value
      expect(screen.getByText('Low Stock Item')).toBeInTheDocument();
    });
  });
});