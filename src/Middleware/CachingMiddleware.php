<?php

declare(strict_types=1);

namespace AnyLLM\Middleware;

use AnyLLM\Middleware\Context\RequestContext;
use AnyLLM\Middleware\Context\ResponseContext;
use AnyLLM\Support\Cache\CacheInterface;

final class CachingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CacheInterface $cache,
        private int $ttl = 3600,
        private bool $enabled = true,
    ) {}

    public function handle(RequestContext $context, callable $next): ResponseContext
    {
        if (! $this->enabled || ! $this->shouldCache($context->method)) {
            return $next($context);
        }

        $cacheKey = $this->generateCacheKey($context);

        // Check cache
        if ($this->cache->has($cacheKey)) {
            $cached = $this->cache->get($cacheKey);
            
            return new ResponseContext(
                request: $context,
                response: $cached['response'],
                error: null,
                metadata: array_merge(
                    $context->metadata,
                    ['cached' => true, 'cache_key' => $cacheKey]
                ),
            );
        }

        // Execute request
        $responseContext = $next($context);

        // Cache successful responses
        if ($responseContext->isSuccessful()) {
            $this->cache->set($cacheKey, [
                'response' => $responseContext->response,
                'cached_at' => time(),
            ], $this->ttl);
        }

        return $responseContext->withMetadata('cached', false)
            ->withMetadata('cache_key', $cacheKey);
    }

    private function generateCacheKey(RequestContext $context): string
    {
        $data = [
            'provider' => $context->provider,
            'model' => $context->model,
            'method' => $context->method,
            'params' => $context->params,
        ];

        return 'llm:' . md5(json_encode($data));
    }

    private function shouldCache(string $method): bool
    {
        // Only cache deterministic methods
        return in_array($method, ['chat', 'generateText', 'embed'], true);
    }

    public function enable(): self
    {
        $this->enabled = true;
        return $this;
    }

    public function disable(): self
    {
        $this->enabled = false;
        return $this;
    }
}

