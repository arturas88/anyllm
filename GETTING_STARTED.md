# Getting Started with AnyLLM

Welcome to AnyLLM! This guide will help you get up and running quickly.

## Installation

```bash
composer require arturas88/anyllm
composer require guzzlehttp/guzzle
```

## Quick Start

### 1. Basic Chat

```php
<?php

use AnyLLM\AnyLLM;
use AnyLLM\Enums\Provider;

// Create provider
$llm = AnyLLM::provider(Provider::OPENAI)
    ->apiKey($_ENV['OPENAI_API_KEY'])
    ->model('gpt-4o')
    ->build();

// Simple chat
$response = $llm->generateText('gpt-4o', 'Explain PHP in simple terms');
echo $response->text;
```

### 2. Conversation with History

```php
use AnyLLM\Messages\UserMessage;

$messages = [
    new UserMessage('What is PHP?'),
];

$response = $llm->chat('gpt-4o', $messages);
echo $response->content();
```

### 3. With Retry & Caching

```php
use AnyLLM\Support\FileCache;

$cache = new FileCache();

// Enable retry logic
$llm->withRetry(maxRetries: 5);

// Cache expensive calls
$result = $cache->remember('llm:question:1', function() use ($llm) {
    return $llm->generateText('gpt-4o', 'What is AI?');
}, 3600);
```

## Core Features

### Structured Output

```php
use AnyLLM\StructuredOutput\Attributes\Description;

class Person {
    #[Description('Full name')]
    public string $name;
    
    #[Description('Age in years')]
    public int $age;
}

$response = $llm->generateObject(
    model: 'gpt-4o',
    prompt: 'Extract person from: John Doe is 30 years old',
    schema: Person::class
);

echo $response->object->name; // "John Doe"
echo $response->object->age;  // 30
```

### Tool Calling

```php
use AnyLLM\Tools\Tool;

$weatherTool = Tool::fromCallable(
    name: 'get_weather',
    description: 'Get current weather',
    callable: function(string $location): string {
        return "Sunny, 72°F in {$location}";
    }
);

$response = $llm->chat('gpt-4o', [
    new UserMessage('What\'s the weather in Paris?')
], tools: [$weatherTool]);
```

### Embeddings

```php
$texts = [
    'The cat sat on the mat',
    'A feline rested on a rug',
];

$embeddings = $llm->embed('text-embedding-3-small', $texts);

// Check similarity
$similarity = $embeddings->similarity(0, 1);
echo "Similarity: " . number_format($similarity, 4); // 0.9234
```

## Production Features

### Persistence

```php
use AnyLLM\Conversations\ConversationManager;
use AnyLLM\Conversations\Repository\ConversationRepositoryFactory;

// Database persistence
$repository = ConversationRepositoryFactory::create('database', [
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'anyllm',
    'username' => 'root',
    'password' => '',
]);

$manager = new ConversationManager($llm, $repository);

// Conversations persist forever
$conversation = $manager->create('user-123', 'Support Chat');
$manager->addMessage($conversation, 'user', 'Hello!');
$response = $manager->chat($conversation, $llm);
```

### Logging

```php
use AnyLLM\Logging\LoggerFactory;

$logger = LoggerFactory::create('database', $dbConfig);

// All requests are logged
// Query logs, generate analytics, track costs
$stats = $logger->analyze('openai');
```

### Rate Limiting

```php
use AnyLLM\Support\RateLimit\RateLimiterFactory;

$limiter = RateLimiterFactory::create('redis', $redisConfig);

// Prevent abuse
$limiter->attempt(
    key: "user:{$userId}",
    callback: fn() => $llm->chat($messages),
    maxAttempts: 10,
    decaySeconds: 60
);
```

### Caching

```php
use AnyLLM\Support\Cache\CacheFactory;

$cache = CacheFactory::create('redis', $redisConfig);

// Massive cost savings
$response = $cache->remember('prompt:' . md5($prompt), function() use ($llm, $prompt) {
    return $llm->generateText('gpt-4o', $prompt);
}, 3600);
```

## Advanced Features

### Middleware

```php
use AnyLLM\Middleware\MiddlewarePipeline;
use AnyLLM\Middleware\CachingMiddleware;
use AnyLLM\Middleware\LoggingMiddleware;
use AnyLLM\Middleware\RateLimitMiddleware;

$pipeline = new MiddlewarePipeline([
    new CachingMiddleware($cache),
    new RateLimitMiddleware($rateLimiter),
    new LoggingMiddleware($logger),
]);

// All requests go through middleware
```

### Streaming

