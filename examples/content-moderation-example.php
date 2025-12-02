<?php

/**
 * Content Moderation Example
 *
 * This example demonstrates how to use content moderation
 * to check if text violates content policies.
 *
 * Requirements:
 * - OpenAI API key (content moderation is currently supported by OpenAI)
 * - PHP 8.2+
 */

require_once __DIR__ . '/../bootstrap.php';

use AnyLLM\AnyLLM;
use AnyLLM\Contracts\ContentModerationInterface;

// Check for API key
$apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');
if (empty($apiKey)) {
    echo "Error: OPENAI_API_KEY environment variable is not set.\n";
    echo "Please set it before running this example:\n";
    echo "  export OPENAI_API_KEY=your-api-key-here\n";
    echo "  Or create a .env file in the project root with: OPENAI_API_KEY=your-api-key-here\n";
    exit(1);
}

// Create OpenAI provider (currently the only provider with moderation support)
$provider = AnyLLM::openai($apiKey);

if (!($provider instanceof ContentModerationInterface)) {
    echo "Error: This provider does not support content moderation.\n";
    exit(1);
}

echo "=== Content Moderation Example ===\n\n";

// Example 1: Moderate a single text
echo "1. Single Text Moderation:\n";
$text1 = "I love programming!";
echo "   Text: \"{$text1}\"\n";

$result1 = $provider->moderate($text1);
echo "   Flagged: " . ($result1->flagged ? 'Yes' : 'No') . "\n";
if ($result1->flagged) {
    echo "   Flagged Categories: " . implode(', ', $result1->getFlaggedCategories()) . "\n";
}
echo "\n";

// Example 2: Moderate potentially harmful content
echo "2. Potentially Harmful Content:\n";
$text2 = "This is a test of harmful content";
echo "   Text: \"{$text2}\"\n";

$result2 = $provider->moderate($text2);
echo "   Flagged: " . ($result2->flagged ? 'Yes' : 'No') . "\n";
if ($result2->flagged) {
    $categories = $result2->getFlaggedCategories();
    echo "   Flagged Categories: " . implode(', ', $categories) . "\n";
    foreach ($categories as $category) {
        $score = $result2->getScore($category);
        echo "   - {$category}: " . round($score * 100, 2) . "%\n";
    }
}
echo "\n";

// Example 3: Batch moderation
echo "3. Batch Moderation:\n";
$texts = [
    "Hello, how are you?",
    "This is a friendly message",
    "I need help with my code",
];

echo "   Moderating " . count($texts) . " texts...\n";
$results = $provider->moderate($texts);

foreach ($results as $index => $result) {
    echo "   Text " . ($index + 1) . ": " . ($result->flagged ? 'FLAGGED' : 'OK') . "\n";
    if ($result->flagged) {
        echo "      Categories: " . implode(', ', $result->getFlaggedCategories()) . "\n";
    }
}
echo "\n";

// Example 4: Async moderation
echo "4. Async Moderation:\n";
$promise = $provider->moderateAsync("Check this text for moderation");
echo "   Request sent asynchronously...\n";

$asyncResult = $promise->wait();
echo "   Flagged: " . ($asyncResult->flagged ? 'Yes' : 'No') . "\n";
echo "\n";

// Example 5: Check specific categories
echo "5. Category-Specific Checks:\n";
$testText = "Sample text to check";
$moderationResult = $provider->moderate($testText);

$categories = [
    'hate',
    'hate/threatening',
    'harassment',
    'harassment/threatening',
    'self-harm',
    'self-harm/intent',
    'self-harm/instructions',
    'sexual',
    'sexual/minors',
    'violence',
    'violence/graphic',
];

echo "   Checking categories for: \"{$testText}\"\n";
foreach ($categories as $category) {
    $isFlagged = $moderationResult->isFlagged($category);
    $score = $moderationResult->getScore($category);
    if ($isFlagged || $score > 0.1) {
        echo "   - {$category}: " . ($isFlagged ? 'FLAGGED' : 'Low score') . " (Score: " . round($score * 100, 2) . "%)\n";
    }
}
echo "\n";

// Example 6: Full moderation details
echo "6. Full Moderation Details:\n";
$fullResult = $provider->moderate("Get full moderation details");
$details = $fullResult->toArray();

echo "   ID: {$details['id']}\n";
echo "   Model: {$details['model']}\n";
echo "   Flagged: " . ($details['flagged'] ? 'Yes' : 'No') . "\n";
echo "   All Categories:\n";
foreach ($details['categories'] as $category => $flagged) {
    $score = $details['category_scores'][$category] ?? 0;
    echo "     - {$category}: " . ($flagged ? 'FLAGGED' : 'OK') . " (Score: " . round($score * 100, 2) . "%)\n";
}

echo "\n=== Example Complete ===\n";
