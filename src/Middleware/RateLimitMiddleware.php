<?php

declare(strict_types=1);

namespace AnyLLM\Middleware;

use AnyLLM\Exceptions\RateLimitException;
use AnyLLM\Middleware\Context\RequestContext;
use AnyLLM\Middleware\Context\ResponseContext;
use AnyLLM\Support\RateLimit\RateLimiterInterface;

final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RateLimiterInterface $rateLimiter,
        private int $maxAttempts = 10,
        private int $decaySeconds = 60,
        private ?string $keyPrefix = null,
    ) {}

    public function handle(RequestContext $context, callable $next): ResponseContext
    {
        $key = $this->generateKey($context);

        if ($this->rateLimiter->tooManyAttempts($key, $this->maxAttempts)) {
            $availableIn = $this->rateLimiter->availableIn($key);
            
            throw new RateLimitException(
                "Rate limit exceeded for '{$context->provider}/{$context->model}'. Try again in {$availableIn}s",
                429
            );
        }

        $this->rateLimiter->hit($key, $this->decaySeconds);

        $responseContext = $next($context);

        $remaining = $this->rateLimiter->remaining($key, $this->maxAttempts);
        
        return $responseContext
            ->withMetadata('rate_limit_remaining', $remaining)
            ->withMetadata('rate_limit_max', $this->maxAttempts);
    }

    private function generateKey(RequestContext $context): string
    {
        $parts = [
            $this->keyPrefix ?? 'ratelimit',
            $context->provider,
            $context->model,
        ];

        // Add user ID if present in metadata
        if (isset($context->metadata['user_id'])) {
            $parts[] = $context->metadata['user_id'];
        }

        return implode(':', $parts);
    }

    public function withKeyPrefix(string $prefix): self
    {
        $new = clone $this;
        $new->keyPrefix = $prefix;
        return $new;
    }
}

