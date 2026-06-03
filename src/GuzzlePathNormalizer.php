<?php

declare(strict_types=1);

namespace Uturakulov\LaravelPrometheus;

use GuzzleHttp\Psr7\Request;

class GuzzlePathNormalizer
{
    private const UUID_PATTERN = '/[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/i';

    private bool $enabled;

    /** @var array<int, array{host: string, pattern: string, template: string}> */
    private array $rules;

    private ?int $maxSegments;

    public function __construct(array $config = [])
    {
        $this->enabled = (bool) ($config['enabled'] ?? true);
        $this->rules = $config['rules'] ?? [];
        $maxSegments = $config['max_segments'] ?? null;
        $this->maxSegments = $maxSegments !== null && $maxSegments !== '' ? (int) $maxSegments : null;
    }

    /**
     * @return array{0: string, 1: string} [external_host, external_path]
     */
    public function resolve(Request $request): array
    {
        $host = $request->getUri()->getHost();

        if (!$this->enabled) {
            return [$host, ''];
        }

        $path = $this->normalizePath($host, $request->getUri()->getPath());

        return [$host, $path];
    }

    public function normalizePath(string $host, string $path): string
    {
        $path = $this->normalizeSlashes($path);

        if ($path === '/') {
            return $path;
        }

        foreach ($this->rules as $rule) {
            $ruleHost = $rule['host'] ?? '*';
            if ($ruleHost !== '*' && $ruleHost !== $host) {
                continue;
            }

            $pattern = $rule['pattern'] ?? '';
            $template = $rule['template'] ?? '';

            if ($pattern !== '' && $template !== '' && preg_match($pattern, $path) === 1) {
                return $this->normalizeSlashes($template);
            }
        }

        return $this->applyHeuristics($path);
    }

    private function applyHeuristics(string $path): string
    {
        $path = preg_replace(self::UUID_PATTERN, '{uuid}', $path) ?? $path;
        $path = preg_replace('#/\d+#', '/{id}', $path) ?? $path;
        $path = preg_replace('#/[0-9a-zA-Z_-]{20,}#', '/{token}', $path) ?? $path;
        $path = preg_replace('#/[a-z0-9]+(?:-[a-z0-9]+)+#i', '/{slug}', $path) ?? $path;

        if ($this->maxSegments !== null && $this->maxSegments > 0) {
            $path = $this->truncateSegments($path, $this->maxSegments);
        }

        return $this->normalizeSlashes($path);
    }

    private function truncateSegments(string $path, int $maxSegments): string
    {
        $segments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => $segment !== ''));

        if (count($segments) <= $maxSegments) {
            return $path;
        }

        $kept = array_slice($segments, 0, $maxSegments);

        return '/' . implode('/', $kept) . '/{truncated}';
    }

    private function normalizeSlashes(string $path): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }

        return '/' . trim($path, '/');
    }
}
