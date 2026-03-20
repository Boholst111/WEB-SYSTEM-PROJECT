# Diecast Empire - System Accounts Information

## System Status: ✅ FULLY OPERATIONAL

**Last Updated**: March 20, 2026

---

## User Accounts Summary

The system now has **2 user accounts**:
- **1 Admin Account** - Full system access
- **1 Regular User Account** - Customer access

---

## 1. ADMIN ACCOUNT (Your Account)

### Login Credentials
```
Email: john.boholst@urios.edu.ph
Password: [Your password]
Role: admin
```

### Account Details
- **User ID**: 1
- **Name**: JOHN BOHOLST
- **Email**: john.boholst@urios.edu.ph
- **Role**: admin
- **Status**: active
- **Loyalty Tier**: bronze
- **Loyalty Credits**: 0.00
- **Total Spent**: ₱0.00

### Admin Capabilities
✅ Full access to admin dashboard
✅ Order management
✅ User management
✅ Inventory management
✅ Analytics and reports
✅ Product management
✅ Pre-order management
✅ Notification management
✅ System settings

### Admin Access Points
- **Admin Dashboard**: http://localhost:3000/admin
- **Order Management**: http://localhost:3000/admin/orders
- **User Management**: http://localhost:3000/admin/users
- **Inventory**: http://localhost:3000/admin/inventory

### Admin API Endpoints
All `/api/admin/*` endpoints are accessible:
- `GET /api/admin/dashboard` - Dashboard data
- `GET /api/admin/analytics/*` - Analytics
- `GET /api/admin/products/*` - Product management
- `GET /api/admin/orders/*` - Order management
- `GET /api/admin/users/*` - User management
- `GET /api/admin/inventory/*` - Inventory management
- `GET /api/admin/preorders/*` - Pre-order management
- `GET /api/admin/notifications/*` - Notifications

---

## 2. REGULAR USER ACCOUNT (Test Customer)

### Login Credentials
```
Email: user@diecastempire.com
Password: password123
Role: user
```

### Account Details
- **User ID**: 2
- **Name**: Test Customer
- **Email**: user@diecastempire.com
- **Phone**: +639171234567
- **Date of Birth**: May 15, 1995
- **Role**: user
- **Status**: active
- **Loyalty Tier**: bronze
- **Loyalty Credits**: 0.00
- **Total Spent**: ₱0.00

### User Capabilities
✅ Browse products
✅ Search and filter
✅ Add to cart
✅ Checkout and place orders
✅ View order history
✅ Manage profile
✅ Track loyalty points
✅ Create pre-orders
✅ View wishlist
✅ Write product reviews

### User Access Points
- **Home**: http://localhost:3000/
- **Products**: http://localhost:3000/products
- **Cart**: http://localhost:3000/cart
- **Account**: http://localhost:3000/account
- **Orders**: http://localhost:3000/account (Orders tab)
- **Loyalty**: http://localhost:3000/loyalty
- **Pre-orders**: http://localhost:3000/preorders

### Restricted Access
❌ Cannot access `/admin` routes
❌ Cannot access admin API endpoints
❌ Cannot manage other users
❌ Cannot modify inventory
❌ Cannot view analytics

---

## System Architecture

### User Authentication System
The system uses a **unified authentication system** with role-based access control:

1. **Single Users Table**: Both admin and regular users are in the `users` table
2. **Role Field**: Determines access level ('user' or 'admin')
3. **JWT Tokens**: Sanctum tokens for API authentication
4. **Role Middleware**: Protects admin routes

### Separate Admin Users Table
There's also an `admin_users` table (currently unused) that was designed for a separate admin authentication system. Currently, the system uses the `users` table with role-based access.

---

## How to Test Both Accounts

### Testing Admin Account
1. Navigate to http://localhost:3000/login
2. Login with: john.boholst@urios.edu.ph
3. You'll be redirected to http://localhost:3000/admin
4. Explore admin dashboard, analytics, and management features

### Testing Regular User Account
1. **Logout** from admin account (if logged in)
2. Navigate to http://localhost:3000/login
3. Login with: user@diecastempire.com / password123
4. You'll be redirected to http://localhost:3000/ (home page)
5. Try browsing products, adding to cart, etc.
6. Try accessing http://localhost:3000/admin - should be redirected to login

---

## Database Information

### Users Table Structure
```sql
- id (primary key)
- email (unique)
- password_hash
- first_name
- last_name
- phone
- date_of_birth
- loyalty_tier (bronze, silver, gold, platinum)
- loyalty_credits (decimal)
- total_spent (decimal)
- email_verified_at
- phone_verified_at
- status (active, inactive, suspended)
- role (user, admin) ← NEW FIELD
- preferences (json)
- last_login_at
- created_at
- updated_at
```

