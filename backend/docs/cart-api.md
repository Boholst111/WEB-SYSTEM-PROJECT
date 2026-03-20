# Cart Management API Documentation

## Overview

The Cart Management API provides comprehensive shopping cart functionality with inventory validation, loyalty credits integration, and shipping cost calculations.

## Endpoints

### 1. Get Cart
**GET** `/api/cart`

Retrieves the user's shopping cart with all items, calculations, and available options.

**Authentication:** Required

**Response:**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 1,
        "product_id": 123,
        "product": {
          "id": 123,
          "name": "Hot Wheels 1:64 Scale Model",
          "sku": "HW-001",
          "brand": "Hot Wheels",
          "category": "Diecast Cars",
          "main_image": "https://cdn.example.com/image.jpg",
          "current_price": 100.00,
          "stock_quantity": 10,
          "is_available": true
        },
        "quantity": 2,
        "price": 100.00,
        "total": 200.00,
        "formatted_total": "₱200.00"
      }
    ],
    "summary": {
      "subtotal": 200.00,
      "formatted_subtotal": "₱200.00",
      "items_count": 1,
      "total_quantity": 2
    },
    "loyalty": {
      "available_credits": 500.00,
      "max_credits_usable": 100.00,
      "formatted_available": "₱500.00",
      "formatted_max_usable": "₱100.00"
    },
    "shipping_options": {
      "options": [
        {
          "id": "lbc:standard",
          "courier": "lbc",
          "service": "standard",
          "name": "LBC Standard",
          "description": "Standard delivery within 3-5 business days",
          "estimated_days": 3,
          "cost": 150.00,
          "formatted_cost": "₱150.00",
          "is_free": false
        }
      ],
      "free_shipping_threshold": 2000.00,
      "formatted_threshold": "₱2,000.00",
      "is_free_shipping_eligible": false,
      "amount_to_free_shipping": 1800.00,
      "formatted_amount_to_free": "₱1,800.00"
    }
  }
}
```

---

### 2. Add Item to Cart
**POST** `/api/cart/items`

Adds a product to the cart with inventory validation.

**Authentication:** Required

**Request Body:**
```json
{
  "product_id": 123,
  "quantity": 2
}
```

**Validation Rules:**
- `product_id`: required, must exist in products table
- `quantity`: required, integer, minimum 1

**Response (Success):**
```json
{
  "success": true,
  "message": "Item added to cart successfully",
  "data": {
    "id": 1,
    "product_id": 123,
    "quantity": 2,
    "price": 100.00,
    "total": 200.00,
    "product": {
      "id": 123,
      "name": "Hot Wheels 1:64 Scale Model",
      "sku": "HW-001",
      "main_image": "https://cdn.example.com/image.jpg"
    }
  }
}
```

**Error Responses:**
- **400 Bad Request** - Product not available or insufficient stock
- **422 Unprocessable Entity** - Validation errors

---

### 3. Update Cart Item
**PUT** `/api/cart/items/{itemId}`

Updates the quantity of a cart item with inventory validation.

**Authentication:** Required

**Request Body:**
```json
{
  "quantity": 5
}
```

**Validation Rules:**
- `quantity`: required, integer, minimum 1

**Response:**
```json
{
  "success": true,
  "message": "Cart item updated successfully",
  "data": {
    "id": 1,
    "quantity": 5,
    "price": 100.00,
    "total": 500.00
  }
}
```

---

### 4. Remove Item from Cart
**DELETE** `/api/cart/items/{itemId}`

Removes a specific item from the cart.

**Authentication:** Required

**Response:**
```json
{
  "success": true,
  "message": "Item removed from cart successfully"
}
```

---

### 5. Clear Cart
**DELETE** `/api/cart/clear`

Removes all items from the user's cart.

**Authentication:** Required

**Response:**
```json
{
  "success": true,
  "message": "Cart cleared successfully"
}
```

---

### 6. Calculate Cart Totals
**POST** `/api/cart/calculate-totals`

Calculates final cart totals including loyalty credits and shipping costs.

**Authentication:** Required

**Request Body:**
```json
{
  "credits_to_use": 50.00,
  "shipping_option": "lbc:standard"
}
```

**Parameters:**
- `credits_to_use` (optional): Amount of loyalty credits to apply
- `shipping_option` (optional): Shipping option ID (format: "courier:service")

**Response:**
```json
{
  "success": true,
  "data": {
    "subtotal": 200.00,
    "credits_applied": 50.00,
    "discount_amount": 50.00,
    "shipping_cost": 150.00,
    "total": 300.00,
    "formatted": {
      "subtotal": "₱200.00",
      "credits_applied": "₱50.00",
      "discount_amount": "₱50.00",
      "shipping_cost": "₱150.00",
      "total": "₱300.00"
    }
  }
}
```

**Business Rules:**
- Maximum 50% of subtotal can be paid with loyalty credits
- Minimum redemption amount: ₱100
- Credits cannot exceed available balance
- Free shipping applies when subtotal ≥ ₱2,000 (or tier-specific threshold)

---

### 7. Validate Cart Inventory
**GET** `/api/cart/validate-inventory`

Validates that all cart items have sufficient inventory and are available.

**Authentication:** Required

**Response (Valid):**
```json
{
  "success": true,
  "data": {
    "valid": true,
    "errors": []
  }
}
```

**Response (Invalid):**
```json
{
  "success": true,
  "data": {
    "valid": false,
    "errors": [
      {
        "cart_item_id": 1,
        "product_id": 123,
        "product_name": "Hot Wheels 1:64 Scale Model",
        "error": "Insufficient stock",
        "requested_quantity": 15,
        "available_quantity": 10
      }
    ]
  }
}
```

---

## Features

### Inventory Validation
- Real-time stock checking when adding/updating items
- Prevents adding more items than available stock
- Validates product availability status

### Cart Persistence
- Cart persists across user sessions
- Stored in database with user association
- Automatic cleanup of old cart items

### Loyalty Credits Integration
- Shows available credits and maximum usable amount
- Enforces 50% maximum redemption rule
- Validates minimum redemption threshold (₱100)
- Respects user's available credit balance

### Shipping Cost Calculation
- Multiple courier options (LBC, J&T, Ninja Van, 2GO)
- Different service levels (standard, express, same-day)
- Free shipping threshold: ₱2,000
- Tier-specific free shipping:
  - **Bronze**: ₱2,000 threshold
  - **Silver**: ₱5,000 threshold
  - **Gold**: ₱3,000 threshold
  - **Platinum**: Free shipping on all orders

### Price Updates
- Cart items store price at time of addition
- Prices update to current price when quantity changes
- Protects against price fluctuations during checkout

---

## Error Handling

### Common Error Codes

**400 Bad Request**
- Product not available
- Insufficient stock
- Cart is empty (when calculating totals)

**404 Not Found**
- Cart item not found
- User trying to access another user's cart item

**422 Unprocessable Entity**
- Validation errors (missing fields, invalid values)

**500 Internal Server Error**
- Database errors
- Unexpected system errors

---

## Testing

The cart management system includes comprehensive test coverage:

- **67 tests** with **243 assertions**
- Unit tests for CartService business logic
- Feature tests for API endpoints
- Integration tests for inventory validation
- Tests for loyalty credits calculations
- Tests for shipping cost calculations
- Tests for cart persistence

Run tests:
```bash
php artisan test --filter=Cart
```

---

## Related Services

### CartService
Business logic service for cart calculations:
- `calculateCartTotals()` - Calculate final totals with credits and shipping
- `calculateMaxCreditsUsable()` - Determine maximum credits that can be applied
- `calculateShippingCost()` - Calculate shipping cost for selected option
- `getShippingOptions()` - Get all available shipping options
- `validateCartInventory()` - Validate all cart items have sufficient stock
- `getCartSummary()` - Get cart summary (items count, quantity, subtotal)

### ShippingService
Handles shipping operations:
- Shipping label generation
- Tracking information
- Courier integrations

---

## Configuration

### Loyalty Credits
Configuration in `config/loyalty.php`:
- `redemption.maximum_percentage`: 50% (max credits as % of subtotal)
- `redemption.minimum_amount`: ₱100 (minimum redemption)
- `redemption.conversion_rate`: 1.0 (1 credit = ₱1)

### Shipping
Configuration in `config/shipping.php`:
- `free_shipping_threshold`: ₱2,000
- `couriers`: Courier configurations and rates
- `zones`: Regional shipping zones and multipliers

---

## Best Practices

1. **Always validate inventory** before proceeding to checkout
2. **Use calculate-totals endpoint** to show accurate final prices
3. **Handle out-of-stock scenarios** gracefully in the UI
4. **Show shipping options** with estimated delivery times
5. **Display loyalty credits** prominently to encourage usage
6. **Update cart prices** when products are modified
7. **Implement cart abandonment** recovery strategies
