<?php

require __DIR__ . '/../vendor/autoload.php';

use AnyLLM\Middleware\CachingMiddleware;
use AnyLLM\Middleware\Context\RequestContext;
use AnyLLM\Middleware\Context\ResponseContext;
use AnyLLM\Middleware\LoggingMiddleware;
use AnyLLM\Middleware\MetricsMiddleware;
use AnyLLM\Middleware\MiddlewareInterface;
use AnyLLM\Middleware\MiddlewarePipeline;
use AnyLLM\Middleware\RateLimitMiddleware;
use AnyLLM\Logging\Drivers\FileLogDriver;
use AnyLLM\Support\Cache\ArrayCache;
use AnyLLM\Support\RateLimit\MemoryRateLimiter;

echo "=== Middleware System Examples ===\n\n";

// =============================================
// Example 1: Basic Middleware
// =============================================
echo "=== 1. Basic Custom Middleware ===\n\n";

class TimingMiddleware implements MiddlewareInterface
{
    public function handle(RequestContext $context, callable $next): ResponseContext
    {
        echo "→ Before request\n";
        $start = microtime(true);
        
        $response = $next($context);
        
        $duration = (microtime(true) - $start) * 1000;
        echo "→ After request (took " . round($duration, 2) . "ms)\n";
        
        return $response->withMetadata('timing_ms', $duration);
    }
}

$pipeline = new MiddlewarePipeline([
    new TimingMiddleware(),
]);

$context = new RequestContext(
    provider: 'openai',
    model: 'gpt-4o',
    method: 'chat',
    params: ['messages' => [['role' => 'user', 'content' => 'Hello']]],
);

$result = $pipeline->execute($context, function($ctx) {
    // Simulate API call
    usleep(100000); // 100ms
    return new ResponseContext(
        request: $ctx,
        response: ['text' => 'Hi there!'],
    );
});

echo "Response: " . json_encode($result->response) . "\n";
echo "Metadata: " . json_encode($result->metadata) . "\n\n";

// =============================================
// Example 2: Caching Middleware
// =============================================
echo "=== 2. Caching Middleware ===\n\n";

$cache = new ArrayCache();
$cachingMiddleware = new CachingMiddleware($cache, ttl: 300);

$pipeline = new MiddlewarePipeline([$cachingMiddleware]);

echo "First request (cache miss):\n";
$result1 = $pipeline->execute($context, function($ctx) {
    echo "  → Making API call...\n";
    return new ResponseContext(
        request: $ctx,
        response: ['text' => 'Cached response'],
    );
});
echo "  Cached: " . ($result1->metadata['cached'] ? 'Yes' : 'No') . "\n\n";

echo "Second request (cache hit):\n";
$result2 = $pipeline->execute($context, function($ctx) {
    echo "  → Making API call...\n";
    return new ResponseContext(
        request: $ctx,
        response: ['text' => 'This shouldnt be called'],
    );
});
echo "  Cached: " . ($result2->metadata['cached'] ? 'Yes' : 'No') . "\n";
echo "  Response: " . json_encode($result2->response) . "\n\n";

// =============================================
// Example 3: Rate Limiting Middleware
// =============================================
echo "=== 3. Rate Limiting Middleware ===\n\n";

$rateLimiter = new MemoryRateLimiter();
$rateLimitMiddleware = new RateLimitMiddleware(
    rateLimiter: $rateLimiter,
    maxAttempts: 3,
    decaySeconds: 60
);

$pipeline = new MiddlewarePipeline([$rateLimitMiddleware]);

for ($i = 1; $i <= 5; $i++) {
    try {
        $result = $pipeline->execute($context, function($ctx) use ($i) {
            return new ResponseContext(
                request: $ctx,
                response: ['request' => $i],
            );
        });
        
        echo "Request {$i}: ✓ (Remaining: {$result->metadata['rate_limit_remaining']})\n";
    } catch (\AnyLLM\Exceptions\RateLimitException $e) {
        echo "Request {$i}: ✗ Rate limited!\n";
    }
}

