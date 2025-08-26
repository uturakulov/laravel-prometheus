<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Namespace
    |--------------------------------------------------------------------------
    |
    | The namespace to use as a prefix for all metrics.
    |
    | This will typically be the name of your project, eg: 'search'.
    |
    */

    'namespace' => env('PROMETHEUS_NAMESPACE', 'app'),

    /*
    |--------------------------------------------------------------------------
    | Metrics Route Enabled?
    |--------------------------------------------------------------------------
    |
    | If enabled, a /metrics route will be registered to export prometheus
    | metrics.
    |
    */

    'metrics_route_enabled' => env('PROMETHEUS_METRICS_ROUTE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Metrics Route Path
    |--------------------------------------------------------------------------
    |
    | The path at which prometheus metrics are exported.
    |
    | This is only applicable if metrics_route_enabled is set to true.
    |
    */

    'metrics_route_path' => env('PROMETHEUS_METRICS_ROUTE_PATH', 'metrics'),

    /*
    |--------------------------------------------------------------------------
    | Storage Adapter
    |--------------------------------------------------------------------------
    |
    | The storage adapter to use.
    |
    | Supported: "memory", "redis", "apc"
    |
    */

    'storage_adapter' => env('PROMETHEUS_STORAGE_ADAPTER', 'memory'),

    /*
    |--------------------------------------------------------------------------
    | Storage Adapters
    |--------------------------------------------------------------------------
    |
    | The storage adapter configs.
    |
    */

    'storage_adapters' => [

        'redis' => [
            'host' => env('PROMETHEUS_REDIS_HOST', 'localhost'),
            'port' => env('PROMETHEUS_REDIS_PORT', 6379),
            'database' => env('PROMETHEUS_REDIS_DATABASE', 0),
            'timeout' => env('PROMETHEUS_REDIS_TIMEOUT', 0.1),
            'read_timeout' => env('PROMETHEUS_REDIS_READ_TIMEOUT', 10),
            'persistent_connections' => env('PROMETHEUS_REDIS_PERSISTENT_CONNECTIONS', false),
            'prefix' => env('PROMETHEUS_REDIS_PREFIX', 'PROMETHEUS_'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Collect full SQL query
    |--------------------------------------------------------------------------
    |
    | Indicates whether we should collect the full SQL query or not.
    |
    */

    'collect_full_sql_query' => env('PROMETHEUS_COLLECT_FULL_SQL_QUERY', false),

    'collect_sql_service_caller' => env('PROMETHEUS_SQL_COLLECT_SERVICE_CALLER', false),

    /*
    |--------------------------------------------------------------------------
    | Async Processing
    |--------------------------------------------------------------------------
    |
    | Enable async processing of metrics to avoid blocking main request.
    | When enabled, metrics will be queued and processed in background.
    |
    */

    'async_enabled' => env('PROMETHEUS_ASYNC_ENABLED', false),
    'async_queue' => env('PROMETHEUS_ASYNC_QUEUE', 'default'),
    'async_connection' => env('PROMETHEUS_ASYNC_CONNECTION', null),
    'async_delay' => env('PROMETHEUS_ASYNC_DELAY', 0), // seconds
    'async_batch_size' => env('PROMETHEUS_ASYNC_BATCH_SIZE', 100),
    'async_timeout' => env('PROMETHEUS_ASYNC_TIMEOUT', 30), // seconds

    /*
    |--------------------------------------------------------------------------
    | Fallback Storage
    |--------------------------------------------------------------------------
    |
    | When async is enabled, use a fast local storage for temporary metrics
    | before they are processed by queue workers.
    |
    */

    'fallback_storage' => env('PROMETHEUS_FALLBACK_STORAGE', 'memory'), // memory, apc

    /*
    |--------------------------------------------------------------------------
    | Collectors
    |--------------------------------------------------------------------------
    |
    | The collectors specified here will be auto-registered in the exporter.
    |
    */

    'collectors' => [
        // \Your\ExporterClass::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Buckets config
    |--------------------------------------------------------------------------
    |
    | The buckets config specified here will be passed to the histogram generator
    | in the prometheus client. You can configure it as an array of time bounds.
    | Default value is null.
    |
    */

    'routes_buckets' => null,
    'sql_buckets' => null,
    'guzzle_buckets' => null,


    'standard_metrics' => [
        'owner' => env('PROMETHEUS_STANDARD_METRICS_OWNER'),
        'domain' => env('PROMETHEUS_STANDARD_METRICS_DOMAIN'),
        'system' => env('PROMETHEUS_STANDARD_METRICS_SYSTEM')
    ],
];
