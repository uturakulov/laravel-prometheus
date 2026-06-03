<?php

declare(strict_types=1);

namespace Uturakulov\LaravelPrometheus;

trait RegistersGuzzlePathNormalizer
{
    protected function registerGuzzlePathNormalizer(): void
    {
        if ($this->app->bound(GuzzlePathNormalizer::class)) {
            return;
        }

        $this->app->singleton(GuzzlePathNormalizer::class, function () {
            return new GuzzlePathNormalizer(config('prometheus.guzzle_path_normalization', []));
        });
    }
}
