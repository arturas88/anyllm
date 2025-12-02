<?php

declare(strict_types=1);

namespace AnyLLM\Middleware;

use AnyLLM\Middleware\Context\RequestContext;
use AnyLLM\Middleware\Context\ResponseContext;

interface MiddlewareInterface
{
    /**
     * Handle the request before it's sent to the provider.
     *
     * @param RequestContext $context
     * @param callable $next The next middleware in the chain
     * @return ResponseContext
     */
    public function handle(RequestContext $context, callable $next): ResponseContext;
}
