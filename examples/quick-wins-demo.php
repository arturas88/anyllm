<?php

require __DIR__ . '/../vendor/autoload.php';

use AnyLLM\AnyLLM;
use AnyLLM\Config\ConfigValidator;
use AnyLLM\Enums\Provider;
use AnyLLM\Messages\UserMessage;
use AnyLLM\Support\FileCache;
use AnyLLM\Support\PromptTemplate;
use AnyLLM\Support\TokenCounter;

// =============================================
// Quick Win #1: Retry Logic
// =============================================
echo "=== Retry Logic Demo ===\n\n";

$llm = AnyLLM::provider(Provider::OPENAI)
    ->model('gpt-4')
    ->apiKey(getenv('OPENAI_API_KEY'))
    ->build();

// Enable retry with custom settings
$llm->withRetry(
    maxRetries: 5,           // Retry up to 5 times
    initialDelayMs: 500,     // Start with 500ms delay
    multiplier: 2.0          // Double the delay each retry
);

// This will automatically retry on rate limits and 5xx errors
try {
    $response = $llm->chat([
        new UserMessage('Hello!'),
    ]);
    echo "Response: {$response->content()}\n\n";
} catch (\Exception $e) {
    echo "Failed after retries: {$e->getMessage()}\n\n";
}

// Disable retry for specific calls
$response = $llm->withoutRetry()->chat([
    new UserMessage('No retry for this one'),
]);

// =============================================
// Quick Win #2: Token Counter
// =============================================
echo "=== Token Counter Demo ===\n\n";

// Estimate tokens in text
$text = "This is a sample text to estimate token count.";
$estimated = TokenCounter::estimate($text, 'gpt-4');
echo "Estimated tokens: {$estimated}\n";

// Estimate tokens in messages
$messages = [
    new UserMessage('What is PHP?'),
    new UserMessage('Tell me about Laravel'),
];
$messageTokens = TokenCounter::estimateMessages($messages, 'gpt-4');
echo "Estimated message tokens: {$messageTokens}\n";

// Check if would exceed limit
$wouldExceed = TokenCounter::wouldExceedLimit($messages, 100, 'gpt-4');
echo "Would exceed 100 tokens: " . ($wouldExceed ? 'Yes' : 'No') . "\n";

// Truncate text to fit token limit
$longText = str_repeat("This is a very long text. ", 100);
$truncated = TokenCounter::truncate($longText, 50, 'gpt-4');
echo "Truncated text length: " . strlen($truncated) . "\n";

// Format token count
$formatted = TokenCounter::format(1234, 4096);
echo "Formatted: {$formatted}\n\n";

// =============================================
// Quick Win #3: Configuration Validator
// =============================================
echo "=== Configuration Validator Demo ===\n\n";

try {
    // Validate custom configuration
    ConfigValidator::validate([
        'temperature' => 0.7,
        'max_tokens' => 100,
        'model' => 'gpt-4',
    ], [
        'temperature' => ['type' => 'double', 'min' => 0, 'max' => 2],
        'max_tokens' => ['type' => 'integer', 'min' => 1],
        'model' => ['required' => true, 'type' => 'string'],
    ]);
    echo "✓ Configuration is valid\n";
} catch (\Exception $e) {
    echo "✗ Configuration error: {$e->getMessage()}\n";
}

// Quick validation helpers
try {
    ConfigValidator::requireApiKey('sk-test123', 'OpenAI');
    ConfigValidator::requireModel('gpt-4');
    ConfigValidator::validateTemperature(0.7);
    ConfigValidator::validateTokenLimit(2000);
    echo "✓ All validations passed\n\n";
} catch (\Exception $e) {
    echo "✗ Validation error: {$e->getMessage()}\n\n";
}

// =============================================
// Quick Win #4: Prompt Templates
// =============================================
echo "=== Prompt Templates Demo ===\n\n";

// Create a custom template
$template = PromptTemplate::make(
    "You are a {{role}}. Your task is to {{task}}.\n\n" .
    "Input: {{input}}\n\n" .
    "Response:"
);

