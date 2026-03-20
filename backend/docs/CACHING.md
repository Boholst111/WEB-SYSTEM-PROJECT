# Caching Infrastructure Documentation

## Overview

The Diecast Empire platform implements a comprehensive caching infrastructure using Redis to handle high-traffic Drop Day events with 100-500 concurrent users while maintaining sub-2-second load times.

## Architecture

### Cache Layers

1. **Application Cache (Redis)**: Frequently accessed product data, categories, brands
2. **Query Result Cache**: Database query results with automatic invalidation
3. **Session Cache**: User sessions and preferences
4. **Response Cache**: Full HTTP response caching for public endpoints
5. **CDN Cache**: Static assets and product images

### Redis Database Allocation

- **DB 0**: Default/general cache
- **DB 1**: Application cache (products, categories, brands)
- **DB 2**: Session storage

## Configuration

### Environment Variables

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

# CDN Configuration
CDN_ENABLED=false
CDN_URL=https://cdn.example.com
CDN_ASSETS_PATH=assets
CDN_IMAGES_PATH=images
CDN_IMAGE_OPTIMIZATION=true
CDN_IMAGE_QUALITY=85
```

## Cache Service Usage

### Caching Products

```php
use App\Services\CacheService;

$cacheService = app(CacheService::class);

// Cache a product
$cacheService->cacheProduct($productId, $productData);

// Retrieve cached product
$product = $cacheService->getProduct($productId);

// Invalidate product cache
$cacheService->invalidateProduct($productId);
```

### Caching Categories

```php
// Cache all categories
$cacheService->cacheAllCategories($categories);

// Get cached categories
$categories = $cacheService->getAllCategories();

// Invalidate category cache
$cacheService->invalidateCategory($categoryId);
```

### Caching User Preferences

```php
// Cache user preferences
$cacheService->cacheUserPreferences($userId, $preferences);

// Get cached preferences
$preferences = $cacheService->getUserPreferences($userId);
```

### Caching Query Results

```php
// Generate query hash
$queryHash = md5($query . serialize($bindings));

// Cache query result
$cacheService->cacheQueryResult($queryHash, $result, 3600);

// Get cached result
$result = $cacheService->getQueryResult($queryHash);
```

## Automatic Cache Invalidation

The system uses Laravel model observers to automatically invalidate caches when data changes:

- **ProductObserver**: Invalidates product cache on update/delete
- **CategoryObserver**: Invalidates category cache on update/delete
- **BrandObserver**: Invalidates brand cache on update/delete

These observers are registered in `AppServiceProvider`.

## Response Caching Middleware

Apply the `cache.response` middleware to routes that should cache full HTTP responses:

```php
// In routes/api.php
Route::get('/products', [ProductController::class, 'index'])
    ->middleware('cache.response:7200'); // Cache for 2 hours

Route::get('/categories', [CategoryController::class, 'index'])
    ->middleware('cache.response:14400'); // Cache for 4 hours
```

**Note**: The middleware automatically:
- Only caches GET requests
- Skips authenticated requests
- Only caches successful responses (2xx status codes)
- Adds `X-Cache: HIT` or `X-Cache: MISS` header

## CDN Integration

### CDN Service Usage

```php
use App\Services\CdnService;

$cdnService = app(CdnService::class);

// Get CDN URL for asset
$cssUrl = $cdnService->getAssetUrl('css/app.css');

// Get CDN URL for image
$imageUrl = $cdnService->getImageUrl('products/model-car.jpg');

