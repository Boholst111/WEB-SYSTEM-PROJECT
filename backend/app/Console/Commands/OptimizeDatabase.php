<?php

namespace App\Console\Commands;

use App\Services\QueryOptimizationService;
use Illuminate\Console\Command;

class OptimizeDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:optimize 
                            {--table= : Specific table to optimize}
                            {--all : Optimize all tables}
                            {--analyze : Analyze table statistics}
                            {--suggest-indexes : Suggest missing indexes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Optimize database tables and analyze performance';

    /**
     * Query optimization service
     *
     * @var QueryOptimizationService
     */
    protected $optimizationService;

    /**
     * Create a new command instance.
     *
     * @param QueryOptimizationService $optimizationService
     */
    public function __construct(QueryOptimizationService $optimizationService)
    {
        parent::__construct();
        $this->optimizationService = $optimizationService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if ($this->option('suggest-indexes')) {
            $this->suggestIndexes();
            return 0;
        }

        if ($this->option('analyze')) {
            $this->analyzeTables();
            return 0;
        }

        $this->optimizeTables();
        return 0;
    }

    /**
     * Optimize database tables
     *
     * @return void
     */
    protected function optimizeTables(): void
    {
        $tables = $this->getTablesToOptimize();

        if (empty($tables)) {
            $this->error('No tables to optimize');
            return;
        }

        $this->info('Optimizing database tables...');
        $this->newLine();

        $bar = $this->output->createProgressBar(count($tables));
        $bar->start();

        $results = [];

        foreach ($tables as $table) {
            $result = $this->optimizationService->optimizeTable($table);
            $results[] = [
                $table,
                $result['success'] ? '<fg=green>Success</>' : '<fg=red>Failed</>',
                $result['status'] ?? $result['error'] ?? 'Unknown',
            ];
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['Table', 'Status', 'Message'], $results);
    }

    /**
     * Analyze table statistics
     *
     * @return void
     */
    protected function analyzeTables(): void
    {
        $tables = $this->getTablesToOptimize();

        if (empty($tables)) {
            $this->error('No tables to analyze');
            return;
        }

        $this->info('Analyzing table statistics...');
        $this->newLine();

        $bar = $this->output->createProgressBar(count($tables));
        $bar->start();

        $results = [];

        foreach ($tables as $table) {
            $result = $this->optimizationService->analyzeTable($table);
            $results[] = [
                $table,
                $result['success'] ? '<fg=green>Success</>' : '<fg=red>Failed</>',
                $result['status'] ?? $result['error'] ?? 'Unknown',
            ];
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['Table', 'Status', 'Message'], $results);
    }

    /**
     * Suggest missing indexes
     *
     * @return void
     */
    protected function suggestIndexes(): void
    {
        $tables = $this->getTablesToOptimize();

        if (empty($tables)) {
            $this->error('No tables to analyze');
            return;
        }

        $this->info('Analyzing indexes and providing suggestions...');
        $this->newLine();

        foreach ($tables as $table) {
            $result = $this->optimizationService->suggestIndexes($table);
            
            $this->info("Table: $table");
            $this->line("  Current indexes: " . ($result['current_indexes'] ?? 0));
            
            if (!empty($result['suggestions'])) {
                $this->warn("  Suggestions:");
                foreach ($result['suggestions'] as $suggestion) {
                    $this->line("    • $suggestion");
                }
            } else {
                $this->line("  <fg=green>No suggestions - table is well indexed</>");
            }
            
            $this->newLine();
        }
    }

    /**
     * Get list of tables to optimize
     *
     * @return array
     */
    protected function getTablesToOptimize(): array
    {
        if ($this->option('table')) {
            return [$this->option('table')];
        }

        if ($this->option('all')) {
            return [
                'products',
                'orders',
                'order_items',
                'users',
                'preorders',
                'loyalty_transactions',
                'payments',
                'shopping_cart',
                'wishlists',
                'product_reviews',
                'inventory_movements',
                'categories',
                'brands',
            ];
        }

        // Default: optimize key tables
        return [
            'products',
            'orders',
            'preorders',
            'loyalty_transactions',
        ];
    }
}
