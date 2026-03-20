# Admin Analytics API Documentation

## Overview

The Admin Analytics API provides comprehensive business intelligence and reporting capabilities for the Diecast Empire platform. It includes sales metrics, product performance analytics, customer behavior insights, and traffic analysis.

## Authentication

All analytics endpoints require admin authentication using Laravel Sanctum tokens with admin role permissions.

```
Authorization: Bearer {admin_token}
```

## Base URL

```
/api/admin/analytics
```

## Endpoints

### 1. Dashboard Overview

**GET** `/api/admin/analytics`

Returns comprehensive analytics dashboard data including all major metrics.

**Parameters:**
- `date_from` (optional): Start date (YYYY-MM-DD), defaults to 1 month ago
- `date_to` (optional): End date (YYYY-MM-DD), defaults to today
- `period` (optional): Time series granularity (`daily`, `weekly`, `monthly`), defaults to `daily`

**Response:**
```json
{
  "success": true,
  "data": {
    "sales_analytics": {
      "revenue_metrics": {
        "total_revenue": 125000.50,
        "gross_revenue": 130000.00,
        "discount_amount": 2500.00,
        "credits_used": 1500.00,
        "shipping_revenue": 3500.00,
        "net_revenue": 121000.50
      },
      "order_metrics": {
        "total_orders": 450,
        "paid_orders": 425,
        "average_order_value": 294.12,
        "conversion_rate": 94.44,
        "status_breakdown": {
          "pending": 15,
          "confirmed": 10,
          "shipped": 200,
          "delivered": 200,
          "cancelled": 25
        }
      },
      "payment_analytics": {
        "payment_methods": [
          {
            "payment_method": "gcash",
            "count": 200,
            "revenue": 58000.00,
            "avg_amount": 290.00
          }
        ]
      },
      "time_series": [
        {
          "period": "2024-03-01",
          "revenue": 4500.00,
          "orders": 15,
          "avg_order_value": 300.00
        }
      ],
      "growth_comparison": {
        "current_period": {
          "revenue": 125000.50,
          "orders": 450
        },
        "previous_period": {
          "revenue": 110000.00,
          "orders": 400
        },
        "growth": {
          "revenue_growth": 13.64,
          "order_growth": 12.50
        }
      }
    },
    "product_analytics": {
      "best_sellers": [
        {
          "product_id": 1,
          "total_sold": 150,
          "total_revenue": 45000.00,
          "order_count": 75,
          "avg_price": 300.00,
          "product": {
            "name": "Hot Wheels Premium Car",
            "sku": "HW-001"
          }
        }
      ],
      "slow_movers": [...],
      "inventory_turnover": [...],
      "category_performance": [...],
      "brand_performance": [...],
      "price_analysis": {
        "price_ranges": [
          {
            "price_range": "Under ₱500",
            "count": 200,
            "revenue": 75000.00
          }
        ],
        "avg_selling_price": 285.50
      }
    },
    "customer_analytics": {
      "acquisition_metrics": {
        "new_customers": 85,
        "total_customers": 1250,
        "growth_rate": 7.29
      },
      "retention_metrics": {
        "returning_customers": 320,
        "retention_rate": 25.60
      },
      "lifetime_value": {
        "avg_lifetime_value": 1850.00,
        "top_customers": [...]
      },
      "loyalty_analysis": {
        "tier_distribution": {
          "bronze": 800,
          "silver": 300,
          "gold": 120,
          "platinum": 30
        },
        "credits_earned": 15000.00,
        "credits_redeemed": 8500.00,
        "utilization_rate": 56.67
      },
      "segmentation": {
        "segments": {
          "VIP": 25,
          "Loyal": 150,
          "Regular": 200,
          "New": 85
        }
      }
    },
    "traffic_analysis": {
      "summary": {
        "estimated_visitors": 9000,
        "conversion_rate": 4.72,
        "cart_abandonment_rate": 15.50,
        "bounce_rate": 45.20,
        "avg_session_duration": "3:24"
      },
      "popular_products": [...],
      "device_types": {
        "desktop": 5400,
        "mobile": 3150,
        "tablet": 450
      },
      "peak_hours": [...]
    },
    "loyalty_metrics": {
      "summary": {
        "credits_earned": 15000.00,
        "credits_redeemed": 8500.00,
        "active_members": 450,
        "utilization_rate": 56.67,
        "tier_progressions": 12,
        "expiring_credits": 2500.00
      },
      "top_earners": [...]
    },
    "inventory_insights": {
      "low_stock_products": [...],
      "out_of_stock_count": 15,
      "total_inventory_value": 2500000.00,
      "preorder_stats": {
        "total_preorders": 85,
        "pending_arrivals": 45,
        "overdue_arrivals": 8
      }
    },
    "date_range": {
      "from": "2024-02-18",
      "to": "2024-03-18",
      "period": "daily"
    }
  }
}
```

