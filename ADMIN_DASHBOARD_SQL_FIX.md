# Admin Dashboard SQL Error Fix

## Issue
The admin dashboard was showing a 500 Internal Server Error when trying to load analytics data.

## Root Cause
SQL error in `AnalyticsService.php` in the `getCustomerLifetimeValue()` method:
```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'users.id' in 'on clause'
```

The query was incorrectly trying to join the `users` table and then use a subquery, which created a conflict in table references.

## Fix Applied
**File**: `backend/app/Services/AnalyticsService.php`
**Method**: `getCustomerLifetimeValue()`

**Before**:
```php
$avgLifetimeValue = DB::table('users')
    ->join('orders', 'users.id', '=', 'orders.user_id')
    ->where('orders.payment_status', 'paid')
    ->select(DB::raw('AVG(order_totals.total) as avg_value'))
    ->fromSub(function ($query) {
        $query->from('orders')
            ->where('payment_status', 'paid')
            ->select('user_id', DB::raw('SUM(total_amount) as total'))
            ->groupBy('user_id');
    }, 'order_totals')
    ->value('avg_value') ?? 0;
```

**After**:
```php
$avgLifetimeValue = DB::table(DB::raw('(SELECT user_id, SUM(total_amount) as total FROM orders WHERE payment_status = "paid" GROUP BY user_id) as order_totals'))
    ->select(DB::raw('AVG(order_totals.total) as avg_value'))
    ->value('avg_value') ?? 0;
```

## Changes Made
1. Removed the unnecessary `users` table join
2. Simplified the query to use a raw subquery directly
3. The query now correctly calculates the average lifetime value by:
   - First grouping orders by user_id and summing their total_amount
   - Then calculating the average of those totals

## Testing
- Backend server restarted on port 8080
- Health check endpoint responding correctly
- Admin dashboard should now load without 500 errors

## Next Steps
1. Clear browser cache (Ctrl+F5) to ensure fresh data
2. Log in as admin user (john.boholst@urios.edu.ph)
3. Navigate to admin dashboard
4. Verify analytics data loads correctly

## Status
✅ SQL error fixed
✅ Backend restarted
✅ Ready for testing
