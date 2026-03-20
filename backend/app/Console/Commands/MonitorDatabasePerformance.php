<?php

namespace App\Console\Commands;

use App\Services\DatabaseMonitoringService;
use Illuminate\Console\Command;

class MonitorDatabasePerformance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:monitor 
                            {--continuous : Run continuously with 60 second intervals}
                            {--health : Show only health check results}
                            {--json : Output in JSON format}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor database performance metrics and health';

    /**
     * Database monitoring service
     *
     * @var DatabaseMonitoringService
     */
    protected $monitoringService;

    /**
     * Create a new command instance.
     *
     * @param DatabaseMonitoringService $monitoringService
     */
    public function __construct(DatabaseMonitoringService $monitoringService)
    {
        parent::__construct();
        $this->monitoringService = $monitoringService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if ($this->option('continuous')) {
            $this->info('Starting continuous database monitoring (Ctrl+C to stop)...');
            
            while (true) {
                $this->displayMetrics();
                sleep(60);
                $this->newLine();
            }
        } else {
            $this->displayMetrics();
        }

        return 0;
    }

    /**
     * Display performance metrics
     *
     * @return void
     */
    protected function displayMetrics(): void
    {
        if ($this->option('health')) {
            $this->displayHealthCheck();
            return;
        }

        $metrics = $this->monitoringService->getPerformanceMetrics();

        if ($this->option('json')) {
            $this->line(json_encode($metrics, JSON_PRETTY_PRINT));
            return;
        }

        $this->info('=== Database Performance Metrics ===');
        $this->newLine();

        // Connection Statistics
        if (!empty($metrics['connections'])) {
            $this->info('Connection Statistics:');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Active Connections', $metrics['connections']['Threads_connected'] ?? 'N/A'],
                    ['Running Threads', $metrics['connections']['Threads_running'] ?? 'N/A'],
                    ['Max Connections', $metrics['connections']['Max_connections'] ?? 'N/A'],
                    ['Connection Usage', ($metrics['connections']['connection_usage_percent'] ?? 0) . '%'],
                    ['Aborted Connects', $metrics['connections']['Aborted_connects'] ?? 'N/A'],
                ]
            );
            $this->newLine();
        }

        // Query Statistics
        if (!empty($metrics['queries'])) {
            $this->info('Query Statistics:');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Queries', number_format($metrics['queries']['Queries'] ?? 0)],
                    ['SELECT Queries', number_format($metrics['queries']['Com_select'] ?? 0)],
                    ['INSERT Queries', number_format($metrics['queries']['Com_insert'] ?? 0)],
                    ['UPDATE Queries', number_format($metrics['queries']['Com_update'] ?? 0)],
                    ['DELETE Queries', number_format($metrics['queries']['Com_delete'] ?? 0)],
                    ['Slow Queries', number_format($metrics['queries']['Slow_queries'] ?? 0)],
                    ['Queries/Second', $metrics['queries']['queries_per_second'] ?? 'N/A'],
                ]
            );
            $this->newLine();
        }

        // Replication Status
        if (!empty($metrics['replication']) && $metrics['replication']['status'] !== 'not_configured') {
            $this->info('Replication Status:');
            
            if ($metrics['replication']['status'] === 'primary_or_not_configured') {
                $this->line('  This is a primary database or replication is not configured');
            } else {
                $isHealthy = $metrics['replication']['is_healthy'] ?? false;
                $this->line('  Status: ' . ($isHealthy ? '<fg=green>Healthy</>' : '<fg=red>Unhealthy</>'));
                $this->line('  IO Thread: ' . ($metrics['replication']['slave_io_running'] ?? 'Unknown'));
                $this->line('  SQL Thread: ' . ($metrics['replication']['slave_sql_running'] ?? 'Unknown'));
                $this->line('  Replication Lag: ' . ($metrics['replication']['seconds_behind_master'] ?? 'N/A') . ' seconds');
                
                if (!empty($metrics['replication']['last_error'])) {
                    $this->error('  Last Error: ' . $metrics['replication']['last_error']);
                }
            }
            $this->newLine();
        }

        // Buffer Pool Statistics
        if (!empty($metrics['buffer_pool'])) {
            $this->info('InnoDB Buffer Pool:');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Pages', number_format($metrics['buffer_pool']['Innodb_buffer_pool_pages_total'] ?? 0)],
                    ['Free Pages', number_format($metrics['buffer_pool']['Innodb_buffer_pool_pages_free'] ?? 0)],
                    ['Data Pages', number_format($metrics['buffer_pool']['Innodb_buffer_pool_pages_data'] ?? 0)],
                    ['Dirty Pages', number_format($metrics['buffer_pool']['Innodb_buffer_pool_pages_dirty'] ?? 0)],
                    ['Hit Ratio', ($metrics['buffer_pool']['buffer_pool_hit_ratio'] ?? 'N/A') . '%'],
                ]
            );
            $this->newLine();
        }

        // Table Statistics
        if (!empty($metrics['table_stats'])) {
            $this->info('Table Statistics:');
            $tableData = [];
            foreach ($metrics['table_stats'] as $table => $stats) {
                $tableData[] = [
                    $table,
                    number_format($stats['rows']),
                    $stats['data_size_mb'] . ' MB',
                    $stats['index_size_mb'] . ' MB',
                    $stats['fragmentation_mb'] . ' MB',
                ];
            }
            $this->table(
                ['Table', 'Rows', 'Data Size', 'Index Size', 'Fragmentation'],
                $tableData
            );
        }
    }

    /**
     * Display health check results
     *
     * @return void
     */
    protected function displayHealthCheck(): void
    {
        $health = $this->monitoringService->healthCheck();

        if ($this->option('json')) {
            $this->line(json_encode($health, JSON_PRETTY_PRINT));
            return;
        }

        $this->info('=== Database Health Check ===');
        $this->newLine();

        if ($health['healthy']) {
            $this->info('✓ Database is healthy');
        } else {
            $this->error('✗ Database has issues');
        }

        if (!empty($health['issues'])) {
            $this->newLine();
            $this->error('Critical Issues:');
            foreach ($health['issues'] as $issue) {
                $this->line('  • ' . $issue);
            }
        }

        if (!empty($health['warnings'])) {
            $this->newLine();
            $this->warn('Warnings:');
            foreach ($health['warnings'] as $warning) {
                $this->line('  • ' . $warning);
            }
        }

        if (empty($health['issues']) && empty($health['warnings'])) {
            $this->newLine();
            $this->info('No issues or warnings detected');
        }
    }
}
