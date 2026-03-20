# Database Performance Optimization Guide

This document describes the database performance optimizations implemented for the Diecast Empire platform to handle 100-500 concurrent users during Drop Day events.

## Overview

The database performance optimization strategy includes:

1. **Optimized Indexes** - Composite indexes for complex filtering queries
2. **Read Replicas** - Query distribution across multiple database instances
3. **Connection Pooling** - Efficient database connection management
4. **Performance Monitoring** - Real-time metrics and health checks
5. **Query Optimization** - Tools for analyzing and optimizing queries

## 1. Optimized Indexes

### Composite Indexes for Complex Queries

The system includes optimized composite indexes for common query patterns:

#### Products Table
- `idx_scale_material_status` - Multi-filter queries (scale + material + status)
- `idx_chase_status_stock` - Chase variant filtering with availability
- `idx_preorder_status_arrival` - Pre-order filtering with date range
- `idx_status_price` - Price range filtering with status

#### Orders Table
- `idx_user_date_status` - User order history with date filtering
- `idx_status_payment_date` - Admin order management queries

#### Pre-orders Table
- `idx_payment_reminder` - Payment reminder queries
- `idx_arrival_notification` - Arrival notification queries

#### Loyalty Transactions Table
- `idx_user_type_date` - User transaction history with type filtering
- `idx_expiration_processing` - Expiration processing queries

### Running Migrations

To apply the performance indexes:

```bash
cd backend
php artisan migrate
```

## 2. Read Replicas

### Architecture

The system supports read/write splitting with multiple read replicas:

- **Primary Database** - Handles all write operations
- **Read Replica 1** - Distributes read queries
- **Read Replica 2** - Distributes read queries
- **Read Replica 3** - Fallback to primary for reads

### Configuration

Read replicas are configured in `config/database.php`:

```php
'mysql' => [
    'write' => [
        'host' => [env('DB_WRITE_HOST', '127.0.0.1')],
    ],
    'read' => [
        'host' => [
            env('DB_READ_HOST_1', '127.0.0.1'),
            env('DB_READ_HOST_2', '127.0.0.1'),
            env('DB_READ_HOST_3', '127.0.0.1'),
        ],
    ],
    'sticky' => true, // Reads after writes use write connection
],
```

### Environment Variables

Set these in your `.env` file:

```env
DB_WRITE_HOST=mysql
DB_READ_HOST_1=mysql_replica_1
DB_READ_HOST_2=mysql_replica_2
DB_READ_HOST_3=mysql
```

### Docker Setup

The `docker-compose.yml` includes three MySQL instances:

```bash
# Start all services including replicas
docker-compose up -d

# Check replica status
docker exec -it diecast_mysql_replica_1 mysql -uroot -proot_password -e "SHOW SLAVE STATUS\G"
```

### Setting Up Replication

To manually set up replication on a replica:

```bash
# Run the setup script on each replica
docker exec -it diecast_mysql_replica_1 /docker/mysql/scripts/setup-replica.sh
docker exec -it diecast_mysql_replica_2 /docker/mysql/scripts/setup-replica.sh
```

## 3. Connection Pooling

### Configuration

Connection pooling is configured in `config/database.php`:

```php
'options' => [
    PDO::ATTR_PERSISTENT => env('DB_PERSISTENT_CONNECTION', false),
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_STRINGIFY_FETCHES => false,
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
],

'pool' => [
    'min' => env('DB_POOL_MIN', 2),
    'max' => env('DB_POOL_MAX', 10),
],
```

### Environment Variables

```env
DB_POOL_MIN=2
DB_POOL_MAX=10
DB_PERSISTENT_CONNECTION=false
```

### MySQL Configuration

The MySQL server is configured with optimized connection settings in `docker/mysql/conf.d/performance.cnf`:

- `max_connections = 500` - Support for high concurrent users
- `thread_cache_size = 50` - Efficient thread reuse
- `wait_timeout = 600` - Connection timeout settings

## 4. Performance Monitoring

### Database Monitoring Service

The `DatabaseMonitoringService` provides real-time metrics:

```php
use App\Services\DatabaseMonitoringService;

$monitoring = new DatabaseMonitoringService();

// Get all performance metrics
$metrics = $monitoring->getPerformanceMetrics();

// Run health check
$health = $monitoring->healthCheck();

// Log metrics for monitoring
$monitoring->logPerformanceMetrics();
```

### Console Commands

#### Monitor Database Performance

```bash
# One-time metrics display
php artisan db:monitor

# Continuous monitoring (60 second intervals)
php artisan db:monitor --continuous

# Health check only
php artisan db:monitor --health

# JSON output
php artisan db:monitor --json
```

#### Optimize Database

```bash
# Optimize key tables
php artisan db:optimize

# Optimize specific table
php artisan db:optimize --table=products

# Optimize all tables
php artisan db:optimize --all

# Analyze table statistics
php artisan db:optimize --analyze

# Suggest missing indexes
php artisan db:optimize --suggest-indexes
```

### Metrics Available

- **Connection Statistics** - Active connections, usage percentage, aborted connects
- **Query Statistics** - Total queries, queries per second, slow queries
- **Replication Status** - Replication lag, IO/SQL thread status
- **Buffer Pool Statistics** - Hit ratio, page usage, dirty pages
- **Table Statistics** - Row counts, data size, index size, fragmentation

## 5. Query Optimization

### Query Optimization Service

The `QueryOptimizationService` provides tools for analyzing queries:

