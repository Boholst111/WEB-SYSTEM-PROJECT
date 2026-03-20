# Diecast Empire - Deployment Guide

## Overview

This guide covers the deployment process for the Diecast Empire e-commerce platform, including staging and production environments, system validation, and monitoring setup.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Environment Configuration](#environment-configuration)
3. [Staging Deployment](#staging-deployment)
4. [Production Deployment](#production-deployment)
5. [System Validation](#system-validation)
6. [Monitoring and Logging](#monitoring-and-logging)
7. [Security Hardening](#security-hardening)
8. [Troubleshooting](#troubleshooting)

## Prerequisites

### Required Software

- Docker 20.10+
- Docker Compose 2.0+
- Git
- Node.js 18+
- PHP 8.2+
- Composer 2.0+
- MySQL 8.0+
- Redis 7+

### Required Accounts

- AWS Account (for S3, CloudFront CDN)
- GCash Merchant Account
- Maya Merchant Account
- Email Service Provider (SMTP)
- SMS Service Provider (Semaphore/Itexmo)

## Environment Configuration

### Backend Configuration

1. **Development Environment**
   ```bash
   cp backend/.env.example backend/.env
   ```

2. **Staging Environment**
   ```bash
   cp backend/.env.production backend/.env.staging
   ```
   Update the following variables:
   - `APP_ENV=staging`
   - `APP_URL=https://staging.diecastempire.com`
   - Database credentials
   - Redis credentials
   - Payment gateway sandbox credentials

3. **Production Environment**
   ```bash
   cp backend/.env.production backend/.env
   ```
   Update all production credentials and API keys.

### Frontend Configuration

1. **Development Environment**
   ```bash
   cp frontend/.env.example frontend/.env
   ```

2. **Production Environment**
   ```bash
   cp frontend/.env.production frontend/.env
   ```
   Update:
   - `REACT_APP_API_URL=https://api.diecastempire.com/api`
   - Analytics IDs
   - Sentry DSN

## Staging Deployment

### Automated Deployment

Run the staging deployment script:

```bash
chmod +x scripts/deploy-staging.sh
./scripts/deploy-staging.sh
```

### Manual Deployment

1. **Pull Latest Code**
   ```bash
   git checkout staging
   git pull origin staging
   ```

2. **Build Docker Images**
   ```bash
   docker-compose -f docker-compose.staging.yml build
   ```

3. **Start Services**
   ```bash
   docker-compose -f docker-compose.staging.yml up -d
   ```

4. **Run Migrations**
   ```bash
   docker-compose -f docker-compose.staging.yml exec app php artisan migrate --force
   ```

5. **Optimize Application**
   ```bash
   docker-compose -f docker-compose.staging.yml exec app php artisan optimize
   ```

6. **Validate Deployment**
   ```bash
   chmod +x scripts/validate-system.sh
   ./scripts/validate-system.sh
   ```

### Staging Environment URLs

- Backend API: http://localhost:8081/api
- Frontend: http://localhost:3001
- MySQL: localhost:3307
- Redis: localhost:6380

## Production Deployment

### Pre-Deployment Checklist

- [ ] All tests passing in staging
- [ ] Security audit completed
- [ ] Performance benchmarks met
- [ ] Database backup created
- [ ] Rollback plan prepared
- [ ] Team notified of deployment window

### Deployment Steps

1. **Create Database Backup**
   ```bash
   docker exec diecast_mysql_primary mysqldump -u root -p diecast_empire_db > backup_$(date +%Y%m%d_%H%M%S).sql
   ```

2. **Deploy to Production**
   ```bash
   git checkout main
   git pull origin main
   docker-compose -f docker-compose.production.yml build
   docker-compose -f docker-compose.production.yml up -d
   ```

3. **Run Migrations**
   ```bash
   docker-compose -f docker-compose.production.yml exec app php artisan migrate --force
   ```

4. **Clear and Warm Caches**
   ```bash
   docker-compose -f docker-compose.production.yml exec app php artisan cache:clear
   docker-compose -f docker-compose.production.yml exec app php artisan config:cache
   docker-compose -f docker-compose.production.yml exec app php artisan route:cache
   docker-compose -f docker-compose.production.yml exec app php artisan view:cache
   ```

5. **Run Smoke Tests**
   ```bash
   curl https://api.diecastempire.com/api/health
   ```

### Post-Deployment

1. Monitor application logs
2. Check error tracking (Sentry)
3. Verify payment gateway connectivity
4. Test critical user journeys
5. Monitor performance metrics

## System Validation

### Automated Validation

Run the comprehensive validation script:

```bash
chmod +x scripts/validate-system.sh
./scripts/validate-system.sh
```

### Manual Validation Checklist

#### Infrastructure
- [ ] All Docker containers running
- [ ] MySQL primary and replicas healthy
- [ ] Redis cache operational
- [ ] Nginx serving requests

#### Backend API
- [ ] Health endpoint responding
- [ ] Products API working
- [ ] Authentication working
- [ ] Payment gateways connected

#### Frontend
- [ ] Application loading
- [ ] API connectivity working
- [ ] Static assets loading from CDN

#### Database
- [ ] All tables present
- [ ] Migrations up to date
- [ ] Read replicas syncing

#### Security
- [ ] Security headers present
- [ ] HTTPS enforced
- [ ] CORS configured correctly
- [ ] Rate limiting active

#### Performance
- [ ] API response time < 2 seconds
- [ ] Page load time < 3 seconds
- [ ] Cache hit rate > 80%

## Monitoring and Logging

### Application Monitoring

1. **Sentry Integration**
   - Error tracking and alerting
   - Performance monitoring
   - Release tracking

2. **Application Logs**
   ```bash
   # View all logs
   docker-compose logs -f
   
   # View specific service
   docker-compose logs -f app
   ```

3. **Performance Metrics**
   - Response time monitoring
   - Database query performance
   - Cache hit rates
   - Memory usage

### Health Checks

The application provides a health check endpoint:

```bash
curl http://localhost:8080/api/health
```

Response:
```json
{
  "status": "ok",
  "timestamp": "2024-01-15T10:30:00Z",
  "version": "1.0.0",
  "environment": "production"
}
```

### Log Locations

- Application logs: `backend/storage/logs/laravel.log`
- Nginx logs: `docker/nginx/logs/`
- MySQL logs: Docker volume `mysql_data`
- Redis logs: Docker volume `redis_data`

## Security Hardening

### Implemented Security Measures

1. **Security Headers**
   - X-Frame-Options: DENY
   - X-Content-Type-Options: nosniff
   - X-XSS-Protection: 1; mode=block
   - Strict-Transport-Security
   - Content-Security-Policy

2. **Authentication & Authorization**
   - JWT token-based authentication
   - Role-based access control (RBAC)
   - Password hashing with bcrypt
   - Session security with secure cookies

3. **Rate Limiting**
   - API: 60 requests/minute
   - Auth: 5 attempts/15 minutes
   - Payment: 10 requests/5 minutes

4. **Input Validation**
   - SQL injection prevention
   - XSS prevention
   - CSRF protection
   - File upload validation

5. **Payment Security**
   - PCI compliance
   - Webhook signature verification
   - Encrypted sensitive data
   - Fraud detection

### Security Configuration Files

- `backend/config/security.php` - Security settings
- `backend/config/monitoring.php` - Monitoring configuration
- `backend/app/Http/Middleware/SecurityHeaders.php` - Security headers middleware

## Troubleshooting

### Common Issues

#### 1. Database Connection Failed

**Symptoms:** Application cannot connect to MySQL

**Solution:**
```bash
# Check MySQL container
docker ps | grep mysql

# Check MySQL logs
docker logs diecast_mysql_primary

# Verify credentials in .env file
```

#### 2. Redis Connection Failed

**Symptoms:** Cache operations failing

**Solution:**
```bash
# Check Redis container
docker ps | grep redis

# Test Redis connection
docker exec diecast_redis redis-cli ping

# Clear Redis cache
docker exec diecast_redis redis-cli FLUSHALL
```

#### 3. High Response Times

**Symptoms:** API responses taking > 2 seconds

**Solution:**
```bash
# Check database query performance
docker-compose exec app php artisan db:monitor

# Clear and rebuild cache
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:cache

# Check slow query log
docker exec diecast_mysql_primary mysql -u root -p -e "SHOW VARIABLES LIKE 'slow_query_log';"
```

#### 4. Payment Gateway Errors

**Symptoms:** Payment processing failing

**Solution:**
- Verify API credentials in .env
- Check webhook URLs are accessible
- Verify webhook signatures
- Check payment gateway status page

#### 5. Frontend Not Loading

**Symptoms:** Blank page or 404 errors

**Solution:**
```bash
# Rebuild frontend
cd frontend
npm run build

# Check Nginx configuration
docker logs diecast_nginx

# Verify API URL in frontend .env
```

### Getting Help

- Check application logs: `docker-compose logs -f`
- Review error tracking: Sentry dashboard
- Check system status: `./scripts/validate-system.sh`
- Run test suite: `./scripts/run-all-tests.sh`

## Testing

### Run All Tests

```bash
chmod +x scripts/run-all-tests.sh
./scripts/run-all-tests.sh
```

### Run Specific Test Suites

```bash
# Unit tests
cd backend && php artisan test --testsuite=Unit

# Feature tests
cd backend && php artisan test --testsuite=Feature

# Property-based tests
cd backend && php artisan test --filter=Property

# Integration tests
cd backend && php artisan test --filter=Integration

# Frontend tests
cd frontend && npm test
```

### Property-Based Tests

All 6 correctness properties are validated:

1. **Property 1:** Product Filtering Accuracy
2. **Property 2:** Pre-order Payment Flow Integrity
3. **Property 3:** Loyalty Credits Ledger Accuracy
4. **Property 4:** Payment Gateway Transaction Integrity
5. **Property 5:** User Authentication Security
6. **Property 6:** Inventory Stock Consistency

## CI/CD Pipeline

The project uses GitHub Actions for continuous integration and deployment.

### Pipeline Stages

1. **Backend Tests** - Unit, feature, and property tests
2. **Frontend Tests** - Component and integration tests
3. **Security Scan** - Dependency vulnerability scanning
4. **Deploy Staging** - Automatic deployment to staging on `staging` branch
5. **Deploy Production** - Manual deployment to production on `main` branch

### Configuration

Pipeline configuration: `.github/workflows/ci-cd.yml`

## Performance Benchmarks

### Target Metrics

- API Response Time: < 2 seconds
- Page Load Time: < 3 seconds
- Database Query Time: < 100ms
- Cache Hit Rate: > 80%
- Concurrent Users: 500+

### Load Testing

Run performance tests:

```bash
cd backend
php artisan test --filter=PerformanceIntegrationTest
```

## Rollback Procedure

If deployment issues occur:

1. **Stop new deployment**
   ```bash
   docker-compose down
   ```

2. **Restore database backup**
   ```bash
   mysql -u root -p diecast_empire_db < backup_YYYYMMDD_HHMMSS.sql
   ```

3. **Checkout previous version**
   ```bash
   git checkout <previous-commit-hash>
   ```

4. **Redeploy**
   ```bash
   docker-compose up -d
   ```

5. **Verify rollback**
   ```bash
   ./scripts/validate-system.sh
   ```

## Support

For deployment support:
- Email: devops@diecastempire.com
- Slack: #deployment-support
- Documentation: https://docs.diecastempire.com