$prompt = $template
    ->with('role', 'helpful assistant')
    ->with('task', 'answer questions concisely')
    ->with('input', 'What is PHP?')
    ->render();

echo "Custom prompt:\n{$prompt}\n\n";

// Use pre-built templates
$classificationPrompt = PromptTemplate::classification(['positive', 'negative', 'neutral'])
    ->with('text', 'This product is amazing!')
    ->render();

echo "Classification prompt:\n{$classificationPrompt}\n\n";

$summaryPrompt = PromptTemplate::summarization(maxWords: 50)
    ->with('text', 'Your long text here...')
    ->render();

echo "Summary prompt:\n{$summaryPrompt}\n\n";

$qaPrompt = PromptTemplate::questionAnswer()
    ->with('context', 'PHP is a popular server-side scripting language.')
    ->with('question', 'What is PHP?')
    ->render();

echo "Q&A prompt:\n{$qaPrompt}\n\n";

// Check template completeness
$incomplete = PromptTemplate::make('Hello {{name}}, welcome to {{place}}!')
    ->with('name', 'John');

echo "Template complete: " . ($incomplete->isComplete() ? 'Yes' : 'No') . "\n";
echo "Required variables: " . implode(', ', $incomplete->getRequiredVariables()) . "\n\n";

// =============================================
// Quick Win #5: File Cache
// =============================================
echo "=== File Cache Demo ===\n\n";

$cache = new FileCache(
    cacheDir: __DIR__ . '/../storage/cache',
    defaultTtl: 3600, // 1 hour
);

// Store in cache
$cache->set('user:123', ['name' => 'John Doe', 'email' => 'john@example.com'], 300);
echo "✓ Stored user in cache\n";

// Retrieve from cache
$user = $cache->get('user:123');
echo "Cached user: " . json_encode($user) . "\n";

// Check if exists
$exists = $cache->has('user:123');
echo "Cache exists: " . ($exists ? 'Yes' : 'No') . "\n";

// Remember pattern (get or compute and cache)
$expensiveData = $cache->remember('expensive_operation', function () {
    // Simulate expensive operation
    sleep(1);
    return ['result' => 'computed data'];
}, 600);

echo "Expensive operation result: " . json_encode($expensiveData) . "\n";

// Cache LLM responses
$cacheKey = FileCache::key('llm', 'openai', 'gpt-4', 'What is PHP?');
$response = $cache->remember($cacheKey, function () use ($llm) {
    return $llm->chat([new UserMessage('What is PHP?')])->content();
}, 3600);

echo "LLM response (cached): " . substr($response, 0, 50) . "...\n";

// Cache statistics
$stats = $cache->stats();
echo "\nCache stats:\n";
echo "- Total items: {$stats['total_items']}\n";
echo "- Valid items: {$stats['valid_items']}\n";
echo "- Expired items: {$stats['expired_items']}\n";
echo "- Total size: " . round($stats['total_size'] / 1024, 2) . " KB\n";

// Prune expired items
$pruned = $cache->prune();
echo "Pruned {$pruned} expired items\n\n";

// =============================================
// Combined Example: All Together
// =============================================
echo "=== Combined Example ===\n\n";

// Create a reusable sentiment analysis function
function analyzeSentiment(string $text, $llm, FileCache $cache): string
{
    // Generate cache key
    $cacheKey = FileCache::key('sentiment', md5($text));
    
    // Try to get from cache
    return $cache->remember($cacheKey, function () use ($text, $llm) {
        // Estimate tokens
        $tokens = TokenCounter::estimate($text);
        echo "Processing text with ~{$tokens} tokens...\n";
        
        // Create prompt from template
        $prompt = PromptTemplate::sentiment()
            ->with('text', $text)
            ->render();
        
        // Call LLM with retry enabled
        $response = $llm->withRetry(maxRetries: 3)
            ->chat([new UserMessage($prompt)]);
        
        return trim($response->content());
    }, 3600);
}

$sentiment = analyzeSentiment(
    "This library is absolutely amazing! It makes working with LLMs so easy!",
    $llm,
    $cache
);

echo "Sentiment: {$sentiment}\n\n";

echo "=== All Quick Wins Demonstrated Successfully! ===\n";

