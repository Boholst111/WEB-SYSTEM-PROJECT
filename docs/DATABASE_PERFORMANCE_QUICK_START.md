# Database Performance Optimization - Quick Start Guide

## Overview

This guide provides quick commands and configurations for database performance optimization.

## Quick Commands

### Monitor Database Performance
```bash
# Health check
php artisan db:monitor --health

# Full metrics
php artisan db:monitor

# Continuous monitoring (60s intervals)
php artisan db:monitor --continuous
```

### Optimize Database
```bash
# Optimize key tables
php artisan db:optimize

# Optimize all tables
php artisan db:optimize --all

# Suggest missing indexes
php artisan db:optimize --suggest-indexes
```

## Environment Configuration

Add to `.env`:
```env
# Database Write Connection
DB_WRITE_HOST=mysql

# Database Read Connections (Replicas)
DB_READ_HOST_1=mysql_replica_1
DB_READ_HOST_2=mysql_replica_2
DB_READ_HOST_3=mysql

# Connection Pool
DB_POOL_MIN=2
DB_POOL_MAX=10
```

## Docker Setup

```bash
# Start all services with replicas
docker-compose up -d

# Check replica status
docker exec -it diecast_mysql_replica_1 mysql -uroot -proot_password -e "SHOW SLAVE STATUS\G"
```

## Performance Metrics

- Connection usage < 75%
- Replication lag < 10 seconds
- Buffer pool hit ratio > 95%
- Slow queries < 1000

For detailed documentation, see `DATABASE_PERFORMANCE.md`
