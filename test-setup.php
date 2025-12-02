#!/usr/bin/env php
<?php

/**
 * Quick setup verification script
 * Run: php test-setup.php
 */

declare(strict_types=1);

echo "🔍 AnyLLM Setup Verification\n";
echo str_repeat("=", 50) . "\n\n";

// Check PHP version
echo "1. Checking PHP version... ";
if (PHP_VERSION_ID >= 80200) {
    echo "✅ " . PHP_VERSION . "\n";
} else {
    echo "❌ PHP 8.2+ required, found " . PHP_VERSION . "\n";
    exit(1);
}

// Check if vendor directory exists
echo "2. Checking dependencies... ";
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "✅ Installed\n";
} else {
    echo "❌ Run 'composer install'\n";
    exit(1);
}

// Load bootstrap
require_once __DIR__ . '/bootstrap.php';

// Check for .env file
echo "3. Checking .env file... ";
if (file_exists(__DIR__ . '/.env')) {
    echo "✅ Found\n";
} else {
    echo "⚠️  Not found (optional)\n";
}

// Check for OpenAI API key
echo "4. Checking OPENAI_API_KEY... ";
$apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');
if ($apiKey && $apiKey !== 'your-api-key' && $apiKey !== 'sk-your-openai-api-key-here') {
    echo "✅ Set (" . substr($apiKey, 0, 7) . "...)\n";
    $hasValidKey = true;
} else {
    echo "⚠️  Not set or using placeholder\n";
    $hasValidKey = false;
}

// Check if classes can be loaded
echo "5. Checking autoloader... ";
try {
    $class = new \AnyLLM\AnyLLM();
    echo "✅ Working\n";
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test FakeProvider (no API key needed)
echo "6. Testing FakeProvider... ";
try {
    $fake = new \AnyLLM\Testing\FakeProvider();
    $fake->willReturn('Hello, World!');
    $response = $fake->generateText('fake-model', 'test');
    if ($response->text === 'Hello, World!') {
        echo "✅ Working\n";
    } else {
        echo "❌ Unexpected response\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test real API if key is available
if ($hasValidKey) {
    echo "7. Testing OpenAI API... ";
    try {
        $llm = \AnyLLM\AnyLLM::openai(apiKey: $apiKey);
        $response = $llm->generateText('gpt-4o-mini', 'Say "test successful" in 2 words');
        echo "✅ Connected\n";
        echo "   Response: " . substr($response->text, 0, 50) . "\n";
    } catch (\Throwable $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        echo "   This might be a network issue, certificate problem, or invalid API key\n";
    }
} else {
    echo "7. Testing OpenAI API... ⏭️  Skipped (no valid API key)\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "✨ Setup verification complete!\n\n";

if ($hasValidKey) {
    echo "Next steps:\n";
    echo "  • Run tests: composer test\n";
    echo "  • Try examples: php examples/basic-usage.php\n";
} else {
    echo "To test with real API:\n";
    echo "  1. Get API key: https://platform.openai.com/api-keys\n";
    echo "  2. Add to .env: OPENAI_API_KEY=sk-your-key\n";
    echo "  3. Run: php test-setup.php\n";
}

echo "\n";
