<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Database Monitoring Service
 * 
 * Monitors database performance metrics including:
 * - Connection pool status
 * - Query performance
 * - Replication lag
 * - Slow queries
 * - Table statistics
 */
class DatabaseMonitoringService
{
    /**
     * Get current database performance metrics
     * 
     * @return array
     */
    public function getPerformanceMetrics(): array
    {
        return [
            'connections' => $this->getConnectionStats(),
            'queries' => $this->getQueryStats(),
            'replication' => $this->getReplicationStatus(),
            'slow_queries' => $this->getSlowQueryCount(),
            'buffer_pool' => $this->getBufferPoolStats(),
            'table_stats' => $this->getTableStatistics(),
        ];
    }

    /**
     * Get connection statistics
     * 
     * @return array
     */
    public function getConnectionStats(): array
    {
        try {
            $stats = DB::select("SHOW STATUS WHERE Variable_name IN (
                'Threads_connected',
                'Threads_running',
                'Max_used_connections',
                'Aborted_connects',
                'Connection_errors_max_connections'
            )");

            $result = [];
            foreach ($stats as $stat) {
                $result[$stat->Variable_name] = $stat->Value;
            }

            $maxConnections = DB::select("SHOW VARIABLES LIKE 'max_connections'");
            $result['Max_connections'] = $maxConnections[0]->Value ?? 0;
            
            // Calculate connection usage percentage
            $result['connection_usage_percent'] = $result['Max_connections'] > 0
                ? round(($result['Threads_connected'] / $result['Max_connections']) * 100, 2)
                : 0;

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to get connection stats: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get query statistics
     * 
     * @return array
     */
    public function getQueryStats(): array
    {
        try {
            $stats = DB::select("SHOW STATUS WHERE Variable_name IN (
                'Questions',
                'Queries',
                'Com_select',
                'Com_insert',
                'Com_update',
                'Com_delete',
                'Slow_queries',
                'Created_tmp_tables',
                'Created_tmp_disk_tables'
            )");

            $result = [];
            foreach ($stats as $stat) {
                $result[$stat->Variable_name] = $stat->Value;
            }

            // Calculate queries per second (cached for 60 seconds)
            $cacheKey = 'db_monitoring_qps_' . now()->format('YmdHi');
            $previousStats = Cache::get($cacheKey);
            
            if ($previousStats) {
                $timeDiff = now()->diffInSeconds($previousStats['timestamp']);
                if ($timeDiff > 0) {
                    $result['queries_per_second'] = round(
                        ($result['Queries'] - $previousStats['queries']) / $timeDiff,
                        2
                    );
                }
            }

            Cache::put($cacheKey, [
                'queries' => $result['Queries'],
                'timestamp' => now(),
            ], 120);

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to get query stats: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get replication status for read replicas
     * 
     * @return array
     */
    public function getReplicationStatus(): array
    {
        try {
            $status = DB::select("SHOW SLAVE STATUS");
            
            if (empty($status)) {
                return ['status' => 'not_configured'];
            }

            $slave = $status[0];
            
            return [
                'slave_io_running' => $slave->Slave_IO_Running ?? 'Unknown',
                'slave_sql_running' => $slave->Slave_SQL_Running ?? 'Unknown',
                'seconds_behind_master' => $slave->Seconds_Behind_Master ?? null,
                'last_error' => $slave->Last_Error ?? null,
                'master_host' => $slave->Master_Host ?? null,
                'master_port' => $slave->Master_Port ?? null,
                'is_healthy' => ($slave->Slave_IO_Running === 'Yes' && 
                               $slave->Slave_SQL_Running === 'Yes' &&
                               ($slave->Seconds_Behind_Master === null || $slave->Seconds_Behind_Master < 10)),
            ];
        } catch (\Exception $e) {
            // This is expected on primary database
            return ['status' => 'primary_or_not_configured'];
        }
    }

    /**
     * Get slow query count
     * 
     * @return int
     */
    public function getSlowQueryCount(): int
    {
        try {
            $result = DB::select("SHOW STATUS LIKE 'Slow_queries'");
            return (int) ($result[0]->Value ?? 0);
        } catch (\Exception $e) {
            Log::error('Failed to get slow query count: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get InnoDB buffer pool statistics
     * 
     * @return array
     */
    public function getBufferPoolStats(): array
    {
        try {
            $stats = DB::select("SHOW STATUS WHERE Variable_name IN (
                'Innodb_buffer_pool_pages_total',
                'Innodb_buffer_pool_pages_free',
                'Innodb_buffer_pool_pages_data',
                'Innodb_buffer_pool_pages_dirty',
                'Innodb_buffer_pool_read_requests',
                'Innodb_buffer_pool_reads',
                'Innodb_buffer_pool_wait_free'
            )");

            $result = [];
            foreach ($stats as $stat) {
                $result[$stat->Variable_name] = $stat->Value;
            }

            // Calculate buffer pool hit ratio
            if (isset($result['Innodb_buffer_pool_read_requests']) && 
                isset($result['Innodb_buffer_pool_reads'])) {
                $totalReads = $result['Innodb_buffer_pool_read_requests'];
                $diskReads = $result['Innodb_buffer_pool_reads'];
                
                if ($totalReads > 0) {
                    $result['buffer_pool_hit_ratio'] = round(
                        (($totalReads - $diskReads) / $totalReads) * 100,
                        2
                    );
                }
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to get buffer pool stats: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get table statistics for key tables
     * 
     * @return array
     */
    public function getTableStatistics(): array
    {
        try {
            $tables = ['products', 'orders', 'users', 'preorders', 'loyalty_transactions'];
            $stats = [];

            foreach ($tables as $table) {
                $result = DB::select("
                    SELECT 
                        table_name,
                        table_rows,
                        data_length,
                        index_length,
                        data_free
                    FROM information_schema.tables
                    WHERE table_schema = DATABASE()
                    AND table_name = ?
                ", [$table]);

                if (!empty($result)) {
                    $stats[$table] = [
                        'rows' => $result[0]->table_rows,
                        'data_size_mb' => round($result[0]->data_length / 1024 / 1024, 2),
                        'index_size_mb' => round($result[0]->index_length / 1024 / 1024, 2),
                        'fragmentation_mb' => round($result[0]->data_free / 1024 / 1024, 2),
                    ];
                }
            }

            return $stats;
        } catch (\Exception $e) {
            Log::error('Failed to get table statistics: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if database performance is healthy
     * 
     * @return array
     */
    public function healthCheck(): array
    {
        $metrics = $this->getPerformanceMetrics();
        $issues = [];
        $warnings = [];

        // Check connection usage
        if (isset($metrics['connections']['connection_usage_percent'])) {
            $usage = $metrics['connections']['connection_usage_percent'];
            if ($usage > 90) {
                $issues[] = "Connection pool usage is critical: {$usage}%";
            } elseif ($usage > 75) {
                $warnings[] = "Connection pool usage is high: {$usage}%";
            }
        }

        // Check replication lag
        if (isset($metrics['replication']['seconds_behind_master']) && 
            $metrics['replication']['seconds_behind_master'] !== null) {
            $lag = $metrics['replication']['seconds_behind_master'];
            if ($lag > 30) {
                $issues[] = "Replication lag is critical: {$lag} seconds";
            } elseif ($lag > 10) {
                $warnings[] = "Replication lag is high: {$lag} seconds";
            }
        }

        // Check buffer pool hit ratio
        if (isset($metrics['buffer_pool']['buffer_pool_hit_ratio'])) {
            $hitRatio = $metrics['buffer_pool']['buffer_pool_hit_ratio'];
            if ($hitRatio < 90) {
                $warnings[] = "Buffer pool hit ratio is low: {$hitRatio}%";
            }
        }

        // Check slow queries
        if (isset($metrics['queries']['Slow_queries'])) {
            $slowQueries = $metrics['queries']['Slow_queries'];
            if ($slowQueries > 1000) {
                $warnings[] = "High number of slow queries: {$slowQueries}";
            }
        }

        return [
            'healthy' => empty($issues),
            'status' => empty($issues) ? 'healthy' : 'unhealthy',
            'issues' => $issues,
            'warnings' => $warnings,
            'metrics' => $metrics,
        ];
    }

    /**
     * Log performance metrics for monitoring
     * 
     * @return void
     */
    public function logPerformanceMetrics(): void
    {
        $health = $this->healthCheck();
        
        if (!$health['healthy']) {
            Log::warning('Database performance issues detected', [
                'issues' => $health['issues'],
                'warnings' => $health['warnings'],
            ]);
        }

        // Store metrics in cache for dashboard display
        Cache::put('db_performance_metrics', $health['metrics'], 300); // 5 minutes
    }
}
