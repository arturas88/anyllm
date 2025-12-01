<?php

require __DIR__ . '/../vendor/autoload.php';

use AnyLLM\Support\Cache\ArrayCache;
use AnyLLM\Support\Cache\CacheFactory;
use AnyLLM\Support\FileCache;

echo "=== Cache Drivers Examples ===\n\n";

// =============================================
// Example 1: Array Cache (In-Memory)
// =============================================
echo "=== 1. Array Cache (In-Memory) ===\n\n";

$arrayCache = new ArrayCache(defaultTtl: 3600);

// Basic operations
$arrayCache->set('user:123', ['name' => 'John Doe', 'email' => 'john@example.com']);
echo "✓ Stored user in array cache\n";

$user = $arrayCache->get('user:123');
echo "Retrieved: " . json_encode($user) . "\n";

// Check existence
echo "Exists: " . ($arrayCache->has('user:123') ? 'Yes' : 'No') . "\n";

// Remember pattern
$expensive = $arrayCache->remember('expensive_calc', function() {
    echo "Computing expensive calculation...\n";
    return 42 * 1337;
}, 60);
echo "Result: {$expensive}\n";

// Second call uses cache
$cached = $arrayCache->remember('expensive_calc', function() {
    echo "This won't be called!\n";
    return 0;
}, 60);
echo "Cached result: {$cached}\n\n";

// =============================================
// Example 2: File Cache
// =============================================
echo "=== 2. File Cache ===\n\n";

$fileCache = new FileCache(
    cacheDir: __DIR__ . '/../storage/cache',
    defaultTtl: 3600
);

// Cache LLM responses
$llmResponse = [
    'text' => 'This is a cached LLM response',
    'tokens' => 150,
    'cost' => 0.0015,
];

$fileCache->set('llm:response:abc123', $llmResponse, 7200);
echo "✓ Cached LLM response to file\n";

$cached = $fileCache->get('llm:response:abc123');
echo "Retrieved: tokens={$cached['tokens']}, cost=\${$cached['cost']}\n";

// Multiple operations
$fileCache->setMultiple([
    'config:api_key' => 'sk-test123',
    'config:model' => 'gpt-4o',
    'config:temperature' => 0.7,
], 3600);
echo "✓ Cached multiple config values\n";

$configs = $fileCache->getMultiple(['config:api_key', 'config:model', 'config:temperature']);
echo "Configs: " . json_encode($configs) . "\n\n";

// Increment/Decrement
$fileCache->set('api:calls:today', 0);
$fileCache->increment('api:calls:today');
$fileCache->increment('api:calls:today', 5);
$calls = $fileCache->get('api:calls:today');
echo "API calls today: {$calls}\n\n";

// =============================================
// Example 3: Redis Cache
// =============================================
echo "=== 3. Redis Cache ===\n\n";

try {
    $redisCache = CacheFactory::create('redis', [
        'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
        'port' => getenv('REDIS_PORT') ?: 6379,
        'default_ttl' => 3600,
    ]);

    // Store session data
    $redisCache->set('session:xyz789', [
        'user_id' => 'user-456',
        'logged_in_at' => time(),
        'ip' => '192.168.1.1',
    ], 1800); // 30 minutes

    echo "✓ Stored session in Redis\n";

    $session = $redisCache->get('session:xyz789');
    echo "Session user: {$session['user_id']}\n";

    // Atomic operations
    $redisCache->set('counter', 0);
    $redisCache->increment('counter', 10);
    echo "Counter: " . $redisCache->get('counter') . "\n";

    // Forever storage
    $redisCache->forever('app:version', '1.0.0');
    echo "✓ Stored app version forever\n\n";

} catch (\Exception $e) {
    echo "Redis not available (expected if not configured): {$e->getMessage()}\n\n";
}

// =============================================
// Example 4: Memcached Cache
// =============================================
echo "=== 4. Memcached Cache ===\n\n";

