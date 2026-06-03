<?php

declare(strict_types=1);

namespace Uturakulov\LaravelPrometheus\Tests\Unit;

use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Uturakulov\LaravelPrometheus\GuzzlePathNormalizer;

class GuzzlePathNormalizerTest extends TestCase
{
    public function test_normalizes_numeric_id_segments(): void
    {
        $normalizer = new GuzzlePathNormalizer(['enabled' => true]);

        $this->assertSame(
            '/v1/users/{id}',
            $normalizer->normalizePath('api.example.com', '/v1/users/42')
        );
    }

    public function test_normalizes_uuid_segments(): void
    {
        $normalizer = new GuzzlePathNormalizer(['enabled' => true]);

        $this->assertSame(
            '/v1/users/{uuid}',
            $normalizer->normalizePath(
                'api.example.com',
                '/v1/users/550e8400-e29b-41d4-a716-446655440000'
            )
        );
    }

    public function test_applies_config_rule_before_heuristics(): void
    {
        $normalizer = new GuzzlePathNormalizer([
            'enabled' => true,
            'rules' => [
                [
                    'host' => 'api.example.com',
                    'pattern' => '#^/custom/\d+$#',
                    'template' => '/custom/{id}',
                ],
            ],
        ]);

        $this->assertSame('/custom/{id}', $normalizer->normalizePath('api.example.com', '/custom/99'));
    }

    public function test_rule_host_wildcard_matches_any_host(): void
    {
        $normalizer = new GuzzlePathNormalizer([
            'enabled' => true,
            'rules' => [
                [
                    'host' => '*',
                    'pattern' => '#^/health$#',
                    'template' => '/health',
                ],
            ],
        ]);

        $this->assertSame('/health', $normalizer->normalizePath('other.example.com', '/health'));
    }

    public function test_disabled_normalization_returns_empty_path(): void
    {
        $normalizer = new GuzzlePathNormalizer(['enabled' => false]);
        $request = new Request('GET', 'https://api.example.com/v1/users/42');

        $this->assertSame(['api.example.com', ''], $normalizer->resolve($request));
    }

    public function test_resolve_returns_host_and_normalized_path(): void
    {
        $normalizer = new GuzzlePathNormalizer(['enabled' => true]);
        $request = new Request('GET', 'https://api.example.com/v1/users/42');

        $this->assertSame(['api.example.com', '/v1/users/{id}'], $normalizer->resolve($request));
    }

    public function test_truncates_deep_paths_when_max_segments_configured(): void
    {
        $normalizer = new GuzzlePathNormalizer([
            'enabled' => true,
            'max_segments' => 2,
        ]);

        $this->assertSame(
            '/v1/users/{truncated}',
            $normalizer->normalizePath('api.example.com', '/v1/users/42/orders/7/items/3')
        );
    }
}