```php
use App\Services\QueryOptimizationService;

$optimizer = new QueryOptimizationService();

// Explain query execution plan
$analysis = $optimizer->explainQuery(
    'SELECT * FROM products WHERE scale = ? AND status = ?',
    ['1:64', 'active']
);

// Get slow queries
$slowQueries = $optimizer->getSlowQueries();

// Suggest indexes for a table
$suggestions = $optimizer->suggestIndexes('products');

// Optimize table
$result = $optimizer->optimizeTable('products');

// Analyze table statistics
$result = $optimizer->analyzeTable('products');
```

### Query Analysis

The service automatically detects:

- Full table scans
- Filesort operations
- Temporary table creation
- Large row examinations
- Missing indexes

## 6. MySQL Performance Configuration

### Buffer Pool Settings

```ini
innodb_buffer_pool_size = 1G
innodb_buffer_pool_instances = 4
innodb_log_buffer_size = 16M
```

### Query Cache Settings

```ini
query_cache_type = 1
query_cache_size = 64M
query_cache_limit = 2M
```

### InnoDB Performance

```ini
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT
innodb_io_capacity = 2000
innodb_io_capacity_max = 4000
```

### Slow Query Log

```ini
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow-query.log
long_query_time = 2
log_queries_not_using_indexes = 1
```

## 7. Performance Best Practices

### Application Level

1. **Use Eager Loading** - Avoid N+1 queries with `with()`
2. **Cache Frequently Accessed Data** - Use Redis for product catalogs
3. **Paginate Large Result Sets** - Limit query results
4. **Use Query Scopes** - Reusable query logic in models
5. **Index Foreign Keys** - All foreign keys should be indexed

### Database Level

1. **Regular Maintenance** - Run `OPTIMIZE TABLE` monthly
2. **Analyze Statistics** - Run `ANALYZE TABLE` after bulk operations
3. **Monitor Slow Queries** - Review slow query log weekly
4. **Check Replication Lag** - Keep lag under 10 seconds
5. **Monitor Buffer Pool Hit Ratio** - Keep above 95%

### Query Optimization

1. **Use Composite Indexes** - For multi-column WHERE clauses
2. **Avoid SELECT *** - Select only needed columns
3. **Use LIMIT** - Limit result sets when possible
4. **Optimize JOIN Order** - Smaller tables first
5. **Use Covering Indexes** - Include all query columns in index

## 8. Monitoring and Alerts

### Health Check Thresholds

- **Connection Usage** - Warning at 75%, Critical at 90%
- **Replication Lag** - Warning at 10s, Critical at 30s
- **Buffer Pool Hit Ratio** - Warning below 90%
- **Slow Queries** - Warning above 1000

### Scheduled Tasks

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Monitor database performance every 5 minutes
    $schedule->command('db:monitor --health')
        ->everyFiveMinutes()
        ->appendOutputTo(storage_path('logs/db-monitor.log'));
    
    // Optimize key tables daily
    $schedule->command('db:optimize')
        ->daily()
        ->at('02:00');
    
    // Analyze all tables weekly
    $schedule->command('db:optimize --all --analyze')
        ->weekly()
        ->sundays()
        ->at('03:00');
}
```

## 9. Troubleshooting

### High Connection Usage

```bash
# Check current connections
php artisan db:monitor

# Increase max_connections in performance.cnf
max_connections = 1000

# Restart MySQL
docker-compose restart mysql
```

### Replication Lag

```bash
# Check replication status
docker exec -it diecast_mysql_replica_1 mysql -uroot -proot_password -e "SHOW SLAVE STATUS\G"

# Reset replication if needed
docker exec -it diecast_mysql_replica_1 /docker/mysql/scripts/setup-replica.sh
```

### Slow Queries

```bash
# Identify slow queries
php artisan db:optimize --suggest-indexes

# Analyze specific query
# Use QueryOptimizationService::explainQuery()

# Add missing indexes
# Create migration with new indexes
```

### Low Buffer Pool Hit Ratio

```bash
# Check buffer pool stats
php artisan db:monitor

# Increase buffer pool size in performance.cnf
innodb_buffer_pool_size = 2G

# Restart MySQL
docker-compose restart mysql
```

## 10. Performance Testing

### Load Testing

Use Apache Bench or similar tools to test database performance:

```bash
# Test product listing endpoint
ab -n 1000 -c 100 http://localhost:8080/api/products

# Monitor during test
php artisan db:monitor --continuous
```

### Benchmarking

```bash
# Benchmark query performance
php artisan tinker

# Run test queries and measure time
$start = microtime(true);
Product::where('scale', '1:64')->where('status', 'active')->get();
$time = microtime(true) - $start;
echo "Query time: " . ($time * 1000) . "ms\n";
```

## 11. Production Deployment

### Pre-Deployment Checklist

- [ ] Run all migrations including performance indexes
- [ ] Configure read replicas in production environment
- [ ] Set up replication between primary and replicas
- [ ] Configure connection pooling settings
- [ ] Enable slow query logging
- [ ] Set up monitoring and alerting
- [ ] Test failover scenarios
- [ ] Document backup and recovery procedures

### Post-Deployment Monitoring

- Monitor connection usage during peak traffic
- Check replication lag regularly
- Review slow query log weekly
- Optimize tables monthly
- Analyze query patterns and add indexes as needed

## Support

For issues or questions about database performance:

1. Check the monitoring dashboard: `php artisan db:monitor`
2. Review slow query log: `/var/log/mysql/slow-query.log`
3. Run health check: `php artisan db:monitor --health`
4. Analyze specific queries: Use `QueryOptimizationService`