try {
    $memcachedCache = CacheFactory::create('memcached', [
        'servers' => [
            ['127.0.0.1', 11211],
        ],
        'default_ttl' => 3600,
    ]);

    // Cache database query results
    $queryResults = [
        ['id' => 1, 'name' => 'Product 1'],
        ['id' => 2, 'name' => 'Product 2'],
        ['id' => 3, 'name' => 'Product 3'],
    ];

    $memcachedCache->set('query:products:all', $queryResults, 600);
    echo "✓ Cached query results in Memcached\n";

    $cached = $memcachedCache->get('query:products:all');
    echo "Cached products: " . count($cached) . "\n";

    // Batch operations
    $memcachedCache->setMultiple([
        'stat:users' => 1000,
        'stat:posts' => 5000,
        'stat:comments' => 25000,
    ], 300);

    $stats = $memcachedCache->getMultiple(['stat:users', 'stat:posts', 'stat:comments']);
    echo "Stats: " . json_encode($stats) . "\n\n";

} catch (\Exception $e) {
    echo "Memcached not available (expected if not configured): {$e->getMessage()}\n\n";
}

// =============================================
// Example 5: Database Cache
// =============================================
echo "=== 5. Database Cache ===\n\n";

try {
    $dbCache = CacheFactory::create('database', [
        'driver' => 'mysql',
        'host' => getenv('DB_HOST') ?: 'localhost',
        'database' => getenv('DB_DATABASE') ?: 'anyllm',
        'username' => getenv('DB_USERNAME') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'default_ttl' => 3600,
    ]);

    // Cache API responses
    $apiResponse = [
        'status' => 'success',
        'data' => ['foo' => 'bar'],
        'timestamp' => time(),
    ];

    $dbCache->set('api:external:weather', $apiResponse, 1800);
    echo "✓ Cached API response in database\n";

    $cached = $dbCache->get('api:external:weather');
    echo "API status: {$cached['status']}\n";

    // Remember with database cache
    $heavyQuery = $dbCache->remember('report:monthly', function() {
        echo "Generating monthly report...\n";
        sleep(1); // Simulate heavy computation
        return ['total' => 15000, 'growth' => '15%'];
    }, 86400); // Cache for 1 day

    echo "Report: " . json_encode($heavyQuery) . "\n\n";

} catch (\Exception $e) {
    echo "Database cache not available (expected if DB not configured): {$e->getMessage()}\n\n";
}

// =============================================
// Example 6: Cache for LLM Responses
// =============================================
echo "=== 6. Caching LLM Responses ===\n\n";

$cache = new FileCache();

function getCachedLLMResponse(string $prompt, callable $llmCall, $cache): array
{
    $cacheKey = 'llm:' . md5($prompt);
    
    return $cache->remember($cacheKey, function() use ($llmCall, $prompt) {
        echo "Making LLM API call...\n";
        return $llmCall($prompt);
    }, 3600); // Cache for 1 hour
}

// Simulate LLM calls
$mockLLM = function($prompt) {
    return [
        'text' => "Response to: {$prompt}",
        'tokens' => 50,
        'cost' => 0.0025,
    ];
};

// First call - hits API
$response1 = getCachedLLMResponse('What is PHP?', $mockLLM, $cache);
echo "Response 1 (from API): {$response1['text']}\n";

// Second call - uses cache
$response2 = getCachedLLMResponse('What is PHP?', $mockLLM, $cache);
echo "Response 2 (from cache): {$response2['text']}\n";

echo "Cache saved " . ($response1['cost']) . " dollars on second call!\n\n";

// =============================================
// Example 7: Multi-Tier Caching
// =============================================
echo "=== 7. Multi-Tier Caching ===\n\n";

$l1Cache = new ArrayCache(defaultTtl: 60); // Fast, 1 minute
$l2Cache = new FileCache(defaultTtl: 3600); // Slower, 1 hour