### 2. Sales Metrics

**GET** `/api/admin/analytics/sales-metrics`

Returns detailed sales and revenue analytics.

**Parameters:**
- `date_from` (optional): Start date
- `date_to` (optional): End date  
- `period` (optional): Time series granularity

### 3. Product Performance

**GET** `/api/admin/analytics/product-performance`

Returns product performance analytics including best sellers, slow movers, and inventory turnover.

**Parameters:**
- `date_from` (optional): Start date
- `date_to` (optional): End date
- `limit` (optional): Number of products to return (max 100), defaults to 20

### 4. Customer Analytics

**GET** `/api/admin/analytics/customer-analytics`

Returns customer behavior and loyalty analytics.

**Parameters:**
- `date_from` (optional): Start date
- `date_to` (optional): End date

### 5. Traffic Analysis

**GET** `/api/admin/analytics/traffic-analysis`

Returns traffic and conversion analytics.

**Parameters:**
- `date_from` (optional): Start date
- `date_to` (optional): End date

### 6. Real-time Summary

**GET** `/api/admin/analytics/real-time-summary`

Returns real-time dashboard summary for today's activity.

**Response:**
```json
{
  "success": true,
  "data": {
    "today_orders": 25,
    "today_revenue": 7500.00,
    "pending_orders": 8,
    "low_stock_products": 12,
    "active_users_today": 150,
    "conversion_rate_today": 5.2
  }
}
```

## Error Responses

All endpoints return consistent error responses:

```json
{
  "success": false,
  "message": "Error description"
}
```

**Common HTTP Status Codes:**
- `200`: Success
- `401`: Unauthorized (missing or invalid token)
- `403`: Forbidden (insufficient permissions)
- `422`: Validation Error (invalid parameters)
- `500`: Internal Server Error

## Caching

Analytics data is cached for 5 minutes (300 seconds) to improve performance. Cache keys are based on date range and period parameters.

## Rate Limiting

Admin analytics endpoints are subject to standard API rate limiting (60 requests per minute per user).

## Data Freshness

- Real-time summary: Updated every minute
- Dashboard analytics: Cached for 5 minutes
- Historical data: Updated when new orders/transactions are processed

## Performance Considerations

- Use appropriate date ranges to avoid large dataset queries
- Leverage caching by using consistent parameters
- Consider using the `limit` parameter for product performance queries
- Real-time summary is optimized for frequent polling

## Examples

### Get last 7 days analytics
```bash
curl -H "Authorization: Bearer {token}" \
  "http://localhost:8000/api/admin/analytics?date_from=2024-03-11&date_to=2024-03-18&period=daily"
```

### Get monthly sales metrics
```bash
curl -H "Authorization: Bearer {token}" \
  "http://localhost:8000/api/admin/analytics/sales-metrics?date_from=2024-02-01&date_to=2024-02-29&period=monthly"
```

### Get top 10 products
```bash
curl -H "Authorization: Bearer {token}" \
  "http://localhost:8000/api/admin/analytics/product-performance?limit=10"
```