---

## Security Notes

### Admin Account Security
⚠️ **Important Security Considerations**:

1. **Change Default Password**: Your admin account should use a strong, unique password
2. **Enable Email Verification**: Implement email verification for added security
3. **Two-Factor Authentication**: Consider adding 2FA for admin accounts
4. **Session Management**: Admin sessions should have shorter timeouts
5. **Activity Logging**: Log all admin actions for audit trails
6. **IP Whitelisting**: Consider restricting admin access to specific IPs in production

### Regular User Security
- Passwords are hashed using bcrypt
- JWT tokens expire after session
- CSRF protection disabled for API (token-based auth)
- Rate limiting on login attempts
- Email verification available (currently disabled)

---

## Creating Additional Users

### Create Another Admin User
```bash
cd backend
php artisan tinker
```
Then run:
```php
$admin = App\Models\User::create([
    'first_name' => 'Admin',
    'last_name' => 'Name',
    'email' => 'admin2@diecastempire.com',
    'password_hash' => Hash::make('password'),
    'role' => 'admin',
    'loyalty_tier' => 'bronze',
    'loyalty_credits' => 0,
    'total_spent' => 0,
    'status' => 'active',
]);
```

### Create Another Regular User
```bash
cd backend
php artisan tinker
```
Then run:
```php
$user = App\Models\User::create([
    'first_name' => 'Customer',
    'last_name' => 'Name',
    'email' => 'customer@example.com',
    'password_hash' => Hash::make('password'),
    'role' => 'user',
    'loyalty_tier' => 'bronze',
    'loyalty_credits' => 0,
    'total_spent' => 0,
    'status' => 'active',
]);
```

### Or Use Registration Page
Regular users can register at: http://localhost:3000/register

---

## Troubleshooting

### Admin Can't Access Dashboard
1. Verify role is set to 'admin':
   ```bash
   cd backend
   php check_users.php
   ```
2. Clear auth token and re-login:
   - Open http://localhost:3000/clear-auth.html
   - Click "Clear Auth Token"
   - Login again

### User Can Access Admin Routes
1. Check user role in database
2. Verify RoleMiddleware is working
3. Check browser console for errors

### Login Not Working
1. Check backend is running: http://localhost:8080/api/health
2. Check frontend is running: http://localhost:3000
3. Clear browser cache and cookies
4. Check browser console for errors

---

## API Testing

### Test Admin API Access
```bash
# Get admin token first by logging in
# Then test admin endpoint:
curl -H "Authorization: Bearer YOUR_TOKEN" http://localhost:8080/api/admin/dashboard
```

### Test Regular User API Access
```bash
# Get user token first by logging in
# Then test user endpoint:
curl -H "Authorization: Bearer YOUR_TOKEN" http://localhost:8080/api/user
```

---

## System URLs

### Frontend
- **Home**: http://localhost:3000
- **Login**: http://localhost:3000/login
- **Register**: http://localhost:3000/register
- **Admin**: http://localhost:3000/admin
- **Clear Auth**: http://localhost:3000/clear-auth.html

### Backend
- **API Base**: http://localhost:8080/api
- **Health Check**: http://localhost:8080/api/health
- **Admin API**: http://localhost:8080/api/admin/*
- **User API**: http://localhost:8080/api/*

---

## Quick Reference

### Admin Login
```
URL: http://localhost:3000/login
Email: john.boholst@urios.edu.ph
Password: [Your password]
→ Redirects to: http://localhost:3000/admin
```

### Regular User Login
```
URL: http://localhost:3000/login
Email: user@diecastempire.com
Password: password123
→ Redirects to: http://localhost:3000/
```

---

## Status Summary

✅ **2 User Accounts Created**
- 1 Admin (john.boholst@urios.edu.ph)
- 1 Regular User (user@diecastempire.com)

✅ **Role-Based Access Control Working**
- Admin can access `/admin` routes
- Regular users cannot access `/admin` routes

✅ **Authentication System Operational**
- Login/logout working
- Token-based authentication
- Role checking functional

✅ **All Errors Fixed**
- SQL GROUP BY error resolved
- Admin dashboard loading successfully
- No 500 or 403 errors

✅ **System Fully Functional**
- Backend running on port 8080
- Frontend running on port 3000
- Database connected and operational

---

## Next Steps

1. **Test Admin Features**: Login as admin and explore dashboard
2. **Test User Features**: Login as regular user and browse products
3. **Add Sample Data**: Add products, categories, brands for testing
4. **Configure Email**: Set up email for notifications
5. **Production Deployment**: Follow deployment guide when ready

---

**System is ready for use! 🎉**

For any issues, check the browser console and backend logs:
- Frontend: F12 → Console
- Backend: `backend/storage/logs/laravel.log`
