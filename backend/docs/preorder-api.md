# Pre-order Management API

This document describes the pre-order management API endpoints implemented for the Diecast Empire e-commerce platform.

## Overview

The pre-order system allows customers to reserve products before they arrive in stock by paying a deposit (typically 30-50% of the product price). When the product arrives, customers are notified and can complete the final payment.

## API Endpoints

### User Endpoints

#### GET /api/preorders
List user's pre-orders with optional filtering and pagination.

**Query Parameters:**
- `status` - Filter by pre-order status
- `product_id` - Filter by specific product
- `sort_by` - Sort field (default: created_at)
- `sort_order` - Sort direction (default: desc)
- `per_page` - Items per page (max: 50)

#### POST /api/preorders
Create a new pre-order for a product.

**Request Body:**
```json
{
  "product_id": 123,
  "quantity": 2,
  "deposit_percentage": 0.4
}
```

#### GET /api/preorders/{id}
Get detailed information about a specific pre-order.

#### POST /api/preorders/{id}/deposit
Process deposit payment for a pre-order.

**Request Body:**
```json
{
  "payment_method": "gcash",
  "gateway_data": {
    "phone": "09123456789"
  }
}
```

#### POST /api/preorders/{id}/complete-payment
Complete final payment for a pre-order.

**Request Body:**
```json
{
  "payment_method": "maya"
}
```

#### GET /api/preorders/{id}/status
Get current status and payment information for a pre-order.

#### GET /api/preorders/{id}/notifications
Get notifications related to a specific pre-order.

### Admin Endpoints

#### GET /api/admin/preorders
List all pre-orders with advanced filtering and statistics.

**Query Parameters:**
- `status` - Filter by status
- `product_id` - Filter by product
- `user_id` - Filter by user
- `date_from` / `date_to` - Date range filter
- `arrival_status` - Filter by arrival status (arrived, pending, overdue)
- `search` - Search by pre-order number, user email, or product name

#### GET /api/admin/preorders/{id}
Get detailed admin view of a pre-order including payment history.

#### PUT /api/admin/preorders/{id}/arrival
Update pre-order arrival status and notify customers.

**Request Body:**
```json
{
  "actual_arrival_date": "2024-03-20",
  "notify_customers": true,
  "notes": "Product arrived in good condition"
}
```

#### PUT /api/admin/preorders/{id}/status
Update pre-order status with optional customer notification.

**Request Body:**
```json
{
  "status": "ready_for_payment",
  "reason": "Product has arrived",
  "notify_customer": true
}
```

#### POST /api/admin/preorders/{id}/notify
Send notifications to customers about pre-order updates.

**Request Body:**
```json
{
  "notification_type": "arrival",
  "message": "Your pre-ordered item has arrived!",
  "subject": "Pre-order Arrival Notification"
}
```

#### POST /api/admin/preorders/bulk-update
Perform bulk operations on multiple pre-orders.

**Request Body:**
```json
{
  "preorder_ids": [1, 2, 3],
  "action": "mark_arrived",
  "arrival_date": "2024-03-20"
}
```

#### GET /api/admin/preorders/analytics/reports
Get analytics and reports for pre-orders.

**Query Parameters:**
- `date_from` / `date_to` - Date range for analytics

## Status Workflow

Pre-orders follow a defined status workflow:

1. **deposit_pending** - Initial state, waiting for deposit payment
2. **deposit_paid** - Deposit has been paid, waiting for product arrival
3. **ready_for_payment** - Product has arrived, ready for final payment
4. **payment_completed** - Final payment completed, ready for shipping
5. **shipped** - Order has been shipped to customer
6. **delivered** - Order has been delivered
7. **cancelled** - Pre-order was cancelled
8. **expired** - Pre-order expired due to non-payment

## Payment Integration

The system supports multiple payment methods:
- **GCash** - Philippine mobile wallet
- **Maya** - Philippine digital payment platform
- **Bank Transfer** - Direct bank transfer

Payment processing is handled through mock implementations that can be easily replaced with actual payment gateway integrations.

## Testing

The pre-order system includes comprehensive testing:

### Unit Tests
- Pre-order creation and validation
- Payment processing workflows
- Status transitions and business rules
- User authorization and access control
- API endpoint functionality

### Property-Based Tests
- **Payment Flow Integrity**: Validates that deposit + remaining amounts always equal total price and status transitions follow the defined workflow
- **Payment Records Integrity**: Ensures payment records maintain consistency with pre-order amounts
- **Cancellation Rules**: Verifies cancellation rules are properly enforced based on status and arrival

## Security Features

- User authentication required for all pre-order operations
- Users can only access their own pre-orders
- Admin role required for administrative functions
- Payment data is securely handled and stored
- Input validation and sanitization on all endpoints

## Error Handling

The API provides comprehensive error handling with appropriate HTTP status codes:
- `400` - Bad Request (validation errors, business rule violations)
- `401` - Unauthorized (authentication required)
- `403` - Forbidden (insufficient permissions)
- `404` - Not Found (resource doesn't exist)
- `422` - Unprocessable Entity (validation failures)
- `500` - Internal Server Error (system errors)

All error responses include detailed error messages and validation details where applicable.