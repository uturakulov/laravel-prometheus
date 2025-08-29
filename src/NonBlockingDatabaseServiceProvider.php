<?php

declare(strict_types = 1);

namespace Uturakulov\LaravelPrometheus;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;

class NonBlockingDatabaseServiceProvider extends ServiceProvider
{
    /**
     * @var array
     */
    protected array $pendingSqlMetrics = [];

    /**
     * Perform post-registration booting of services.
     */
    public function boot(): void
    {
        DB::listen(function ($query) {
            // Просто сохраняем данные в памяти, не записываем в Redis сразу
            $this->pendingSqlMetrics[] = [
                'sql' => $query->sql,
                'time' => $query->time,
                'timestamp' => microtime(true)
            ];
        });

        // Регистрируем обработчик завершения приложения
        $this->app->terminating(function () {
            $this->processPendingSqlMetrics();
        });
    }

    /**
     * Process pending SQL metrics after response is sent.
     */
    protected function processPendingSqlMetrics(): void
    {
        if (empty($this->pendingSqlMetrics)) {
            return;
        }

        try {
            $histogram = $this->app->get('prometheus.sql.histogram');
            
            foreach ($this->pendingSqlMetrics as $metricData) {
                $querySql = '[omitted]';
                $type = strtoupper(strtok((string)$metricData['sql'], ' '));
                
                if (config('prometheus.collect_full_sql_query')) {
                    $querySql = $this->cleanupSqlString((string)$metricData['sql']);
                }
                
                $labels = array_values(array_filter([
                    $querySql,
                    $type
                ]));
                
                $histogram->observe($metricData['time'] / 1000, $labels);
            }
            
        } catch (\Exception $e) {
            \Log::error('Failed to process SQL metrics', [
                'error' => $e->getMessage(),
                'metrics_count' => count($this->pendingSqlMetrics)
            ]);
        } finally {
            // Очищаем массив
            $this->pendingSqlMetrics = [];
        }
    }

    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton('prometheus.sql.histogram', function ($app) {
            return $app['prometheus']->getOrRegisterHistogram(
                'sql_query_duration',
                'SQL query duration histogram',
                array_values(array_filter([
                    'query',
                    'query_type'
                ])),
                config('prometheus.sql_buckets') ?? null
            );
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
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
