<?php

declare(strict_types=1);

namespace AnyLLM\Middleware;

use AnyLLM\Middleware\Context\RequestContext;
use AnyLLM\Middleware\Context\ResponseContext;

final class MiddlewarePipeline
{
    /** @var array<MiddlewareInterface> */
    private array $middleware = [];

    /**
     * @param array<MiddlewareInterface> $middleware
     */
    public function __construct(array $middleware = [])
    {
        $this->middleware = $middleware;
    }

    /**
     * Add middleware to the pipeline.
     */
    public function add(MiddlewareInterface $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Execute the middleware pipeline.
     */
    public function execute(RequestContext $context, callable $handler): ResponseContext
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            fn($next, $middleware) => fn($ctx) => $middleware->handle($ctx, $next),
            $handler
        );

        return $pipeline($context);
    }

    /**
     * Get all middleware in the pipeline.
     *
     * @return array<MiddlewareInterface>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Clear all middleware.
     */
    public function clear(): self
    {
        $this->middleware = [];
        return $this;
    }

    /**
     * Get the count of middleware.
     */
    public function count(): int
    {
        return count($this->middleware);
    }
}