echo "\n";

// =============================================
// Example 4: Metrics Middleware
// =============================================
echo "=== 4. Metrics Middleware ===\n\n";

$metricsMiddleware = new MetricsMiddleware();
$pipeline = new MiddlewarePipeline([$metricsMiddleware]);

// Make several requests
for ($i = 1; $i <= 5; $i++) {
    $ctx = new RequestContext(
        provider: $i % 2 === 0 ? 'openai' : 'anthropic',
        model: 'test-model',
        method: 'chat',
        params: [],
    );
    
    $pipeline->execute($ctx, function($ctx) {
        usleep(rand(50000, 150000)); // Random delay
        return new ResponseContext(
            request: $ctx,
            response: (object) ['usage' => (object) ['totalTokens' => rand(50, 200)]],
        );
    });
}

$metrics = $metricsMiddleware->getMetrics();

echo "Metrics:\n";
echo "- Total requests: {$metrics['total_requests']}\n";
echo "- Failed requests: {$metrics['failed_requests']}\n";
echo "- Success rate: " . number_format($metrics['success_rate'] * 100, 2) . "%\n";
echo "- Avg duration: " . round($metrics['avg_duration_ms'], 2) . "ms\n";
echo "- Total tokens: {$metrics['total_tokens']}\n\n";

echo "By provider:\n";
foreach ($metrics['by_provider'] as $provider => $stats) {
    echo "- {$provider}: {$stats['requests']} requests, {$stats['tokens']} tokens\n";
}

echo "\n";

// =============================================
// Example 5: Logging Middleware
// =============================================
echo "=== 5. Logging Middleware ===\n\n";

$logger = new FileLogDriver(__DIR__ . '/../storage/logs');
$loggingMiddleware = new LoggingMiddleware($logger);

$pipeline = new MiddlewarePipeline([$loggingMiddleware]);

$result = $pipeline->execute($context, function($ctx) {
    return new ResponseContext(
        request: $ctx,
        response: ['text' => 'Logged response'],
    );
});

echo "✓ Request logged to file\n\n";

// =============================================
// Example 6: Multiple Middleware (Chain)
// =============================================
echo "=== 6. Middleware Chain ===\n\n";

$pipeline = new MiddlewarePipeline([
    new TimingMiddleware(),
    new CachingMiddleware(new ArrayCache()),
    new MetricsMiddleware(),
]);

echo "Middleware count: {$pipeline->count()}\n\n";

echo "First request:\n";
$result1 = $pipeline->execute($context, function($ctx) {
    echo "  → Handler executed\n";
    usleep(50000);
    return new ResponseContext(
        request: $ctx,
        response: ['text' => 'Response'],
    );
});

echo "\nSecond request (cached):\n";
$result2 = $pipeline->execute($context, function($ctx) {
    echo "  → Handler executed (should not see this!)\n";
    return new ResponseContext(
        request: $ctx,
        response: ['text' => 'Different'],
    );
});

echo "\n";

// =============================================
// Example 7: Custom Transformation Middleware
// =============================================
echo "=== 7. Custom Transformation Middleware ===\n\n";

class UppercaseMiddleware implements MiddlewareInterface
{
    public function handle(RequestContext $context, callable $next): ResponseContext
    {
        // Transform request
        if (isset($context->params['messages'])) {
            foreach ($context->params['messages'] as &$message) {
                if (isset($message['content'])) {
                    $message['content'] = strtoupper($message['content']);
                }
            }
            $context = $context->withParams($context->params);
        }
        
        $response = $next($context);
        
        // Transform response
        if (is_array($response->response) && isset($response->response['text'])) {
            $response = $response->withResponse([
                'text' => strtoupper($response->response['text']),
                'transformed' => true,
            ]);
        }
        
        return $response;
    }
}

$pipeline = new MiddlewarePipeline([new UppercaseMiddleware()]);

$ctx = new RequestContext(
    provider: 'test',
    model: 'test',
    method: 'chat',
    params: ['messages' => [['role' => 'user', 'content' => 'hello']]],
);

