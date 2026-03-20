# Admin Access Guide

## Current System Architecture

The Diecast Empire system has **two separate user tables**:

1. **`users` table** - Regular customers who shop on the site
2. **`admin_users` table** - Admin staff who manage the system

## Admin Account Created

An admin account has been created with the following credentials:

```
Username: admin
Email: admin@diecastempire.com
Password: admin123
Role: super_admin
```

## Current Issue

The frontend admin routes (`/admin`, `/admin/dashboard`, `/admin/orders`, etc.) are currently protected by the same authentication system used for regular customers. However, the backend has a separate `admin_users` table with its own authentication.

## What Needs to Be Done

To access the admin panel, you have two options:

### Option 1: Use Separate Admin Authentication (Recommended)

This requires implementing:

1. **Admin Login Page** (`/admin/login`)
   - Separate login form for admin users
   - Authenticates against `admin_users` table
   - Stores admin token separately from user token

2. **Admin Auth API Endpoints**
   - `POST /api/admin/auth/login` - Admin login
   - `POST /api/admin/auth/logout` - Admin logout
   - `GET /api/admin/auth/me` - Get admin user info

3. **Admin Auth State**
   - Separate Redux slice for admin authentication
   - Separate token storage (e.g., `admin_token`)
   - Admin-specific ProtectedRoute component

4. **Backend Admin Auth Controller**
   - Similar to regular auth but uses `AdminUser` model
   - Returns admin-specific data

### Option 2: Quick Workaround (For Testing)

Convert your regular user account to have admin privileges:

1. Add a `role` field to the `users` table
2. Set your user's role to 'admin'
3. Update the backend middleware to check user role
4. Update frontend to check user role for admin routes

## Current Admin Routes

The following admin routes exist in the frontend:

- `/admin` or `/admin/dashboard` - Admin Dashboard
- `/admin/orders` - Order Management
- `/admin/users` - User Management  
- `/admin/inventory` - Inventory Management

## Backend Admin API Endpoints

The backend already has admin API endpoints at:

- `/api/admin/dashboard` - Dashboard data
- `/api/admin/analytics/*` - Analytics endpoints
- `/api/admin/products/*` - Product management
- `/api/admin/orders/*` - Order management
- `/api/admin/users/*` - User management
- `/api/admin/inventory/*` - Inventory management
- `/api/admin/preorders/*` - Pre-order management
- `/api/admin/notifications/*` - Notification management

These endpoints are protected by the `role:admin` middleware, which currently checks the regular `users` table.

## Recommended Next Steps

1. **Implement Admin Authentication System**:
   - Create admin login page
   - Create admin auth API endpoints
   - Implement admin auth state management
   - Update admin routes to use admin authentication

2. **Or Use Quick Workaround**:
   - Add `role` column to `users` table migration
   - Update your user account to have admin role
   - Access admin panel through regular login

## Files to Create/Modify for Full Admin Auth

### Backend
- `app/Http/Controllers/Admin/AuthController.php` - Admin authentication
- Update `routes/api.php` - Add admin auth routes
- Update middleware to check `admin_users` table

### Frontend
- `src/pages/admin/AdminLoginPage.tsx` - Admin login page
- `src/components/admin/AdminLoginForm.tsx` - Admin login form
- `src/store/slices/adminAuthSlice.ts` - Admin auth state
- `src/services/adminAuthApi.ts` - Admin auth API calls
- `src/components/admin/AdminProtectedRoute.tsx` - Admin route protection
- Update `src/App.tsx` - Add admin login route

## Temporary Access Method

For now, you can test the admin panel by:

1. Logging in as a regular user
2. Manually navigating to `/admin` routes
3. The backend will reject requests due to role middleware

To bypass this temporarily, you can:
- Comment out the `role:admin` middleware in `routes/api.php`
- Or add a `role` field to your user account and set it to 'admin'

## Security Note

⚠️ **Important**: The admin password (`admin123`) is a temporary password for development. In production:
- Use a strong, unique password
- Enable two-factor authentication
- Implement password rotation policies
- Use environment-specific credentials
- Never commit credentials to version control

## Status

✅ Admin user created in database
✅ Admin backend API endpoints exist
✅ Admin frontend pages exist
❌ Admin authentication system not connected
❌ Admin login page doesn't exist
❌ Admin routes not properly protected

The admin infrastructure is in place, but the authentication bridge between frontend and backend needs to be implemented.
