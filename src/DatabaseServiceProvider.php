<?php

declare(strict_types = 1);

namespace Uturakulov\LaravelPrometheus;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;

class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     */
    public function boot() : void
    {
        DB::listen(function ($query) {
            $querySql = '[omitted]';
            $type = strtoupper(strtok((string)$query->sql, ' '));
            if (config('prometheus.collect_full_sql_query')) {
                $querySql = $this->cleanupSqlString((string)$query->sql);
            }
            
            $labels = [
                $querySql ?: '[omitted]',
                $type ?: 'UNKNOWN'
            ];

            // если включён флаг, ищем сервис
            if (config('prometheus.collect_sql_service_caller')) {
                $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 50);

                $serviceCaller = '[unknown]';
                foreach ($trace as $frame) {
                    if (!isset($frame['class'])) {
                        continue;
                    }

                    if (str_starts_with($frame['class'], 'App\\Services\\')) {
                        $serviceCaller = $frame['class'] . (isset($frame['function']) ? '::' . $frame['function'] : '');
                        break;
                    }
                }

                $labels[] = $serviceCaller;
            }

            $this->app->get('prometheus.sql.histogram')->observe($query->time, $labels);
        });
    }

    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register() : void
    {
        $this->app->singleton('prometheus.sql.histogram', function ($app) {
            $labelNames = [
                'query',
                'query_type',
            ];

            if (config('prometheus.collect_sql_service_caller')) {
                $labelNames[] = 'service_caller';
            }

            return $app['prometheus']->getOrRegisterHistogram(
                'sql_query_duration',
                'SQL query duration histogram',
                $labelNames,
                config('prometheus.sql_buckets') ?? null
            );
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides() : array
    {
        return [
            'prometheus.sql.histogram',
        ];
    }

    /**
     * Cleans the SQL string for registering the metric.
     * Removes repetitive question marks and simplifies "VALUES" clauses.
     *
     * @param string $sql
     * @return string
     */
    private function cleanupSqlString(string $sql): string
    {
        // 1. Replace all string literals (single or double quoted) with ?
        $sql = preg_replace("/'[^']*'/", "?", $sql);
        $sql = preg_replace('/"[^"]*"/', "?", $sql);

        // 2. Replace all numbers (integers, decimals, negative values) with ?
        $sql = preg_replace('/\b\d+(\.\d+)?\b/', "?", $sql);

        // 3. Normalize IN (...) lists to a single placeholder
        $sql = preg_replace('/IN\s*\([^)]+\)/i', "IN (?)", $sql);

        // 4. Normalize VALUES (...) lists to a single placeholder
        $sql = preg_replace('/(VALUES\s*)\([^)]+\)(\s*,\s*\([^)]+\))*/i', 'VALUES (?)', $sql);

        // 5. Collapse multiple placeholders (?, ?, ?) into a single ?
        $sql = preg_replace('/(\?\s*,\s*)+/', "?", $sql);

        // 6. Normalize whitespace
        $sql = preg_replace('/\s+/', ' ', trim($sql));

        // 7. Return normalized SQL or [error] if something went wrong
        return empty($sql) ? '[error]' : strtolower($sql);
    }
}