$result = $pipeline->execute($ctx, function($ctx) {
    echo "Handler received: " . $ctx->params['messages'][0]['content'] . "\n";
    return new ResponseContext(
        request: $ctx,
        response: ['text' => 'goodbye'],
    );
});

echo "Response: " . $result->response['text'] . "\n\n";

// =============================================
// Example 8: Error Handling Middleware
// =============================================
echo "=== 8. Error Handling Middleware ===\n\n";

class ErrorHandlingMiddleware implements MiddlewareInterface
{
    public function handle(RequestContext $context, callable $next): ResponseContext
    {
        try {
            return $next($context);
        } catch (\Exception $e) {
            echo "→ Caught error: {$e->getMessage()}\n";
            echo "→ Returning fallback response\n";
            
            return new ResponseContext(
                request: $context,
                response: ['text' => 'Fallback response'],
                error: $e->getMessage(),
                metadata: ['error_handled' => true],
            );
        }
    }
}

$pipeline = new MiddlewarePipeline([new ErrorHandlingMiddleware()]);

$result = $pipeline->execute($context, function($ctx) {
    throw new \Exception("Simulated API error");
});

echo "Error handled: " . ($result->metadata['error_handled'] ? 'Yes' : 'No') . "\n";
echo "Response: " . json_encode($result->response) . "\n\n";

// =============================================
// Example 9: Conditional Middleware
// =============================================
echo "=== 9. Conditional Middleware ===\n\n";

class ConditionalMiddleware implements MiddlewareInterface
{
    public function __construct(
        private string $targetProvider,
    ) {}
    
    public function handle(RequestContext $context, callable $next): ResponseContext
    {
        if ($context->provider === $this->targetProvider) {
            echo "→ Middleware applied for {$this->targetProvider}\n";
            $context = $context->withMetadata('conditional', true);
        } else {
            echo "→ Middleware skipped for {$context->provider}\n";
        }
        
        return $next($context);
    }
}

$pipeline = new MiddlewarePipeline([
    new ConditionalMiddleware('openai'),
]);

$openaiCtx = new RequestContext('openai', 'gpt-4o', 'chat', []);
$anthropicCtx = new RequestContext('anthropic', 'claude-4', 'chat', []);

$pipeline->execute($openaiCtx, fn($ctx) => new ResponseContext($ctx, []));
$pipeline->execute($anthropicCtx, fn($ctx) => new ResponseContext($ctx, []));

echo "\n";

// =============================================
// Example 10: Production Stack
// =============================================
echo "=== 10. Production Middleware Stack ===\n\n";

$productionPipeline = new MiddlewarePipeline([
    new TimingMiddleware(),
    new ErrorHandlingMiddleware(),
    new RateLimitMiddleware(new MemoryRateLimiter(), maxAttempts: 10),
    new CachingMiddleware(new ArrayCache(), ttl: 3600),
    new MetricsMiddleware(),
    new LoggingMiddleware(new FileLogDriver(__DIR__ . '/../storage/logs')),
]);

echo "Production stack with {$productionPipeline->count()} middleware:\n";
foreach ($productionPipeline->getMiddleware() as $i => $middleware) {
    echo ($i + 1) . ". " . get_class($middleware) . "\n";
}

echo "\n=== All Middleware Examples Complete! ===\n\n";

echo "Summary:\n";
echo "- Middleware allows intercepting requests/responses\n";
echo "- Chain multiple middleware for powerful combinations\n";
echo "- Built-in middleware: Caching, Logging, RateLimit, Metrics\n";
echo "- Easy to create custom middleware\n";
echo "- Perfect for cross-cutting concerns\n\n";

echo "Common Use Cases:\n";
echo "- Caching responses\n";
echo "- Logging all requests\n";
echo "- Rate limiting\n";
echo "- Metrics collection\n";
echo "- Error handling\n";
echo "- Request/response transformation\n";
echo "- Authentication/authorization\n";
echo "- Retry logic\n";

