<?php

namespace Uturakulov\LaravelPrometheus;

use Closure;
use Illuminate\Contracts\Http\Terminable;
use Illuminate\Support\Facades\Route as RouteFacade;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class NonBlockingPrometheusLaravelRouteMiddleware implements Terminable
{
    private const NONE_ERROR = "NONE";
    private const BUCKETS = [
        0, 0.005, 0.01, 0.025, 0.05, 0.075, 0.1, 0.25, 0.5, 0.75,
        1, 1.5, 2, 2.5, 5, 7.5, 10, 20, 30, 40, 50, 60
    ];

    /**
     * @var float
     */
    private float $startTime;

    /**
     * @var \Illuminate\Routing\Route|null
     */
    private $cachedRoute = null;

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     *
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Только запоминаем время начала и кэшируем маршрут
        $this->startTime = microtime(true);
        $this->cachedRoute = $this->getMatchedRoute($request);

        // ✅ СРАЗУ ВОЗВРАЩАЕМ ОТВЕТ - никаких блокирующих операций!
        return $next($request);
    }

    /**
     * Perform any final actions for the request lifecycle.
     * Этот метод выполняется ПОСЛЕ отправки ответа клиенту!
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function terminate(Request $request, Response $response): void
    {
        try {
            // Вычисляем длительность
            $duration = microtime(true) - $this->startTime;

            /** @var PrometheusExporter $exporter */
            $exporter = app('prometheus');

            // Регистрируем метрики ПОСЛЕ отправки ответа
            $this->recordResponseTimeMetrics($request, $response, $duration, $exporter);
            $this->recordExecutionMetrics($request, $response, $duration, $exporter);

        } catch (\Exception $e) {
            // Логируем ошибки, но не прерываем выполнение
            \Log::error('Failed to record Prometheus metrics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Record response time metrics.
     */
    private function recordResponseTimeMetrics(Request $request, Response $response, float $duration, PrometheusExporter $exporter): void
    {
        $histogram = $exporter->getOrRegisterHistogram(
            'response_time_seconds',
            'It observes response time.',
            ['method', 'route', 'status_code'],
            config('prometheus.guzzle_buckets') ?? null
        );

        $histogram->observe(
            $duration,
            [
                $request->method(),
                $this->cachedRoute->uri(),
                $response->getStatusCode(),
            ]
        );
    }

    /**
     * Record execution metrics.
     */
    private function recordExecutionMetrics(Request $request, Response $response, float $duration, PrometheusExporter $exporter): void
    {
        $labels = $this->getLabels($request, $response);

        $executionCounter = $exporter->getOrRegisterNamelessCounter(
            'execution_count',
            'Counter of system execution',
            ['owner', 'domain', 'system', 'component', 'operation', 'error', 'error_class']
        );
        $executionCounter->inc($labels);

        $latencyHistogram = $exporter->getOrRegisterNamelessHistogram(
            'execution_latency_seconds',
            'Latency of system execution in seconds',
            ['owner', 'domain', 'system', 'component', 'operation', 'error', 'error_class'],
            self::BUCKETS
        );
        $latencyHistogram->observe($duration, $labels);
    }

    public function getMatchedRoute(Request $request)
    {
        if ($this->cachedRoute !== null) {
            return $this->cachedRoute;
        }

        $routeCollection = RouteFacade::getRoutes();
        return $routeCollection->match($request);
    }

    private function getLabels(Request $request, Response $response): array
    {
        return array_merge(
            $this->getConfigLabels(),
            $this->getComponentOperationLabels($request),
            $this->getErrorLabels($response)
        );
    }

    private function getConfigLabels(): array
    {
        return [
            'owner' => config('prometheus.standard_metrics.owner') ?: '',
            'domain' => config('prometheus.standard_metrics.domain') ?: '',
            'system' => config('prometheus.standard_metrics.system') ?: '',
        ];
    }

    private function getComponentOperationLabels(Request $request): array
    {
        // Используем кэшированный маршрут
        $route = $this->cachedRoute;
        $controllerAction = $route->getActionName();
        $component = class_basename(explode('@', $controllerAction)[0]);
        $operation = explode('@', $controllerAction)[1] ?? 'unknown';

        return [
            'component' => $component ?: 'unknown',
            'operation' => $operation
        ];
    }

    private function getErrorLabels(Response $response): array
    {
        $error = self::NONE_ERROR;
        $errorClass = self::NONE_ERROR;

        if ($response->isClientError() || $response->isServerError()) {
            $errorClass = (string)$response->getStatusCode();
            $error = Response::$statusTexts[$response->getStatusCode()] ?? 'Unknown error';
        }

        return [
            'error' => $error,
            'error_class' => $errorClass,
        ];
    }
}
