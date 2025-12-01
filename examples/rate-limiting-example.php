<?php

require __DIR__ . '/../vendor/autoload.php';

use AnyLLM\Support\RateLimit\MemoryRateLimiter;
use AnyLLM\Support\RateLimit\RateLimiterFactory;
use AnyLLM\Exceptions\RateLimitException;

echo "=== Rate Limiting Examples ===\n\n";

// =============================================
// Example 1: Basic Rate Limiting
// =============================================
echo "=== 1. Basic Rate Limiting ===\n\n";

$limiter = new MemoryRateLimiter();

// Allow 5 attempts per minute
$key = 'user:123';
$maxAttempts = 5;
$decaySeconds = 60;

for ($i = 1; $i <= 7; $i++) {
    try {
        $result = $limiter->attempt(
            key: $key,
            callback: function() use ($i) {
                return "Request #{$i} successful!";
            },
            maxAttempts: $maxAttempts,
            decaySeconds: $decaySeconds
        );
        
        $remaining = $limiter->remaining($key, $maxAttempts);
        echo "✓ {$result} (Remaining: {$remaining})\n";
        
    } catch (RateLimitException $e) {
        $availableIn = $limiter->availableIn($key);
        echo "✗ Rate limit exceeded! Try again in {$availableIn} seconds\n";
        break;
    }
}

echo "\n";

// =============================================
// Example 2: Manual Rate Limit Tracking
// =============================================
echo "=== 2. Manual Rate Limit Tracking ===\n\n";

$limiter = new MemoryRateLimiter();
$apiKey = 'api:key:abc123';

// Check before making request
if ($limiter->tooManyAttempts($apiKey, 10)) {
    echo "✗ Too many attempts for API key\n";
    echo "Available in: " . $limiter->availableIn($apiKey) . " seconds\n";
} else {
    // Make request
    echo "✓ Making API request...\n";
    $limiter->hit($apiKey, 60); // Track the attempt
    
    $remaining = $limiter->remaining($apiKey, 10);
    echo "Remaining requests: {$remaining}/10\n";
}

echo "\n";

// =============================================
// Example 3: Per-User Rate Limiting
// =============================================
echo "=== 3. Per-User Rate Limiting ===\n\n";

$limiter = new MemoryRateLimiter();

$users = ['user-1', 'user-2', 'user-3'];
$perUserLimit = 3;

foreach ($users as $userId) {
    echo "User: {$userId}\n";
    
    for ($i = 1; $i <= 4; $i++) {
        try {
            $limiter->attempt(
                key: "user:{$userId}",
                callback: fn() => "Success",
                maxAttempts: $perUserLimit,
                decaySeconds: 60
            );
            
            $remaining = $limiter->remaining("user:{$userId}", $perUserLimit);
            echo "  Request {$i}: ✓ ({$remaining} remaining)\n";
            
        } catch (RateLimitException $e) {
            echo "  Request {$i}: ✗ Rate limited\n";
        }
    }
    
    echo "\n";
}

// =============================================
// Example 4: Different Time Windows
// =============================================
echo "=== 4. Different Time Windows ===\n\n";

$limiter = new MemoryRateLimiter();

// Per-second limit
echo "Per-second limit (10/second):\n";
for ($i = 1; $i <= 12; $i++) {
    $limiter->hit('api:per-second', 1); // 1 second decay
    
    if ($limiter->tooManyAttempts('api:per-second', 10)) {
        echo "  Attempt {$i}: ✗ Rate limited\n";
    } else {
        echo "  Attempt {$i}: ✓\n";
    }
}

echo "\n";

// Per-hour limit
echo "Per-hour limit (1000/hour):\n";
for ($i = 1; $i <= 5; $i++) {
    $limiter->hit('api:per-hour', 3600); // 1 hour decay
    $remaining = $limiter->remaining('api:per-hour', 1000);
    echo "  Request {$i}: {$remaining}/1000 remaining\n";
}

echo "\n";

// =============================================
// Example 5: Clear and Reset
// =============================================
echo "=== 5. Clear and Reset ===\n\n";

$limiter = new MemoryRateLimiter();

// Hit the limit
$key = 'clear-test';
for ($i = 0; $i < 5; $i++) {
    $limiter->hit($key, 60);
}

echo "Attempts: 5/5 (rate limited)\n";
echo "Too many attempts: " . ($limiter->tooManyAttempts($key, 5) ? 'Yes' : 'No') . "\n";

// Clear this specific key
$limiter->clear($key);
echo "After clearing key:\n";
echo "Too many attempts: " . ($limiter->tooManyAttempts($key, 5) ? 'Yes' : 'No') . "\n";
echo "Remaining: " . $limiter->remaining($key, 5) . "/5\n";

echo "\n";

// =============================================
// Example 6: Redis Rate Limiter
// =============================================
echo "=== 6. Redis Rate Limiter ===\n\n";