// Get optimized image URL
$optimizedUrl = $cdnService->getOptimizedImageUrl('products/model-car.jpg', [
    'width' => 800,
    'height' => 600,
    'quality' => 85,
    'format' => 'webp',
]);
```

### CDN Configuration

1. Enable CDN in `.env`:
```env
CDN_ENABLED=true
CDN_URL=https://cdn.diecastempire.com
```

2. Configure cache control headers in `config/cdn.php`

3. Upload assets to CDN storage

## Cache Management Commands

### Warm Up Cache

Pre-populate cache with frequently accessed data:

```bash
php artisan cache:warmup
```

Force refresh all caches:

```bash
php artisan cache:warmup --force
```

### Clear Cache

Clear all application caches:

```bash
php artisan cache:clear
```

Clear specific cache store:

```bash
php artisan cache:clear --store=redis
```

## Performance Optimization Tips

### 1. Cache Frequently Accessed Data

- Product listings and details
- Categories and brands
- Filter options
- User preferences

### 2. Use Appropriate TTL Values

- **Static data** (categories, brands): 12-24 hours
- **Product data**: 2-4 hours
- **Query results**: 1 hour
- **User sessions**: 24 hours

### 3. Implement Cache Tags

For Redis stores that support tags, use tag-based invalidation:

```php
Cache::tags(['products', 'featured'])->put('key', $value, $ttl);
Cache::tags(['products'])->flush(); // Invalidate all product caches
```

### 4. Monitor Cache Performance

- Track cache hit/miss ratios
- Monitor Redis memory usage
- Set up alerts for cache failures
- Use Redis INFO command for statistics

### 5. Cache Warming Strategy

- Run `cache:warmup` after deployments
- Schedule periodic cache warming during low-traffic periods
- Warm cache before Drop Day events

## Drop Day Optimization

For high-traffic Drop Day events:

1. **Pre-warm cache** 1 hour before event:
```bash
php artisan cache:warmup --force
```

2. **Increase TTL** for product data temporarily:
```php
$cacheService->cacheProduct($id, $data, 14400); // 4 hours
```

3. **Enable response caching** for all public endpoints

4. **Monitor Redis** memory and connection pool

5. **Use CDN** for all static assets and images

## Troubleshooting

### Cache Not Working

1. Check Redis connection:
```bash
redis-cli ping
```

2. Verify environment variables:
```bash
php artisan config:cache
```

3. Check Redis logs:
```bash
docker logs diecast_redis
```

### High Memory Usage

1. Check Redis memory:
```bash
redis-cli INFO memory
```

2. Reduce TTL values for less critical data

3. Implement cache eviction policies in Redis config

### Cache Invalidation Issues

1. Verify observers are registered in `AppServiceProvider`

2. Check logs for invalidation events:
```bash
tail -f storage/logs/laravel.log | grep "cache invalidated"
```

3. Manually clear specific cache keys:
```php
Cache::forget('product:123');
```

## Best Practices

1. **Always set TTL**: Never use infinite cache TTL
2. **Use cache tags**: Group related cache entries
3. **Monitor performance**: Track cache hit rates
4. **Invalidate proactively**: Clear cache when data changes
5. **Test cache behavior**: Include cache tests in test suite
6. **Document cache keys**: Use consistent naming conventions
7. **Handle cache failures**: Implement fallback logic
8. **Warm critical caches**: Pre-populate before high traffic

## Security Considerations

1. **Never cache sensitive data**: User passwords, payment info
2. **Separate user sessions**: Use dedicated Redis DB
3. **Validate cached data**: Don't trust cache blindly
4. **Secure Redis**: Use password authentication in production
5. **Encrypt sensitive cache**: Use Laravel's encrypted cache store

## Monitoring and Metrics

### Key Metrics to Track

- Cache hit/miss ratio
- Average response time with/without cache
- Redis memory usage
- Cache invalidation frequency
- CDN bandwidth usage

### Monitoring Tools

- Laravel Telescope (development)
- Redis Commander (Redis GUI)
- New Relic / DataDog (production monitoring)
- Custom metrics in analytics dashboard

## Future Enhancements

1. Implement distributed caching with Redis Cluster
2. Add cache warming scheduler
3. Implement predictive cache pre-loading
4. Add cache analytics dashboard
5. Implement cache versioning for zero-downtime deployments
