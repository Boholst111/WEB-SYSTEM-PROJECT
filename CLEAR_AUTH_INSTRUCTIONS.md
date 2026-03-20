# Clear Authentication and Re-login

## Why You Need to Do This

Your current login session was created BEFORE we added the `role` field to your account. The authentication token stored in your browser doesn't include the admin role, so the system still sees you as a regular user (or not authenticated).

## Steps to Fix

### Option 1: Clear Browser Storage (Recommended)

1. Open your browser's Developer Tools (F12)
2. Go to the "Application" or "Storage" tab
3. Find "Local Storage" in the left sidebar
4. Click on `http://localhost:3000`
5. Find the key `auth_token` and delete it
6. Refresh the page
7. Log in again with your credentials

### Option 2: Use Browser Console

1. Open your browser's Developer Tools (F12)
2. Go to the "Console" tab
3. Type: `localStorage.removeItem('auth_token')`
4. Press Enter
5. Refresh the page
6. Log in again

### Option 3: Click Logout (If Available)

1. If you can see a logout button in the header, click it
2. You'll be redirected to the home page
3. Click "Sign In" and log in again

## After Re-login

Once you log back in:
- Your new session will include `role: 'admin'`
- You'll be able to access `/admin` routes
- The admin dashboard will load successfully
- No more 403 Forbidden errors

## Verify Your Admin Access

After logging in, you can verify your role by:

1. Open Developer Tools (F12)
2. Go to Console tab
3. Type: `localStorage.getItem('auth_token')`
4. You should see a token

Then navigate to http://localhost:3000/admin - it should work!

## Your Admin Credentials

Email: john.boholst@urios.edu.ph
Password: [your password]
Role: admin ✅

## Troubleshooting

If you still can't access `/admin` after re-login:

1. Check browser console for errors
2. Verify the backend is running (http://localhost:8080/api/health)
3. Check that your user has admin role:
   ```bash
   cd backend
   php artisan tinker --execute="echo App\Models\User::where('email', 'john.boholst@urios.edu.ph')->first()->role;"
   ```
   Should output: `admin`

## What Changed

Before:
```json
{
  "user": {
    "id": 1,
    "email": "john.boholst@urios.edu.ph",
    "firstName": "JOHN",
    "lastName": "BOHOLST"
    // No role field
  }
}
```

After re-login:
```json
{
  "user": {
    "id": 1,
    "email": "john.boholst@urios.edu.ph",
    "firstName": "JOHN",
    "lastName": "BOHOLST",
    "role": "admin"  // ✅ Now includes role
  }
}
```
