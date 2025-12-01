<?php

require __DIR__ . '/../vendor/autoload.php';

use AnyLLM\Logging\LogEntry;
use AnyLLM\Logging\LoggerFactory;
use AnyLLM\Logging\Drivers\DatabaseLogDriver;
use AnyLLM\Logging\Drivers\FileLogDriver;
use AnyLLM\Logging\Drivers\NullLogDriver;

echo "=== Logging Examples ===\n\n";

// =============================================
// Example 1: File Logging
// =============================================
echo "=== 1. File Logging ===\n\n";

$fileLogger = new FileLogDriver(
    logPath: __DIR__ . '/../storage/logs',
    maxFileSize: 10485760, // 10MB
    maxFiles: 5
);

// Log a successful request
$entry1 = new LogEntry(
    provider: 'openai',
    model: 'gpt-4o',
    method: 'chat',
    request: ['messages' => [['role' => 'user', 'content' => 'Hello']]],
    response: ['choices' => [['message' => ['content' => 'Hi there!']]]],
    error: null,
    durationMs: 1234,
    tokensUsed: 50,
    cost: 0.0025,
    metadata: ['user_id' => 'user-123'],
);

$fileLogger->write($entry1);
echo "✓ Logged successful request to file\n";

// Log an error
$entry2 = new LogEntry(
    provider: 'anthropic',
    model: 'claude-opus-4-5',
    method: 'chat',
    request: [],
    response: [],
    error: 'Rate limit exceeded',
    durationMs: 500,
    tokensUsed: 0,
    cost: 0,
    metadata: [],
);

$fileLogger->write($entry2);
echo "✓ Logged error to file\n\n";

// Query logs
$logs = $fileLogger->query(['provider' => 'openai'], limit: 10);
echo "Found " . count($logs) . " OpenAI logs\n";

// Analyze logs
$analytics = $fileLogger->analyze();
echo "Analytics:\n";
echo "- Total requests: {$analytics['overall']['total_requests']}\n";
echo "- Failed requests: {$analytics['overall']['failed_requests']}\n";
echo "- Total tokens: {$analytics['overall']['total_tokens']}\n";
echo "- Total cost: $" . number_format($analytics['overall']['total_cost'], 4) . "\n\n";

// =============================================
// Example 2: Database Logging
// =============================================
echo "=== 2. Database Logging ===\n\n";

try {
    $dbLogger = LoggerFactory::create('database', [
        'driver' => 'mysql',
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: 3306,
        'database' => getenv('DB_DATABASE') ?: 'anyllm',
        'username' => getenv('DB_USERNAME') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
    ]);

    // Log entries
    $dbLogger->write($entry1);
    $dbLogger->write($entry2);
    echo "✓ Logged to database\n";

    // Query with filters
    $recentLogs = $dbLogger->query([
        'provider' => 'openai',
        'has_error' => false,
    ], limit: 20);
    echo "Found " . count($recentLogs) . " successful OpenAI requests\n";

    // Analytics
    $stats = $dbLogger->analyze('openai');
    echo "\nOpenAI Analytics:\n";
    print_r($stats['overall']);

    // Prune old logs
    $deleted = $dbLogger->prune(30); // Delete logs older than 30 days
    echo "\nPruned {$deleted} old log entries\n\n";

} catch (\Exception $e) {
    echo "Database logging not available (expected if DB not configured):\n";
    echo $e->getMessage() . "\n\n";
}

// =============================================
// Example 3: Null Logger (for testing)
// =============================================
echo "=== 3. Null Logger ===\n\n";

$nullLogger = new NullLogDriver();

$nullLogger->write($entry1);
echo "✓ Null logger doesn't write anything (useful for testing)\n\n";

// =============================================
// Example 4: Factory Pattern
// =============================================
echo "=== 4. Using Logger Factory ===\n\n";

// Create logger from config
$logger = LoggerFactory::create('file', [
    'log_path' => __DIR__ . '/../storage/logs',
    'max_file_size' => 5242880, // 5MB
    'max_files' => 3,
]);

echo "✓ Created file logger via factory\n";

// Use it
$logger->write(new LogEntry(
    provider: 'google',
    model: 'gemini-2.5-flash',
    method: 'generateText',
    request: [],
    response: [],
    error: null,
    durationMs: 800,
    tokensUsed: 100,
    cost: 0.001,
    metadata: [],
));

echo "✓ Logged Google Gemini request\n\n";

// =============================================
// Example 5: Advanced Analytics
// =============================================
echo "=== 5. Advanced Analytics ===\n\n";

// Log multiple requests for different providers
$providers = ['openai', 'anthropic', 'google'];
$models = ['gpt-4o', 'claude-opus-4-5', 'gemini-2.5-flash'];

foreach ($providers as $i => $provider) {
    for ($j = 0; $j < 5; $j++) {
        $entry = new LogEntry(
            provider: $provider,
            model: $models[$i],
            method: 'chat',
            request: [],
            response: [],
            error: $j === 4 ? 'Test error' : null, // One error per provider
            durationMs: rand(500, 2000),
            tokensUsed: rand(50, 200),
            cost: rand(1, 10) / 1000,
            metadata: ['batch' => 'analytics-test'],
        );
        
        $fileLogger->write($entry);
    }
}

echo "✓ Logged 15 test requests (3 providers × 5 requests)\n\n";

// Analyze
$analytics = $fileLogger->analyze();

echo "Overall Statistics:\n";
echo "- Total requests: {$analytics['overall']['total_requests']}\n";
echo "- Failed requests: {$analytics['overall']['failed_requests']}\n";
echo "- Success rate: " . number_format((1 - ($analytics['overall']['failed_requests'] / max($analytics['overall']['total_requests'], 1))) * 100, 2) . "%\n";
echo "- Average duration: " . round($analytics['overall']['avg_duration']) . "ms\n";
echo "- Total cost: $" . number_format($analytics['overall']['total_cost'], 4) . "\n\n";

echo "By Provider:\n";
foreach ($analytics['by_provider'] as $provider => $stats) {
    echo "- {$provider}: {$stats['requests']} requests, {$stats['tokens']} tokens, $" . number_format($stats['cost'], 4) . "\n";
}

// =============================================
// Example 6: Error Tracking
// =============================================
echo "\n=== 6. Error Tracking ===\n\n";

// Query only errors
$errors = $fileLogger->query(['has_error' => true], limit: 50);

echo "Found " . count($errors) . " failed requests:\n";
foreach (array_slice($errors, 0, 5) as $error) {
    echo "- {$error->provider}/{$error->model}: {$error->error}\n";
}

echo "\n=== Logging examples completed! ===\n\n";

echo "Log files created in: " . __DIR__ . "/../storage/logs/\n";
echo "- anyllm-" . date('Y-m-d') . ".log (human-readable)\n";
echo "- details-" . date('Y-m-d') . ".jsonl (machine-readable JSON lines)\n";

