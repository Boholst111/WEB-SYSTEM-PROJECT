<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Query Optimization Service
 * 
 * Provides utilities for query optimization and analysis
 */
class QueryOptimizationService
{
    /**
     * Analyze a query and return execution plan
     * 
     * @param string $query
     * @param array $bindings
     * @return array
     */
    public function explainQuery(string $query, array $bindings = []): array
    {
        try {
            // Replace placeholders with actual values for EXPLAIN
            $explainQuery = $this->prepareQueryForExplain($query, $bindings);
            
            $result = DB::select("EXPLAIN $explainQuery");
            
            return [
                'query' => $query,
                'execution_plan' => $result,
                'analysis' => $this->analyzeExecutionPlan($result),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to explain query: ' . $e->getMessage());
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze execution plan and provide recommendations
     * 
     * @param array $executionPlan
     * @return array
     */
    protected function analyzeExecutionPlan(array $executionPlan): array
    {
        $issues = [];
        $recommendations = [];

        foreach ($executionPlan as $row) {
            // Check for full table scans
            if ($row->type === 'ALL') {
                $issues[] = "Full table scan detected on table: {$row->table}";
                $recommendations[] = "Consider adding an index on the columns used in WHERE/JOIN clauses for table: {$row->table}";
            }

            // Check for filesort
            if (isset($row->Extra) && strpos($row->Extra, 'Using filesort') !== false) {
                $issues[] = "Filesort detected on table: {$row->table}";
                $recommendations[] = "Consider adding an index to avoid filesort on table: {$row->table}";
            }

            // Check for temporary tables
            if (isset($row->Extra) && strpos($row->Extra, 'Using temporary') !== false) {
                $issues[] = "Temporary table created for query on table: {$row->table}";
                $recommendations[] = "Consider optimizing the query to avoid temporary table creation";
            }

            // Check for large row examinations
            if (isset($row->rows) && $row->rows > 10000) {
                $issues[] = "Large number of rows examined: {$row->rows} on table: {$row->table}";
                $recommendations[] = "Consider adding more selective indexes or refining WHERE clauses";
            }
        }

        return [
            'issues' => $issues,
            'recommendations' => $recommendations,
            'is_optimized' => empty($issues),
        ];
    }

    /**
     * Prepare query for EXPLAIN by replacing placeholders
     * 
     * @param string $query
     * @param array $bindings
     * @return string
     */
    protected function prepareQueryForExplain(string $query, array $bindings): string
    {
        foreach ($bindings as $binding) {
            $value = is_string($binding) ? "'$binding'" : $binding;
            $query = preg_replace('/\?/', $value, $query, 1);
        }
        
        return $query;
    }

    /**
     * Get slow queries from slow query log
     * 
     * @param int $limit
     * @return array
     */
    public function getSlowQueries(int $limit = 10): array
    {
        try {
            // Check if slow query log is enabled
            $logStatus = DB::select("SHOW VARIABLES LIKE 'slow_query_log'");
            
            if (empty($logStatus) || $logStatus[0]->Value !== 'ON') {
                return [
                    'enabled' => false,
                    'message' => 'Slow query log is not enabled',
                ];
            }

            // Get slow query log file location
            $logFile = DB::select("SHOW VARIABLES LIKE 'slow_query_log_file'");
            
            return [
                'enabled' => true,
                'log_file' => $logFile[0]->Value ?? 'Unknown',
                'message' => 'Slow query log is enabled. Check the log file for details.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get slow queries: ' . $e->getMessage());
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Suggest indexes for a table based on query patterns
     * 
     * @param string $table
     * @return array
     */
    public function suggestIndexes(string $table): array
    {
        try {
            // Get current indexes
            $indexes = DB::select("SHOW INDEX FROM $table");
            
            // Get table statistics
            $stats = DB::select("
                SELECT 
                    COLUMN_NAME,
                    CARDINALITY,
                    SEQ_IN_INDEX
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                ORDER BY SEQ_IN_INDEX
            ", [$table]);

            // Analyze columns without indexes
            $columns = DB::select("
                SELECT COLUMN_NAME, DATA_TYPE
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
            ", [$table]);

            $indexedColumns = array_map(function($idx) {
                return $idx->Column_name;
            }, $indexes);

            $suggestions = [];
            
            foreach ($columns as $column) {
                if (!in_array($column->COLUMN_NAME, $indexedColumns)) {
                    // Suggest indexes for common query columns
                    if (in_array($column->COLUMN_NAME, ['status', 'created_at', 'updated_at', 'user_id'])) {
                        $suggestions[] = "Consider adding an index on column: {$column->COLUMN_NAME}";
                    }
                }
            }

            return [
                'table' => $table,
                'current_indexes' => count($indexes),
                'suggestions' => $suggestions,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to suggest indexes: ' . $e->getMessage());
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Optimize table (defragment and rebuild indexes)
     * 
     * @param string $table
     * @return array
     */
    public function optimizeTable(string $table): array
    {
        try {
            $result = DB::select("OPTIMIZE TABLE $table");
            
            return [
                'table' => $table,
                'status' => $result[0]->Msg_text ?? 'Unknown',
                'success' => true,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to optimize table: ' . $e->getMessage());
            return [
                'table' => $table,
                'error' => $e->getMessage(),
                'success' => false,
            ];
        }
    }

    /**
     * Analyze table statistics
     * 
     * @param string $table
     * @return array
     */
    public function analyzeTable(string $table): array
    {
        try {
            $result = DB::select("ANALYZE TABLE $table");
            
            return [
                'table' => $table,
                'status' => $result[0]->Msg_text ?? 'Unknown',
                'success' => true,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to analyze table: ' . $e->getMessage());
            return [
                'table' => $table,
                'error' => $e->getMessage(),
                'success' => false,
            ];
        }
    }
}
