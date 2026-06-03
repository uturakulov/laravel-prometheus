<?php

declare(strict_types=1);

namespace Uturakulov\LaravelPrometheus\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use Uturakulov\LaravelPrometheus\GuzzleMiddleware;
use Uturakulov\LaravelPrometheus\GuzzlePathNormalizer;

class GuzzleMiddlewareTest extends TestCase
{
    public function test_records_histogram_with_host_and_normalized_path_labels(): void
    {
        $registry = new CollectorRegistry(new InMemory(), false);
        $histogram = $registry->getOrRegisterHistogram(
            'app',
            'guzzle_response_duration',
            'test',
            GuzzleMiddleware::HISTOGRAM_LABELS
        );

        $middleware = new GuzzleMiddleware(
            $histogram,
            new GuzzlePathNormalizer(['enabled' => true])
        );

        $mock = new MockHandler([new Response(200, [], 'ok')]);
        $stack = HandlerStack::create($mock);
        $stack->push($middleware);

        $client = new Client(['handler' => $stack]);
        $client->get('https://api.example.com/v1/users/42');

        $samples = $registry->getMetricFamilySamples();
        $histogramSample = null;

        foreach ($samples as $sample) {
            if ($sample->getName() === 'app_guzzle_response_duration') {
                $histogramSample = $sample;
                break;
            }
        }

        $this->assertNotNull($histogramSample, 'Expected guzzle histogram metric family');

        $labelSets = [];
        foreach ($histogramSample->getSamples() as $sample) {
            $labelSets[] = implode(',', $sample->getLabelValues());
        }

        $matching = array_filter(
            $labelSets,
            static fn (string $labels): bool => str_contains($labels, 'GET,api.example.com,/v1/users/{id},200')
        );

        $this->assertNotEmpty(
            $matching,
            'Expected histogram sample with labels GET, api.example.com, /v1/users/{id}, 200. Got: ' . implode(' | ', $labelSets)
        );
    }
}
