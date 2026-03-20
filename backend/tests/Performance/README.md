# Performance Tests

This directory contains performance tests for the Diecast Empire platform, validating **Requirement 1.2**: Handle 100-500 concurrent users during Drop Day events with sub-2-second load times.

## Test Suites

### 1. LoadTest.php
Tests system performance under concurrent user load:
- **Product catalog endpoint**: 100 concurrent requests
- **Product filtering**: 50 concurrent filtered queries
- **Product detail pages**: 100 concurrent detail views
- **Authentication**: 50 concurrent login attempts
- **Mixed workload**: 200 requests simulating realistic Drop Day traffic

**Success Criteria:**
- Average response time < 2000ms
- Success rate > 95%
- All endpoints remain responsive under load

### 2. DatabasePerformanceTest.php
Benchmarks database query performance:
- **Product catalog queries**: < 100ms average
- **Complex filtering queries**: < 150ms average
- **Product search queries**: < 200ms average
- **Order history queries**: < 100ms average
- **Loyalty ledger queries**: < 100ms average
- **Connection pool performance**: < 50ms per batch
- **Aggregate queries**: < 200ms for analytics

**Success Criteria:**
- All queries meet their specific time thresholds
- Database indexes are used effectively
- Connection pooling handles concurrent requests efficiently

## Running Performance Tests

### Run All Performance Tests
```bash
cd backend
php artisan test --testsuite=Performance
```

### Run Specific Test Suite
```bash
# Load tests only
php artisan test tests/Performance/LoadTest.php

# Database performance tests only
php artisan test tests/Performance/DatabasePerformanceTest.php
```

### Run with Verbose Output
```bash
php artisan test --testsuite=Performance --verbose
```

## Performance Benchmarks

### Target Metrics (Requirement 1.2)
- **Concurrent Users**: 100-500 users
- **Response Time**: < 2 seconds (2000ms)
- **Success Rate**: > 95%
- **Database Queries**: < 200ms for complex operations
- **Page Load Time**: < 2.5 seconds (LCP)

### Current Performance
Run the tests to see current performance metrics. Each test outputs timing information:
```
[Load Test] Product Catalog: 100 requests in 1250.45ms (avg: 12.50ms per request)
[DB Performance] Product Catalog Query: avg 45.23ms over 10 iterations
```

## Optimization Tips

### If Tests Fail
1. **Check Database Indexes**: Ensure all indexes from migration `2024_01_20_000001_add_performance_indexes.php` are applied
2. **Verify Redis Cache**: Ensure Redis is running and cache is enabled
3. **Check Connection Pool**: Verify database connection pool settings in `config/database.php`
4. **Review Query Performance**: Use `EXPLAIN` to analyze slow queries
5. **Monitor Resources**: Check CPU, memory, and disk I/O during tests

### Performance Tuning
- Enable query caching for frequently accessed data
- Use eager loading to reduce N+1 query problems
- Implement database read replicas for query distribution
- Configure Redis for session and cache storage
- Optimize database indexes based on query patterns

## Integration with CI/CD

Add performance tests to your CI/CD pipeline:
```yaml
# .github/workflows/performance.yml
- name: Run Performance Tests
  run: |
    cd backend
    php artisan test --testsuite=Performance
```

## Monitoring in Production

These tests establish baseline performance metrics. In production:
- Monitor actual response times with APM tools
- Set up alerts for response times > 2 seconds
- Track database query performance
- Monitor concurrent user capacity
- Analyze Drop Day traffic patterns

## Notes

- Tests use `RefreshDatabase` trait, so they run on a clean database each time
- Large datasets are seeded automatically for realistic testing
- Tests output performance metrics to console for easy monitoring
- All tests validate against Requirement 1.2 thresholds
