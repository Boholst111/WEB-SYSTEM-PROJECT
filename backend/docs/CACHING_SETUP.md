# Caching Infrastructure Setup Guide

## Quick Start

The Diecast Empire caching infrastructure is now fully configured and ready to use. This guide will help you get started.

## What's Included

### 1. Redis Cache Service
- **Location**: `app/Services/CacheService.php`
- **Purpose**: Centralized caching for products, categories, brands, and query results
- **Features**:
  - Product caching with automatic invalidation
  - Category and brand caching
  - User preference storage
  - Query result caching
  - Cache warming capabilities

### 2. CDN Service
- **Location**: `app/Services/CdnService.php`
- **Purpose**: Manage CDN URLs for static assets and images
- **Features**:
  - Asset URL generation
  - Image optimization parameters
  - Cache control headers
  - Automatic CDN/local switching

### 3. Response Cache Middleware
- **Location**: `app/Http/Middleware/CacheResponse.php`
- **Purpose**: Cache full HTTP responses for public endpoints
- **Features**:
  - Automatic GET request caching
  - Skips authenticated requests
  - Configurable TTL per route
  - Cache hit/miss tracking

### 4. Model Observers
- **Location**: `app/Observers/`
- **Purpose**: Automatic cache invalidation on data changes
- **Observers**:
  - ProductObserver
  - CategoryObserver
  - BrandObserver

### 5. Cache Management Command
- **Command**: `php artisan cache:warmup`
- **Purpose**: Pre-populate cache with frequently accessed data
- **Options**: `--force` to clear and refresh all caches

## Configuration

### 1. Environment Setup

Copy the following to your `.env` file:

```env
# Cache Configuration
CACHE_DRIVER=redis
CACHE_TTL=3600
PRODUCT_CACHE_TTL=7200
USER_SESSION_TTL=86400

# Redis Configuration
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_CLIENT=predis
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_SESSION_DB=2

# CDN Configuration (optional)
CDN_ENABLED=false
CDN_URL=
CDN_ASSETS_PATH=assets
CDN_IMAGES_PATH=images
CDN_IMAGE_OPTIMIZATION=true
CDN_IMAGE_QUALITY=85
```

### 2. Docker Setup

Redis is already configured in `docker-compose.yml`:

```bash
# Start Redis container
docker-compose up -d redis

# Verify Redis is running
docker-compose ps redis
```

### 3. Session Configuration

Update `config/session.php` to use Redis:

```php
'driver' => env('SESSION_DRIVER', 'redis'),
'store' => env('SESSION_STORE', 'sessions'),
```

## Usage Examples

### Basic Caching

```php
use App\Services\CacheService;

$cacheService = app(CacheService::class);

// Cache a product
$product = Product::find($id);
$cacheService->cacheProduct($id, $product->toArray());

// Retrieve from cache
$cachedProduct = $cacheService->getProduct($id);

// Invalidate cache
$cacheService->invalidateProduct($id);
```

### Route Caching

```php
// In routes/api.php
Route::get('/products', [ProductController::class, 'index'])
    ->middleware('cache.response:7200'); // Cache for 2 hours
```

### CDN Usage

```php
use App\Services\CdnService;

$cdnService = app(CdnService::class);

// Get optimized image URL
$imageUrl = $cdnService->getOptimizedImageUrl('products/car.jpg', [
    'width' => 800,
    'height' => 600,
]);
```

## Testing

Run the caching tests:

```bash
# All cache tests
php artisan test --filter=Cache

# Specific test suites
php artisan test --filter=CacheServiceTest
php artisan test --filter=CdnServiceTest
php artisan test --filter=CacheResponseTest
```

## Performance Optimization

### Before Drop Day Events

1. Warm up the cache:
```bash
php artisan cache:warmup --force
```

2. Verify Redis is running:
```bash
docker-compose ps redis
redis-cli ping
```

3. Monitor Redis memory:
```bash
redis-cli INFO memory
```

### During High Traffic

- Cache hit rates should be > 80%
- Redis memory usage should be stable
- Response times should be < 2 seconds

## Monitoring

### Check Cache Status

```php
$stats = $cacheService->getCacheStats();
// Returns: ['status' => 'operational', 'driver' => 'redis']
```

### Redis Commands

```bash
# Connect to Redis
docker exec -it diecast_redis redis-cli

# Check all keys
KEYS *

# Get cache value
GET diecast_empire_cache_product:1

# Check memory usage
INFO memory

# Monitor commands in real-time
MONITOR
```

## Troubleshooting

### Cache Not Working

1. Check Redis connection:
```bash
docker-compose ps redis
redis-cli ping
```

2. Clear config cache:
```bash
php artisan config:clear
php artisan cache:clear
```

3. Verify environment variables:
```bash
php artisan config:cache
```

### High Memory Usage

1. Check Redis memory:
```bash
redis-cli INFO memory
```

2. Reduce TTL values in `.env`

3. Clear old caches:
```bash
php artisan cache:clear
```

## Next Steps

1. **Enable CDN**: Configure CDN_ENABLED=true and set CDN_URL
2. **Add Response Caching**: Apply `cache.response` middleware to public routes
3. **Schedule Cache Warming**: Add to Laravel scheduler for periodic warming
4. **Monitor Performance**: Set up monitoring for cache hit rates
5. **Optimize TTL**: Adjust cache TTL values based on usage patterns

## Documentation

For detailed documentation, see:
- [CACHING.md](./CACHING.md) - Complete caching documentation
- [Cache Service Tests](../tests/Unit/Services/CacheServiceTest.php)
- [CDN Service Tests](../tests/Unit/Services/CdnServiceTest.php)

## Support

For issues or questions:
1. Check logs: `storage/logs/laravel.log`
2. Review Redis logs: `docker logs diecast_redis`
3. Run diagnostics: `php artisan cache:clear && php artisan config:cache`
