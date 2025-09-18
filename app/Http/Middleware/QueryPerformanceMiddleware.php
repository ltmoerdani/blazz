<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * PHASE-3 Query Performance Monitoring Middleware
 * Monitor dan log slow queries untuk optimization
 */
class QueryPerformanceMiddleware
{
    // Threshold for slow query detection (milliseconds)
    const SLOW_QUERY_THRESHOLD = 100;
    
    // Maximum queries per request before warning
    const MAX_QUERIES_WARNING = 20;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Start query monitoring
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        // Enable query logging
        DB::enableQueryLog();
        
        // Process request
        $response = $next($request);
        
        // Analyze performance metrics
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $memoryUsage = $endMemory - $startMemory;
        
        // Get query log
        $queries = DB::getQueryLog();
        $queryCount = count($queries);
        
        // Analyze slow queries
        $slowQueries = $this->analyzeSlowQueries($queries);
        
        // Log performance metrics
        $this->logPerformanceMetrics([
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'execution_time' => $executionTime,
            'memory_usage' => $memoryUsage,
            'query_count' => $queryCount,
            'slow_queries_count' => count($slowQueries),
            'controller_action' => $this->getControllerAction($request),
        ]);
        
        // Log slow queries for optimization
        if (!empty($slowQueries)) {
            $this->logSlowQueries($slowQueries, $request);
        }
        
        // Warning untuk excessive queries (potential N+1)
        if ($queryCount > self::MAX_QUERIES_WARNING) {
            Log::warning('Excessive database queries detected', [
                'url' => $request->fullUrl(),
                'query_count' => $queryCount,
                'controller_action' => $this->getControllerAction($request),
                'queries' => $this->summarizeQueries($queries)
            ]);
        }
        
        // Add performance headers untuk debugging (only in development)
        if (app()->environment(['local', 'staging'])) {
            $response->headers->set('X-Query-Count', $queryCount);
            $response->headers->set('X-Execution-Time', round($executionTime, 2) . 'ms');
            $response->headers->set('X-Memory-Usage', $this->formatBytes($memoryUsage));
            $response->headers->set('X-Slow-Queries', count($slowQueries));
        }
        
        return $response;
    }

    /**
     * Analyze queries untuk slow performance
     */
    private function analyzeSlowQueries(array $queries): array
    {
        $slowQueries = [];
        
        foreach ($queries as $query) {
            $executionTime = $query['time'] ?? 0;
            
            if ($executionTime > self::SLOW_QUERY_THRESHOLD) {
                $slowQueries[] = [
                    'sql' => $query['query'],
                    'bindings' => $query['bindings'],
                    'time' => $executionTime,
                    'query_hash' => md5($this->normalizeQuery($query['query']))
                ];
            }
        }
        
        return $slowQueries;
    }

    /**
     * Log performance metrics ke database untuk analysis
     */
    private function logPerformanceMetrics(array $metrics): void
    {
        // Only log in production untuk performance analysis
        if (app()->environment('production')) {
            try {
                DB::table('query_performance_logs')->insert([
                    'query_hash' => md5($metrics['url'] . $metrics['method']),
                    'query_sql' => json_encode($metrics),
                    'execution_time' => $metrics['execution_time'] / 1000, // Convert to seconds
                    'rows_examined' => $metrics['query_count'],
                    'rows_sent' => 1, // Response count
                    'connection_name' => 'mysql',
                    'controller_action' => $metrics['controller_action'],
                    'executed_at' => now(),
                ]);
            } catch (\Exception $e) {
                // Silent fail to avoid performance impact
                Log::error('Failed to log performance metrics: ' . $e->getMessage());
            }
        }
    }

    /**
     * Log slow queries untuk manual optimization
     */
    private function logSlowQueries(array $slowQueries, Request $request): void
    {
        foreach ($slowQueries as $query) {
            Log::warning('Slow query detected', [
                'url' => $request->fullUrl(),
                'controller_action' => $this->getControllerAction($request),
                'execution_time' => $query['time'] . 'ms',
                'sql' => $query['sql'],
                'bindings' => $query['bindings'],
                'query_hash' => $query['query_hash']
            ]);
            
            // Store slow query untuk analysis
            if (app()->environment('production')) {
                try {
                    DB::table('query_performance_logs')->insert([
                        'query_hash' => $query['query_hash'],
                        'query_sql' => $query['sql'],
                        'execution_time' => $query['time'] / 1000,
                        'rows_examined' => 0, // Would need EXPLAIN to get this
                        'rows_sent' => 0,
                        'connection_name' => 'mysql',
                        'controller_action' => $this->getControllerAction($request),
                        'query_bindings' => json_encode($query['bindings']),
                        'executed_at' => now(),
                    ]);
                } catch (\Exception $e) {
                    // Silent fail
                }
            }
        }
    }

    /**
     * Get controller action for request
     */
    private function getControllerAction(Request $request): ?string
    {
        $route = $request->route();
        
        if ($route) {
            $action = $route->getAction();
            
            if (isset($action['controller'])) {
                return $action['controller'];
            }
            
            if (isset($action['uses'])) {
                return $action['uses'];
            }
        }
        
        return null;
    }

    /**
     * Normalize query untuk consistent hashing
     */
    private function normalizeQuery(string $query): string
    {
        // Remove extra whitespace
        $query = preg_replace('/\s+/', ' ', trim($query));
        
        // Replace parameter placeholders untuk consistent comparison
        $query = str_replace('?', '?', $query);
        
        return strtolower($query);
    }

    /**
     * Summarize queries untuk logging
     */
    private function summarizeQueries(array $queries): array
    {
        $summary = [];
        
        foreach ($queries as $query) {
            $table = $this->extractTableName($query['query']);
            $type = $this->getQueryType($query['query']);
            
            $key = $table . '_' . $type;
            $summary[$key] = ($summary[$key] ?? 0) + 1;
        }
        
        return $summary;
    }

    /**
     * Extract table name from query
     */
    private function extractTableName(string $query): string
    {
        // Simple extraction - could be improved
        if (preg_match('/(?:from|join|update|into)\s+`?(\w+)`?/i', $query, $matches)) {
            return $matches[1];
        }
        
        return 'unknown';
    }

    /**
     * Get query type (SELECT, INSERT, UPDATE, DELETE)
     */
    private function getQueryType(string $query): string
    {
        $query = strtoupper(trim($query));
        
        $queryTypes = [
            'SELECT' => 'SELECT',
            'INSERT' => 'INSERT',
            'UPDATE' => 'UPDATE',
            'DELETE' => 'DELETE'
        ];
        
        foreach ($queryTypes as $type => $return) {
            if (strpos($query, $type) === 0) {
                return $return;
            }
        }
        
        return 'OTHER';
    }

    /**
     * Format bytes untuk human readable display
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $pow = floor(log($bytes) / log(1024));
        
        return round($bytes / (1024 ** $pow), 2) . ' ' . $units[$pow];
    }
}
