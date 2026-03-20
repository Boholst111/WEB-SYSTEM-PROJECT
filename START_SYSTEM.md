# Diecast Empire - System Startup Guide

## Prerequisites

Since Docker is not available on this system, you'll need to install and run the services manually:

### Required Software
1. **PHP 8.2+** with extensions: mbstring, xml, ctype, json, mysql, redis
2. **Composer** (PHP dependency manager)
3. **Node.js 18+** and npm
4. **MySQL 8.0+**
5. **Redis 7+**

## Quick Start (Without Docker)

### 1. Start MySQL Database

```bash
# Start MySQL service (Windows)
net start MySQL80

# Or if using XAMPP/WAMP, start MySQL from the control panel
```

### 2. Create Database

```bash
# Connect to MySQL
mysql -u root -p

# Create database and user
CREATE DATABASE diecast_empire_db;
CREATE USER 'diecast_user'@'localhost' IDENTIFIED BY 'diecast_password';
GRANT ALL PRIVILEGES ON diecast_empire_db.* TO 'diecast_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3. Start Redis

```bash
# If Redis is installed as a service (Windows)
net start Redis

# Or download and run Redis from: https://github.com/microsoftarchive/redis/releases
redis-server
```

### 4. Setup Backend (Laravel)

```bash
# Navigate to backend directory
cd backend

# Copy environment file
cp .env.example .env

# Update .env file with local settings:
# DB_HOST=127.0.0.1 (or localhost)
# REDIS_HOST=127.0.0.1 (or localhost)

# Install dependencies
composer install

# Generate application key
php artisan key:generate

# Run database migrations
php artisan migrate

# Seed database with sample data (optional)
php artisan db:seed

# Start Laravel development server
php artisan serve --host=0.0.0.0 --port=8080
```

Backend will be available at: http://localhost:8080

### 5. Setup Frontend (React)

Open a new terminal:

```bash
# Navigate to frontend directory
cd frontend

# Copy environment file
cp .env.example .env

# Update .env file:
# REACT_APP_API_URL=http://localhost:8080/api

# Install dependencies
npm install

# Start development server
npm start
```

Frontend will be available at: http://localhost:3000

## Alternative: Using PHP Built-in Server

If you don't want to use `php artisan serve`, you can use PHP's built-in server:

```bash
cd backend/public
php -S localhost:8080
```

## Verify System is Running

### Check Backend Health
```bash
curl http://localhost:8080/api/health
```

Expected response:
```json
{
  "status": "ok",
  "timestamp": "2024-01-15T10:30:00Z",
  "version": "1.0.0",
  "environment": "local"
}
```

### Check Frontend
Open browser: http://localhost:3000

### Check Database Connection
```bash
cd backend
php artisan tinker
>>> DB::connection()->getPdo();
```

### Check Redis Connection
```bash
cd backend
php artisan tinker
>>> Redis::connection()->ping();
```

## Running Tests

### Backend Tests
```bash
cd backend

# Run all tests
php artisan test

# Run specific test suites
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature

# Run property-based tests
php artisan test --filter=Property
```

### Frontend Tests
```bash
cd frontend
npm test
```

## Common Issues

### Issue: Port Already in Use

**Backend (8080):**
```bash
# Find process using port 8080
netstat -ano | findstr :8080

# Kill the process
taskkill /PID <process_id> /F
```

**Frontend (3000):**
```bash
# Find process using port 3000
netstat -ano | findstr :3000

# Kill the process
taskkill /PID <process_id> /F
```

### Issue: Database Connection Failed

1. Verify MySQL is running: `net start | findstr MySQL`
2. Check credentials in `backend/.env`
3. Test connection: `mysql -u diecast_user -p diecast_empire_db`

### Issue: Redis Connection Failed

1. Verify Redis is running
2. Check Redis host in `backend/.env` (should be 127.0.0.1 or localhost)
3. Test connection: `redis-cli ping`

## Production Deployment

For production deployment with Docker, see: `DEPLOYMENT.md`

## System Architecture

```
┌─────────────────┐
│  React Frontend │  (Port 3000)
│   (npm start)   │
└────────┬────────┘
         │
         │ HTTP API Calls
         │
┌────────▼────────┐
│ Laravel Backend │  (Port 8080)
│ (php artisan    │
│     serve)      │
└────────┬────────┘
         │
    ┌────┴────┐
    │         │
┌───▼──┐  ┌──▼───┐
│MySQL │  │Redis │
│(3306)│  │(6379)│
└──────┘  └──────┘
```

## Next Steps

1. Access the application at http://localhost:3000
2. Register a new user account
3. Browse the product catalog
4. Test the shopping cart and checkout
5. Explore admin dashboard (if admin user created)

## Support

For issues or questions:
- Check logs: `backend/storage/logs/laravel.log`
- Run system validation: `./scripts/validate-system.sh`
- Review documentation: `DEPLOYMENT.md`
