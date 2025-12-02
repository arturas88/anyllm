<?php

/**
 * Async/Promise Support Example
 *
 * This example demonstrates how to use async/promise-based methods
 * for non-blocking LLM API calls.
 *
 * Requirements:
 * - guzzlehttp/guzzle must be installed
 * - PHP 8.2+
 */

require_once __DIR__ . '/../bootstrap.php';

use AnyLLM\AnyLLM;
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

// Create a provider
$provider = AnyLLM::openai($apiKey);

echo "=== Async/Promise Support Example ===\n\n";

// Example 1: Single async request
echo "1. Single Async Request:\n";
try {
    $promise = $provider->generateTextAsync(
        model: 'gpt-4o-mini',
        prompt: 'Say hello in one sentence.',
    );

    // Do other work while waiting...
    echo "   Doing other work while request is processing...\n";

    // Wait for the result
    $response = $promise->wait();
    echo "   Response: {$response->text}\n\n";
} catch (\Exception $e) {
    echo "   Error: {$e->getMessage()}\n";
    echo "   Note: Make sure OPENAI_API_KEY environment variable is set.\n\n";
}

// Example 2: Multiple async requests
echo "2. Multiple Async Requests:\n";
$promises = [
    'greeting' => $provider->generateTextAsync('gpt-4o-mini', 'Say hello'),
    'farewell' => $provider->generateTextAsync('gpt-4o-mini', 'Say goodbye'),
    'question' => $provider->generateTextAsync('gpt-4o-mini', 'What is PHP?'),
];

// All requests are sent concurrently
echo "   Sent 3 requests concurrently...\n";

// Wait for all to complete
$results = \GuzzleHttp\Promise\Utils::settle($promises)->wait();

foreach ($results as $key => $result) {
    if ($result['state'] === 'fulfilled') {
        echo "   {$key}: {$result['value']->text}\n";
    } else {
        echo "   {$key}: Error - {$result['reason']->getMessage()}\n";
    }
}
echo "\n";

// Example 3: Async chat
echo "3. Async Chat:\n";
try {
    $chatPromise = $provider->chatAsync(
        model: 'gpt-4o-mini',
        messages: [
            new UserMessage('Explain async programming in one sentence.'),
        ],
    );

    $chatResponse = $chatPromise->wait();
    echo "   Response: {$chatResponse->content}\n\n";
} catch (\Exception $e) {
    echo "   Error: {$e->getMessage()}\n\n";
}

// Example 4: Promise chaining
echo "4. Promise Chaining:\n";
try {
    $chainedPromise = $provider->generateTextAsync('gpt-4o-mini', 'Count to 3')
        ->then(function ($response) {
            echo "   First response: {$response->text}\n";
            // You could chain another async operation here
            return $response;
        })
        ->then(function ($response) {
            echo "   Processing complete!\n";
            return $response;
        });

    $chainedPromise->wait();
    echo "\n";
} catch (\Exception $e) {
    echo "   Error: {$e->getMessage()}\n\n";
}

// Example 5: Error handling
echo "5. Error Handling:\n";
try {
    $errorPromise = $provider->generateTextAsync(
        model: 'invalid-model',
        prompt: 'This will fail',
    );

    $errorPromise->wait();
} catch (\Exception $e) {
    echo "   Caught error: {$e->getMessage()}\n";
}

echo "\n=== Example Complete ===\n";