function getTwoTierCached(string $key, callable $callback, $l1, $l2): mixed
{
    // Try L1 (fast)
    if ($l1->has($key)) {
        echo "✓ Hit L1 cache (memory)\n";
        return $l1->get($key);
    }
    
    // Try L2 (slower)
    if ($l2->has($key)) {
        echo "✓ Hit L2 cache (file)\n";
        $value = $l2->get($key);
        
        // Warm L1 cache
        $l1->set($key, $value, 60);
        
        return $value;
    }
    
    // Generate value
    echo "✗ Cache miss - generating value\n";
    $value = $callback();
    
    // Store in both tiers
    $l1->set($key, $value, 60);
    $l2->set($key, $value, 3600);
    
    return $value;
}

// First call - cache miss
$data1 = getTwoTierCached('expensive:data', function() {
    return ['computed' => true, 'value' => 42];
}, $l1Cache, $l2Cache);

// Second call - L1 hit
$data2 = getTwoTierCached('expensive:data', function() {
    return ['computed' => true, 'value' => 42];
}, $l1Cache, $l2Cache);

// Clear L1, third call - L2 hit
$l1Cache->clear();
$data3 = getTwoTierCached('expensive:data', function() {
    return ['computed' => true, 'value' => 42];
}, $l1Cache, $l2Cache);

echo "\n";

// =============================================
// Example 8: Cache Invalidation
// =============================================
echo "=== 8. Cache Invalidation ===\n\n";

$cache = new ArrayCache();

// Store related data with tags
$cache->set('user:123:profile', ['name' => 'John'], 600);
$cache->set('user:123:settings', ['theme' => 'dark'], 600);
$cache->set('user:123:posts', [1, 2, 3], 600);

echo "Cached 3 items for user:123\n";

// Invalidate all user data
$userKeys = ['user:123:profile', 'user:123:settings', 'user:123:posts'];
$cache->deleteMultiple($userKeys);

echo "✓ Invalidated all user:123 cache\n";
echo "Profile exists: " . ($cache->has('user:123:profile') ? 'Yes' : 'No') . "\n\n";

// =============================================
// Summary
// =============================================
echo "=== Cache Drivers Summary ===\n\n";

echo "Driver Comparison:\n\n";

echo "Array Cache:\n";
echo "  Pros: Fastest, no setup\n";
echo "  Cons: Lost on script end, not shared\n";
echo "  Use for: Testing, request-level cache\n\n";

echo "File Cache:\n";
echo "  Pros: Simple, persistent, no dependencies\n";
echo "  Cons: Slower, filesystem I/O\n";
echo "  Use for: Development, small apps\n\n";

echo "Redis Cache:\n";
echo "  Pros: Fast, persistent, distributed, atomic ops\n";
echo "  Cons: Requires Redis server\n";
echo "  Use for: Production, high traffic, microservices\n\n";

echo "Memcached Cache:\n";
echo "  Pros: Fast, distributed\n";
echo "  Cons: Not persistent, requires Memcached\n";
echo "  Use for: Session storage, temporary data\n\n";

echo "Database Cache:\n";
echo "  Pros: Persistent, queryable, transactions\n";
echo "  Cons: Slower than memory stores\n";
echo "  Use for: When you already have a database\n\n";

echo "Best Practices:\n";
echo "- Use cache keys with clear namespaces (e.g., 'llm:response:...')\n";
echo "- Set appropriate TTL values\n";
echo "- Implement cache warming for critical data\n";
echo "- Use multi-tier caching for hot data\n";
echo "- Monitor cache hit rates\n";
echo "- Plan cache invalidation strategy\n\n";

echo "For LLM Applications:\n";
echo "- Cache identical prompts (huge cost savings!)\n";
echo "- Use Redis for distributed caching across instances\n";
echo "- Cache embeddings (expensive to generate)\n";
echo "- Cache tool/function results\n";
echo "- Set longer TTL for deterministic responses\n";

