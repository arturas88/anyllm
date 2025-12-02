<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AnyLLM\AnyLLM;
use AnyLLM\Messages\UserMessage;

echo "=== AnyLLM - All Providers Demo ===\n\n";

// ============================================================================
// Example 1: OpenAI (GPT models)
// ============================================================================
echo "1. OpenAI (GPT-4o-mini)\n";
echo str_repeat('-', 50) . "\n";

try {
    $openai = AnyLLM::openai(apiKey: $_ENV['OPENAI_API_KEY'] ?? null);

    $response = $openai->generateText(
        model: 'gpt-4o-mini',
        prompt: 'Say hello in 5 words',
    );

    echo "Response: {$response->text}\n";
    echo "Tokens: {$response->usage?->totalTokens}\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Example 2: Anthropic (Claude)
// ============================================================================
echo "2. Anthropic (Claude Haiku 4.5)\n";
echo str_repeat('-', 50) . "\n";

try {
    $anthropic = AnyLLM::anthropic(apiKey: $_ENV['ANTHROPIC_API_KEY'] ?? null);

    $response = $anthropic->generateText(
        model: 'claude-haiku-4-5',
        prompt: 'Say hello in 5 words',
    );

    echo "Response: {$response->text}\n";
    echo "Tokens: {$response->usage?->totalTokens}\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Example 3: Google (Gemini)
// ============================================================================
echo "3. Google (Gemini 2.5 Flash)\n";
echo str_repeat('-', 50) . "\n";

try {
    $google = AnyLLM::google(apiKey: $_ENV['GOOGLE_AI_API_KEY'] ?? null);

    $response = $google->generateText(
        model: 'gemini-2.5-flash',
        prompt: 'Say hello in 5 words',
    );

    echo "Response: {$response->text}\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Example 4: Mistral AI (with OCR capability)
// ============================================================================
echo "4. Mistral AI (with OCR)\n";
echo str_repeat('-', 50) . "\n";

try {
    $mistral = AnyLLM::mistral(apiKey: $_ENV['MISTRAL_API_KEY'] ?? null);

    // Text generation
    $response = $mistral->generateText(
        model: 'mistral-small-latest',
        prompt: 'Say hello in 5 words',
    );

    echo "Response: {$response->text}\n";

    // OCR Example (if you have an image URL)
    // $ocrResult = $mistral->extractText(
    //     model: 'pixtral-12b-2409',
    //     image: 'https://example.com/image.jpg',
    //     prompt: 'Extract all text from this image',
    // );
    // echo "OCR Text: {$ocrResult['text']}\n";

} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Example 5: xAI (Grok)
// ============================================================================
echo "5. xAI (Grok)\n";
echo str_repeat('-', 50) . "\n";

try {
    $xai = AnyLLM::xai(apiKey: $_ENV['XAI_API_KEY'] ?? null);

    $response = $xai->generateText(
        model: 'grok-beta',
        prompt: 'Say hello in 5 words',
    );

    echo "Response: {$response->text}\n";
    echo "Tokens: {$response->usage?->totalTokens}\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Example 6: OpenRouter (Access to 100+ models)
// ============================================================================
echo "6. OpenRouter (Multiple Models)\n";
echo str_repeat('-', 50) . "\n";

try {
    $openrouter = AnyLLM::openrouter(
        apiKey: $_ENV['OPENROUTER_API_KEY'] ?? null,
        config: [
            'options' => [
                'site_url' => 'https://example.com',
                'app_name' => 'My App',
            ],
        ]
    );

    // Use any model available on OpenRouter
    $response = $openrouter->generateText(
        model: 'anthropic/claude-3.5-sonnet',
        prompt: 'Say hello in 5 words',
    );

    echo "Response: {$response->text}\n";
    echo "Model used: {$response->model}\n";

    // OpenRouter-specific: Model routing with fallback
    echo "\nTrying with fallback routing...\n";
    $response = $openrouter->chat(
        model: 'openai/gpt-4',
        messages: [UserMessage::create('Hello!')],
        options: [
            'route' => 'fallback',
            'models' => ['openai/gpt-4', 'openai/gpt-3.5-turbo'],
        ],
    );
    echo "Fallback response: {$response->content}\n";

} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Example 7: Ollama (Local Models)
// ============================================================================
echo "7. Ollama (Local Models)\n";
echo str_repeat('-', 50) . "\n";

try {
    $ollama = AnyLLM::ollama(baseUri: 'http://localhost:11434/v1');

    // List available local models
    echo "Available models: " . json_encode($ollama->listModels()) . "\n";

    // Use a local model (make sure you have it installed: ollama pull llama3.2)
    $response = $ollama->generateText(
        model: 'llama3.2',
        prompt: 'Say hello in 5 words',
    );

    echo "Response: {$response->text}\n";
    echo "✓ Free, private, runs locally!\n";

} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
    echo "Tip: Make sure Ollama is running (ollama serve)\n";
}
echo "\n";

// ============================================================================
// Example 8: Provider Comparison - Same Question to All
// ============================================================================
echo "8. Provider Comparison\n";
echo str_repeat('-', 50) . "\n";

$question = "What is 2+2? Answer in one word.";
echo "Question: {$question}\n\n";

$providers = [
    'OpenAI' => fn() => AnyLLM::openai($_ENV['OPENAI_API_KEY'] ?? null),
    'Anthropic' => fn() => AnyLLM::anthropic($_ENV['ANTHROPIC_API_KEY'] ?? null),
    'Google' => fn() => AnyLLM::google($_ENV['GOOGLE_AI_API_KEY'] ?? null),
    'Mistral' => fn() => AnyLLM::mistral($_ENV['MISTRAL_API_KEY'] ?? null),
    'xAI' => fn() => AnyLLM::xai($_ENV['XAI_API_KEY'] ?? null),
    'OpenRouter' => fn() => AnyLLM::openrouter($_ENV['OPENROUTER_API_KEY'] ?? null),
    'Ollama' => fn() => AnyLLM::ollama(),
];

$models = [
    'OpenAI' => 'gpt-4o-mini',
    'Anthropic' => 'claude-haiku-4-5',
    'Google' => 'gemini-2.5-flash',
    'Mistral' => 'mistral-small-latest',
    'xAI' => 'grok-beta',
    'OpenRouter' => 'openai/gpt-3.5-turbo',
    'Ollama' => 'llama3.2',
];

foreach ($providers as $name => $providerFactory) {
    try {
        $provider = $providerFactory();
        $response = $provider->generateText(
            model: $models[$name],
            prompt: $question,
        );
        echo "{$name}: {$response->text}\n";
    } catch (\Exception $e) {
        echo "{$name}: ✗ {$e->getMessage()}\n";
    }
}

echo "\n";

// ============================================================================
// Example 9: OpenRouter Advanced Features
// ============================================================================
echo "9. OpenRouter Advanced Features\n";
echo str_repeat('-', 50) . "\n";

try {
    $openrouter = AnyLLM::openrouter(apiKey: $_ENV['OPENROUTER_API_KEY'] ?? null);

    // Provider preferences
    $response = $openrouter->chat(
        model: 'anthropic/claude-3.5-sonnet',
        messages: [UserMessage::create('Hello!')],
        options: [
            'provider' => [
                'order' => ['Anthropic', 'AWS', 'Google'],
                'require_parameters' => true,
            ],
        ],
    );

    echo "Response with provider preferences: {$response->content}\n";

    // Get detailed generation stats (including native token counts and cost)
    if ($response->id) {
        echo "\nFetching generation statistics...\n";
        // $stats = $openrouter->getGenerationStats($response->id);
        // echo "Native tokens: " . json_encode($stats['native_tokens_prompt'] ?? 0) . "\n";
        // echo "Cost: $" . ($stats['total_cost'] ?? 0) . "\n";
    }

} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Example 10: Streaming Comparison
// ============================================================================
echo "10. Streaming Example (OpenRouter)\n";
echo str_repeat('-', 50) . "\n";

try {
    $openrouter = AnyLLM::openrouter(apiKey: $_ENV['OPENROUTER_API_KEY'] ?? null);

    echo "Streaming: ";
    foreach ($openrouter->streamText(
        model: 'openai/gpt-3.5-turbo',
        prompt: 'Count from 1 to 5',
    ) as $chunk) {
        echo $chunk;
        flush();
    }
    echo "\n";

} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}

echo "\n" . str_repeat('=', 50) . "\n";
echo "✓ Demo complete!\n";
echo "\nKey Takeaways:\n";
echo "• Same interface works for all providers\n";
echo "• Easy to switch between providers\n";
echo "• OpenRouter gives access to 100+ models\n";
echo "• Ollama allows free local execution\n";
echo "• Mistral supports OCR capabilities\n";
echo "• Each provider has unique features\n";
