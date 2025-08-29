<?php

declare(strict_types = 1);

namespace Uturakulov\LaravelPrometheus;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Illuminate\Support\ServiceProvider;

class NonBlockingGuzzleServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     */
    public function boot(): void
    {
        $this->app->extend(Client::class, function (Client $client, $app) {
            $histogram = $app['prometheus.guzzle.histogram'];
            $middleware = new NonBlockingGuzzleMiddleware($histogram);
            
            $config = $client->getConfig();
            $handler = $config['handler'] ?? HandlerStack::create();
            
            if ($handler instanceof HandlerStack) {
                $handler->push($middleware, 'prometheus_non_blocking');
            }
            
            return new Client(array_merge($config, ['handler' => $handler]));
        });
    }

    /**
     * Register bindings in the container.
     */
    public function register(): void
    {
        $this->app->singleton('prometheus.guzzle.histogram', function ($app) {
            return $app['prometheus']->getOrRegisterHistogram(
                'guzzle_request_duration_seconds',
                'Guzzle HTTP request duration in seconds',
                ['method', 'external_endpoint', 'status_code'],
                config('prometheus.guzzle_buckets') ?? null
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
            'prometheus.guzzle.histogram',
        ];
    }
}

