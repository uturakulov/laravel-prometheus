<?php

declare(strict_types = 1);

namespace Uturakulov\LaravelPrometheus;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\Adapter;

class NonBlockingPrometheusServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/prometheus.php' => $this->configPath('prometheus.php'),
        ]);
        $this->loadRoutes();
    }

    /**
     * Register bindings in the container.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/prometheus.php', 'prometheus');

        $this->app->singleton(PrometheusExporter::class, function ($app) {
            $adapter = $app['prometheus.storage_adapter'];
            $prometheus = new CollectorRegistry($adapter, true);
            $exporter = new PrometheusExporter(config('prometheus.namespace'), $prometheus);
            foreach (config('prometheus.collectors') as $collectorClass) {
                $collector = $this->app->make($collectorClass);
                $exporter->registerCollector($collector);
            }
            return $exporter;
        });
        $this->app->alias(PrometheusExporter::class, 'prometheus');

        $this->app->bind('prometheus.storage_adapter_factory', function () {
            return new StorageAdapterFactory();
        });

        $this->app->bind(Adapter::class, function ($app) {
            /* @var StorageAdapterFactory $factory */
            $factory = $app['prometheus.storage_adapter_factory'];
            $driver = config('prometheus.storage_adapter');
            $configs = config('prometheus.storage_adapters');
            $config = Arr::get($configs, $driver, []);

            return $factory->make($driver, $config);
        });
        $this->app->alias(Adapter::class, 'prometheus.storage_adapter');

        // Регистрируем глобальный обработчик завершения для очистки всех pending метрик
        $this->registerGlobalTerminationHandler();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            'prometheus',
            'prometheus.storage_adapter',
            'prometheus.storage_adapter_factory',
        ];
    }

    private function loadRoutes()
    {
        if (!config('prometheus.metrics_route_enabled')) {
            return;
        }

        $router = $this->app['router'];

        /** @var Route $route */
        $isLumen = mb_strpos($this->app->version(), 'Lumen') !== false;
        if ($isLumen) {
            $router->get(
                config('prometheus.metrics_route_path'),
                [
                    'as' => 'metrics',
                    'uses' => MetricsController::class . '@getMetrics',
                ]
            );
        } else {
            $router->get(
                config('prometheus.metrics_route_path'),
                MetricsController::class . '@getMetrics'
            )->name('metrics');
        }
    }

    /**
     * Register global termination handler to ensure all metrics are processed.
     */
    private function registerGlobalTerminationHandler(): void
    {
        $this->app->terminating(function () {
            // Обрабатываем все pending метрики из различных источников
            $this->processAllPendingMetrics();
        });
    }

    /**
     * Process all pending metrics from all sources.
     */
    private function processAllPendingMetrics(): void
    {
        try {
            // Обработка Guzzle метрик если они есть
            if (class_exists(NonBlockingGuzzleMiddleware::class)) {
                $guzzleMiddleware = new NonBlockingGuzzleMiddleware(
                    $this->app->make('prometheus.guzzle.histogram')
                );
                $guzzleMiddleware->processPendingMetrics();
            }

            // Здесь можно добавить обработку других типов pending метрик
            
        } catch (\Exception $e) {
            if (function_exists('logger')) {
                logger()->error('Failed to process pending Prometheus metrics', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }

    private function configPath($path): string
    {
        return $this->app->basePath() . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }
}

