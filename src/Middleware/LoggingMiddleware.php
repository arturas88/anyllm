<?php

declare(strict_types=1);

namespace AnyLLM\Middleware;

use AnyLLM\Logging\Drivers\LogDriverInterface;
use AnyLLM\Logging\LogEntry;
use AnyLLM\Middleware\Context\RequestContext;
use AnyLLM\Middleware\Context\ResponseContext;
use AnyLLM\Responses\Response;

final class LoggingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private LogDriverInterface $logger,
    ) {}

    public function handle(RequestContext $context, callable $next): ResponseContext
    {
        $startTime = microtime(true);

        try {
            $responseContext = $next($context);

            $this->log($context, $responseContext, $startTime);

            return $responseContext;
        } catch (\Throwable $e) {
            $this->logError($context, $e, $startTime);
            throw $e;
        }
    }

    private function log(RequestContext $context, ResponseContext $responseContext, float $startTime): void
    {
        $duration = (microtime(true) - $startTime) * 1000;

        $tokens = 0;
        $cost = 0.0;

        if ($responseContext->response instanceof Response && isset($responseContext->response->usage)) {
            $tokens = $responseContext->response->usage->totalTokens;
            // Cost calculation would go here based on model pricing
        }

        $responseData = $responseContext->response instanceof Response
            ? ['type' => get_class($responseContext->response)]
            : (is_array($responseContext->response) ? $responseContext->response : []);

        $entry = new LogEntry(
            provider: $context->provider,
            model: $context->model,
            method: $context->method,
            request: $context->params,
            response: $responseData,
            error: $responseContext->error,
            durationMs: (int) $duration,
            tokensUsed: $tokens,
            cost: $cost,
            metadata: array_merge($context->metadata, $responseContext->metadata),
        );

        $this->logger->write($entry);
    }

    private function logError(RequestContext $context, \Throwable $e, float $startTime): void
    {
        $duration = (microtime(true) - $startTime) * 1000;

        $entry = new LogEntry(
            provider: $context->provider,
            model: $context->model,
            method: $context->method,
            request: $context->params,
            response: [],
            error: $e->getMessage(),
            durationMs: (int) $duration,
            tokensUsed: 0,
            cost: 0.0,
            metadata: $context->metadata,
        );

        $this->logger->write($entry);
    }
}
