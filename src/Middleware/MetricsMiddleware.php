<?php

declare(strict_types=1);

namespace AnyLLM\Middleware;

use AnyLLM\Middleware\Context\RequestContext;
use AnyLLM\Middleware\Context\ResponseContext;

final class MetricsMiddleware implements MiddlewareInterface
{
    /** @var array<string, mixed> */
    private array $metrics = [
        'total_requests' => 0,
        'failed_requests' => 0,
        'total_duration_ms' => 0.0,
        'total_tokens' => 0,
        'by_provider' => [],
        'by_method' => [],
    ];

    public function handle(RequestContext $context, callable $next): ResponseContext
    {
        $startTime = microtime(true);

        try {
            $responseContext = $next($context);

            $this->recordSuccess($context, $responseContext, $startTime);

            return $responseContext->withMetadata('metrics_recorded', true);
        } catch (\Throwable $e) {
            $this->recordFailure($context, $startTime);
            throw $e;
        }
    }

    private function recordSuccess(RequestContext $context, ResponseContext $responseContext, float $startTime): void
    {
        $duration = (microtime(true) - $startTime) * 1000;

        $this->metrics['total_requests']++;
        $this->metrics['total_duration_ms'] += $duration;

        // Track by provider
        if (!isset($this->metrics['by_provider']) || !is_array($this->metrics['by_provider'])) {
            $this->metrics['by_provider'] = [];
        }
        if (! isset($this->metrics['by_provider'][$context->provider])) {
            $this->metrics['by_provider'][$context->provider] = [
                'requests' => 0,
                'duration_ms' => 0.0,
                'tokens' => 0,
            ];
        }
        if (is_array($this->metrics['by_provider'][$context->provider])) {
            $this->metrics['by_provider'][$context->provider]['requests']++;
            $this->metrics['by_provider'][$context->provider]['duration_ms'] += $duration;
        }

        // Track by method
        if (!isset($this->metrics['by_method']) || !is_array($this->metrics['by_method'])) {
            $this->metrics['by_method'] = [];
        }
        if (! isset($this->metrics['by_method'][$context->method])) {
            $this->metrics['by_method'][$context->method] = [
                'requests' => 0,
                'duration_ms' => 0.0,
            ];
        }
        if (is_array($this->metrics['by_method'][$context->method])) {
            $this->metrics['by_method'][$context->method]['requests']++;
            $this->metrics['by_method'][$context->method]['duration_ms'] += $duration;
        }

        // Track tokens if available
        if ($responseContext->response instanceof \AnyLLM\Responses\Response && isset($responseContext->response->usage)) {
            $tokens = $responseContext->response->usage->totalTokens;
            $this->metrics['total_tokens'] += $tokens;
            if (isset($this->metrics['by_provider'][$context->provider])) {
                $this->metrics['by_provider'][$context->provider]['tokens'] += $tokens;
            }
        }
    }

    private function recordFailure(RequestContext $context, float $startTime): void
    {
        $duration = (microtime(true) - $startTime) * 1000;

        $this->metrics['total_requests']++;
        $this->metrics['failed_requests']++;
        $this->metrics['total_duration_ms'] += $duration;

        if (!isset($this->metrics['by_provider']) || !is_array($this->metrics['by_provider'])) {
            $this->metrics['by_provider'] = [];
        }
        if (! isset($this->metrics['by_provider'][$context->provider])) {
            $this->metrics['by_provider'][$context->provider] = [
                'requests' => 0,
                'duration_ms' => 0.0,
                'tokens' => 0,
            ];
        }
        if (is_array($this->metrics['by_provider'][$context->provider])) {
            $this->metrics['by_provider'][$context->provider]['requests']++;
            $this->metrics['by_provider'][$context->provider]['duration_ms'] += $duration;
        }
    }

    /**
     * Get collected metrics.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        $metrics = $this->metrics;

        // Calculate averages
        if ($metrics['total_requests'] > 0) {
            $metrics['avg_duration_ms'] = $metrics['total_duration_ms'] / $metrics['total_requests'];
            $metrics['success_rate'] = 1 - ($metrics['failed_requests'] / $metrics['total_requests']);
        } else {
            $metrics['avg_duration_ms'] = 0.0;
            $metrics['success_rate'] = 1.0;
        }

        return $metrics;
    }

    /**
     * Reset all metrics.
     */
    public function reset(): void
    {
        $this->metrics = [
            'total_requests' => 0,
            'failed_requests' => 0,
            'total_duration_ms' => 0.0,
            'total_tokens' => 0,
            'by_provider' => [],
            'by_method' => [],
        ];
    }
}