```php
use AnyLLM\Streaming\StreamController;

$controller = new StreamController();

$controller
    ->onChunk(fn($content) => echo $content)
    ->onProgress(fn($progress) => updateProgressBar($progress))
    ->onComplete(fn($content, $tokens) => echo "\nDone! {$tokens} tokens");

// Stream responses in real-time
```

### Metrics

```php
use AnyLLM\Metrics\MetricsCollector;
use AnyLLM\Metrics\MetricsDashboard;

$metrics = new MetricsCollector();

// Track everything
$metrics->recordRequest('openai', 'gpt-4o', 'chat');
$metrics->recordLatency('openai', 'gpt-4o', 1234);
$metrics->recordTokens('openai', 'gpt-4o', 150);
$metrics->recordCost('openai', 'gpt-4o', 0.0075);

// View dashboard
$dashboard = new MetricsDashboard($metrics);
echo $dashboard->render();
```

## Supported Providers

- **OpenAI** - GPT-4o, GPT-4, DALL-E, Whisper
- **Anthropic** - Claude Opus 4.5, Sonnet 4.5, Haiku 4.5
- **Google** - Gemini 2.5 Flash, Gemini 3 Pro
- **Mistral** - Large, Medium, Small + Pixtral (OCR)
- **xAI** - Grok Beta
- **OpenRouter** - Access to 100+ models
- **Ollama** - Run models locally

## Configuration

Create `config/any-llm.php`:

```php
return [
    'default_provider' => 'openai',
    
    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'default_model' => 'gpt-4o',
        ],
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'default_model' => 'claude-opus-4-5',
        ],
    ],
    
    'conversations' => [
        'repository' => 'database',
        'auto_summarize' => true,
    ],
    
    'logging' => [
        'driver' => 'database',
        'enabled' => true,
    ],
    
    'cache' => [
        'driver' => 'redis',
        'default_ttl' => 3600,
    ],
];
```

## Database Setup

Run migrations:

```bash
# Copy migrations to your project
cp vendor/arturas88/anyllm/database/migrations/* database/migrations/

# Run migrations
php artisan migrate

# Or run SQL directly
mysql database < vendor/arturas88/anyllm/database/migrations/create_llm_logs_table.php
```

## Examples

See the `examples/` directory for comprehensive examples:

- `basic-usage.php` - Getting started
- `all-providers-demo.php` - All 7 providers
- `embeddings-example.php` - Embeddings & RAG
- `middleware-example.php` - Middleware system
- `streaming-advanced.php` - Advanced streaming
- `metrics-example.php` - Metrics & monitoring
- `conversation-persistence.php` - Persistent conversations
- `logging-example.php` - Logging & analytics
- `rate-limiting-example.php` - Rate limiting
- `cache-drivers-example.php` - Caching strategies

## Best Practices

### 1. Always Use Retry Logic

```php
$llm->withRetry(maxRetries: 5)->chat($messages);
```

### 2. Cache Expensive Calls

```php
$cache->remember($key, fn() => $llm->embed($texts), 3600);
```

### 3. Monitor Usage

```php
$logger->analyze(); // Get cost and usage stats
```

### 4. Set Rate Limits

```php
$limiter->attempt($userKey, $callback, maxAttempts: 10);
```

### 5. Use Conversations for Chat

```php
$manager->chat($conversation, $llm); // Auto-saves, auto-summarizes
```

## Next Steps

1. **Read the Docs** - See [README.md](README.md) for full documentation
2. **Try Examples** - Run `php examples/basic-usage.php`
3. **Configure Logging** - Set up database logging
4. **Add Caching** - Configure Redis for performance
5. **Set Up Monitoring** - Use metrics dashboard

## Troubleshooting

### API Key Issues

```php
// Validate API key
ConfigValidator::requireApiKey($apiKey, 'OpenAI');
```

### Rate Limits

```php
// Check remaining attempts
$remaining = $limiter->remaining($key, $maxAttempts);
```

### Token Issues

```php
// Estimate tokens before calling
$tokens = TokenCounter::estimate($text, 'gpt-4');
```

### Debugging

```php
// Enable detailed logging
$logger = LoggerFactory::create('file', ['log_path' => './logs']);
```

## Support

- **Documentation**: See all `.md` files in the root
- **Examples**: Check `examples/` directory
- **Issues**: Report on GitHub
- **Questions**: Create a discussion

## What's Next?

Now that you're set up, explore:

- [Embeddings & RAG](examples/embeddings-example.php)
- [Middleware System](examples/middleware-example.php)
- [Metrics Dashboard](examples/metrics-example.php)
- [All Providers](examples/all-providers-demo.php)

Happy coding! 🚀