try {
    $redisLimiter = RateLimiterFactory::create('redis', [
        'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
        'port' => getenv('REDIS_PORT') ?: 6379,
    ]);

    $key = 'redis:test';
    
    for ($i = 1; $i <= 5; $i++) {
        try {
            $redisLimiter->attempt(
                key: $key,
                callback: fn() => "Request #{$i}",
                maxAttempts: 3,
                decaySeconds: 60
            );
            
            $remaining = $redisLimiter->remaining($key, 3);
            echo "✓ Request {$i} (Remaining: {$remaining})\n";
            
        } catch (RateLimitException $e) {
            echo "✗ Rate limited: {$e->getMessage()}\n";
        }
    }

    // Clean up
    $redisLimiter->clear($key);
    echo "\n";

} catch (\Exception $e) {
    echo "Redis not available (expected if not configured): {$e->getMessage()}\n\n";
}

// =============================================
// Example 7: Database Rate Limiter
// =============================================
echo "=== 7. Database Rate Limiter ===\n\n";

try {
    $dbLimiter = RateLimiterFactory::create('database', [
        'driver' => 'mysql',
        'host' => getenv('DB_HOST') ?: 'localhost',
        'database' => getenv('DB_DATABASE') ?: 'anyllm',
        'username' => getenv('DB_USERNAME') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
    ]);

    $key = 'db:test';
    
    for ($i = 1; $i <= 5; $i++) {
        try {
            $dbLimiter->attempt(
                key: $key,
                callback: fn() => "Request #{$i}",
                maxAttempts: 3,
                decaySeconds: 60
            );
            
            $remaining = $dbLimiter->remaining($key, 3);
            echo "✓ Request {$i} (Remaining: {$remaining})\n";
            
        } catch (RateLimitException $e) {
            echo "✗ Rate limited: {$e->getMessage()}\n";
        }
    }

    // Clean up
    $dbLimiter->clear($key);
    echo "\n";

} catch (\Exception $e) {
    echo "Database not available (expected if not configured): {$e->getMessage()}\n\n";
}

// =============================================
// Example 8: Real-World API Rate Limiting
// =============================================
echo "=== 8. Real-World API Rate Limiting ===\n\n";

function makeAPIRequest(string $userId, string $endpoint, MemoryRateLimiter $limiter): void
{
    $key = "api:{$userId}:{$endpoint}";
    
    try {
        $result = $limiter->attempt(
            key: $key,
            callback: function() use ($endpoint) {
                // Simulate API call
                usleep(100000); // 100ms
                return "API response from {$endpoint}";
            },
            maxAttempts: 10, // 10 requests per minute
            decaySeconds: 60
        );
        
        $remaining = $limiter->remaining($key, 10);
        echo "✓ {$result} (Rate limit: {$remaining}/10)\n";
        
    } catch (RateLimitException $e) {
        $availableIn = $limiter->availableIn($key);
        echo "✗ Rate limit exceeded. Retry in {$availableIn}s\n";
    }
}

$limiter = new MemoryRateLimiter();

// Simulate multiple API calls
for ($i = 0; $i < 12; $i++) {
    makeAPIRequest('user-123', '/api/chat', $limiter);
}

echo "\n";

// =============================================
// Example 9: Multi-Tier Rate Limiting
// =============================================
echo "=== 9. Multi-Tier Rate Limiting ===\n\n";

$limiter = new MemoryRateLimiter();

// Define tiers
$tiers = [
    'free' => ['limit' => 3, 'window' => 60],
    'pro' => ['limit' => 10, 'window' => 60],
    'enterprise' => ['limit' => 100, 'window' => 60],
];

$users = [
    'user-free' => 'free',
    'user-pro' => 'pro',
    'user-enterprise' => 'enterprise',
];

foreach ($users as $userId => $tier) {
    $config = $tiers[$tier];
    echo "{$userId} (tier: {$tier}, limit: {$config['limit']}/{$config['window']}s):\n";
    
    $key = "tier:{$userId}";
    
    // Make 5 requests
    for ($i = 1; $i <= 5; $i++) {
        if ($limiter->tooManyAttempts($key, $config['limit'])) {
            echo "  Request {$i}: ✗ Rate limited\n";
        } else {
            $limiter->hit($key, $config['window']);
            $remaining = $limiter->remaining($key, $config['limit']);
            echo "  Request {$i}: ✓ ({$remaining} remaining)\n";
        }
    }
    
    echo "\n";
}

echo "=== Rate limiting examples completed! ===\n\n";

echo "Summary:\n";
echo "- Memory: Fast, loses data on restart\n";
echo "- Redis: Fast, persistent, distributed\n";
echo "- Database: Persistent, queryable, slower\n\n";

echo "Use cases:\n";
echo "- Per-user API rate limiting\n";
echo "- Cost control for LLM calls\n";
echo "- Prevent abuse\n";
echo "- Multi-tier subscription limits\n";

