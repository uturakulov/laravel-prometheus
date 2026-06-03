<?php

declare(strict_types = 1);

namespace Uturakulov\LaravelPrometheus;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Prometheus\Histogram;

class GuzzleMiddleware
{
    public const HISTOGRAM_LABELS = ['method', 'external_host', 'external_path', 'status_code'];

    /**
     * @var Histogram
     */
    private $histogram;

    private GuzzlePathNormalizer $pathNormalizer;

    public function __construct(Histogram $histogram, GuzzlePathNormalizer $pathNormalizer)
    {
        $this->histogram = $histogram;
        $this->pathNormalizer = $pathNormalizer;
    }

    /**
     * Middleware that calculates the duration of a guzzle request.
     * After calculation it sends metrics to prometheus.
     *
     * @param callable $handler
     *
     * @return callable Returns a function that accepts the next handler.
     */
    public function __invoke(callable $handler) : callable
    {
        return function (Request $request, array $options) use ($handler) {
            $start = microtime(true);
            return $handler($request, $options)->then(
                function (Response $response) use ($request, $start) {
                    [$externalHost, $externalPath] = $this->pathNormalizer->resolve($request);

                    $this->histogram->observe(
                        microtime(true) - $start,
                        [
                            $request->getMethod(),
                            $externalHost,
                            $externalPath,
                            (string) $response->getStatusCode(),
                        ]
                    );
                    return $response;
                }
            );
        };
    }
}
