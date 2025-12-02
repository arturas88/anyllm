<?php

/**
 * Batch Processing Example
 * 
 * This example demonstrates how to process multiple LLM requests
 * concurrently using the BatchProcessor class.
 * 
 * Requirements:
 * - guzzlehttp/guzzle must be installed
 * - PHP 8.2+
 */

require_once __DIR__ . '/../bootstrap.php';

use AnyLLM\AnyLLM;
use AnyLLM\Batch\BatchProcessor;
use AnyLLM\Messages\UserMessage;

// Check for API key
$apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');
if (empty($apiKey)) {
    echo "Error: OPENAI_API_KEY environment variable is not set.\n";
    echo "Please set it before running this example:\n";
    echo "  export OPENAI_API_KEY=your-api-key-here\n";
    echo "  Or create a .env file in the project root with: OPENAI_API_KEY=your-api-key-here\n";
    exit(1);
}

// Create a provider and batch processor
$provider = AnyLLM::openai($apiKey);
$processor = new BatchProcessor($provider);

echo "=== Batch Processing Example ===\n\n";

// Example 1: Batch text generation
echo "1. Batch Text Generation:\n";
$promises = [
    'greeting' => $processor->generateText('gpt-4o-mini', 'Say hello'),
    'farewell' => $processor->generateText('gpt-4o-mini', 'Say goodbye'),
    'question' => $processor->generateText('gpt-4o-mini', 'What is PHP?'),
    'joke' => $processor->generateText('gpt-4o-mini', 'Tell a short joke'),
];

echo "   Processing 4 requests concurrently...\n";
$startTime = microtime(true);

$results = $processor->wait($promises);

$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

foreach ($results as $key => $response) {
    echo "   {$key}: " . substr($response->text, 0, 50) . "...\n";
}
echo "   Completed in {$duration} seconds\n\n";

// Example 2: Batch chat requests
echo "2. Batch Chat Requests:\n";
$chatPromises = [
    'explain' => $processor->chat(
        'gpt-4o-mini',
        [new UserMessage('Explain async programming')],
    ),
    'compare' => $processor->chat(
        'gpt-4o-mini',
        [new UserMessage('Compare PHP and Python')],
    ),
    'example' => $processor->chat(
        'gpt-4o-mini',
        [new UserMessage('Give a code example')],
    ),
];

$chatResults = $processor->wait($chatPromises);

foreach ($chatResults as $key => $response) {
    echo "   {$key}: " . substr($response->content, 0, 60) . "...\n";
}
echo "\n";

// Example 3: Handling failures with settle
echo "3. Handling Failures:\n";
$mixedPromises = [
    'valid' => $processor->generateText('gpt-4o-mini', 'This will work'),
    'invalid' => $processor->generateText('invalid-model', 'This will fail'),
];

$settled = $processor->settle($mixedPromises);

foreach ($settled as $key => $result) {
    if ($result['state'] === 'fulfilled') {
        // TextResponse uses ->text, ChatResponse uses ->content
        $text = $result['value']->text ?? $result['value']->content ?? '';
        echo "   {$key}: Success - " . substr($text, 0, 40) . "...\n";
    } else {
        echo "   {$key}: Failed - {$result['reason']->getMessage()}\n";
    }
}
echo "\n";

// Example 4: Timeout handling
echo "4. Timeout Handling:\n";
try {
    $timeoutPromises = [
        'slow' => $processor->generateText('gpt-4o-mini', 'Write a long story'),
    ];
    
    $timeoutResults = $processor->waitWithTimeout($timeoutPromises, 5.0);
    echo "   Completed within timeout\n";
} catch (\Exception $e) {
    echo "   Timeout or error: {$e->getMessage()}\n";
}
echo "\n";

// Example 5: Performance comparison
echo "5. Performance Comparison:\n";
$prompts = [
    'What is PHP?',
    'What is Python?',
    'What is JavaScript?',
    'What is Java?',
    'What is C++?',
];

// Sequential processing
echo "   Sequential processing:\n";
$startTime = microtime(true);
foreach ($prompts as $prompt) {
    $provider->generateText('gpt-4o-mini', $prompt);
}
$sequentialTime = microtime(true) - $startTime;
echo "   Time: " . round($sequentialTime, 2) . " seconds\n";

// Batch processing
echo "   Batch processing:\n";
$startTime = microtime(true);
$batchPromises = [];
foreach ($prompts as $index => $prompt) {
    $batchPromises["prompt_{$index}"] = $processor->generateText('gpt-4o-mini', $prompt);
}
$processor->wait($batchPromises);
$batchTime = microtime(true) - $startTime;
echo "   Time: " . round($batchTime, 2) . " seconds\n";
echo "   Speedup: " . round($sequentialTime / $batchTime, 2) . "x faster\n";

echo "\n=== Example Complete ===\n";

