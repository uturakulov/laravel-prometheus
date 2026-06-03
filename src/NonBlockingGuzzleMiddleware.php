<?php

declare(strict_types = 1);

namespace Uturakulov\LaravelPrometheus;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Prometheus\Histogram;

class NonBlockingGuzzleMiddleware
{
    /**
     * @var Histogram
     */
    private $histogram;

    private GuzzlePathNormalizer $pathNormalizer;

    /**
     * @var array
     */
    private static array $pendingMetrics = [];

    private static bool $terminationHandlerRegistered = false;

    public function __construct(Histogram $histogram, GuzzlePathNormalizer $pathNormalizer)
    {
        $this->histogram = $histogram;
        $this->pathNormalizer = $pathNormalizer;

        if (!self::$terminationHandlerRegistered) {
            $this->registerTerminationHandler();
            self::$terminationHandlerRegistered = true;
        }
    }

    /**
     * Middleware that calculates the duration of a guzzle request.
     * Metrics are processed after response in termination handler.
     *
     * @param callable $handler
     *
     * @return callable Returns a function that accepts the next handler.
     */
    public function __invoke(callable $handler): callable
    {
        return function (Request $request, array $options) use ($handler) {
            $start = microtime(true);
            return $handler($request, $options)->then(
                function (Response $response) use ($request, $start) {
                    $duration = microtime(true) - $start;
                    [$externalHost, $externalPath] = $this->pathNormalizer->resolve($request);

                    self::$pendingMetrics[] = [
                        'histogram' => $this->histogram,
                        'duration' => $duration,
                        'labels' => [
                            $request->getMethod(),
                            $externalHost,
                            $externalPath,
                            (string) $response->getStatusCode(),
                        ],
                        'timestamp' => microtime(true),
                    ];

                    return $response;
                }
            );
        };
    }

    /**
     * Register termination handler to process metrics after response.
     */
    private function registerTerminationHandler(): void
    {
        if (function_exists('app') && app()->bound('events')) {
            app()->terminating(function () {
                $this->processPendingMetrics();
            });
        } else {
            // Fallback для случаев когда Laravel app недоступен
            register_shutdown_function([$this, 'processPendingMetrics']);
        }
    }

    /**
     * Process all pending Guzzle metrics.
     */
    public function processPendingMetrics(): void
    {
        if (empty(self::$pendingMetrics)) {
            return;
        }

        try {
            foreach (self::$pendingMetrics as $metricData) {
                /** @var Histogram $histogram */
                $histogram = $metricData['histogram'];
                $histogram->observe($metricData['duration'], $metricData['labels']);
            }
        } catch (\Exception $e) {
            if (function_exists('logger')) {
                logger()->error('Failed to process Guzzle metrics', [
                    'error' => $e->getMessage(),
                    'metrics_count' => count(self::$pendingMetrics)
                ]);
            }
        } finally {
            // Очищаем массив
            self::$pendingMetrics = [];
        }
    }
}

