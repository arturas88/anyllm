<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AnyLLM\AnyLLM;
use AnyLLM\Messages\{SystemMessage, UserMessage};

echo "=== OpenRouter Advanced Features Demo ===\n\n";

/**
 * OpenRouter provides access to 100+ models through a unified interface
 * with advanced routing and fallback capabilities.
 *
 * @see https://openrouter.ai/docs/api/reference/overview
 */

$openrouter = AnyLLM::openrouter(
    apiKey: $_ENV['OPENROUTER_API_KEY'] ?? 'your-key',
    config: [
        'options' => [
            'site_url' => 'https://yourapp.com',
            'app_name' => 'Your App Name',
        ],
    ]
);

// ============================================================================
// Feature 1: Access to 100+ Models
// ============================================================================
echo "1. Access to Multiple Providers\n";
echo str_repeat('-', 50) . "\n";

$models = [
    'openai/gpt-4o' => 'OpenAI GPT-4o',
    'anthropic/claude-3.5-sonnet' => 'Claude 3.5 Sonnet',
    'google/gemini-3-pro-preview' => 'Gemini 3 Pro',
    'meta-llama/llama-3.3-70b-instruct' => 'Llama 3.3 70B',
    'mistralai/mistral-large' => 'Mistral Large',
    'x-ai/grok-beta' => 'Grok Beta',
];

foreach ($models as $model => $name) {
    try {
        $response = $openrouter->generateText(
            model: $model,
            prompt: 'Say hello in 3 words',
        );
        echo "✓ {$name}: {$response->text}\n";
    } catch (\Exception $e) {
        echo "✗ {$name}: {$e->getMessage()}\n";
    }
}
echo "\n";

// ============================================================================
// Feature 2: Model Fallback Routing
// ============================================================================
echo "2. Automatic Fallback Routing\n";
echo str_repeat('-', 50) . "\n";
echo "If primary model fails, automatically try backups\n\n";

try {
    $response = $openrouter->chat(
        model: 'openai/gpt-4', // Primary model
        messages: [UserMessage::create('Explain quantum computing briefly')],
        options: [
            'route' => 'fallback',
            'models' => [
                'openai/gpt-4',           // Try first
                'anthropic/claude-3.5-sonnet', // Then this
                'openai/gpt-3.5-turbo',   // Finally this (max 3 models)
            ],
        ],
    );

    echo "Response: {$response->content}\n";
    echo "Model used: {$response->model}\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Feature 3: Provider Preferences
// ============================================================================
echo "3. Provider Routing & Preferences\n";
echo str_repeat('-', 50) . "\n";
echo "Control which infrastructure provider serves your request\n\n";

try {
    $response = $openrouter->chat(
        model: 'anthropic/claude-3.5-sonnet',
        messages: [UserMessage::create('Hello!')],
        options: [
            'provider' => [
                'order' => ['Anthropic', 'AWS', 'Google'], // Prefer official API
                'allow_fallbacks' => true,
                'require_parameters' => true, // Only use providers supporting all params
                'data_collection' => 'deny', // Opt out of data collection
            ],
        ],
    );

    echo "Response: {$response->content}\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Feature 4: Message Transforms
// ============================================================================
echo "4. Message Transforms\n";
echo str_repeat('-', 50) . "\n";
echo "Automatically optimize prompts for different models\n\n";

try {
    $response = $openrouter->chat(
        model: 'meta-llama/llama-3.3-70b-instruct',
        messages: [
            SystemMessage::create('You are a helpful assistant'),
            UserMessage::create('Tell me a joke'),
        ],
        options: [
            'transforms' => ['middle-out'], // Optimize token usage
        ],
    );

    echo "Response: {$response->content}\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Feature 5: Advanced Parameters
// ============================================================================
echo "5. Advanced Model Parameters\n";
echo str_repeat('-', 50) . "\n";

try {
    $response = $openrouter->chat(
        model: 'openai/gpt-4o-mini',
        messages: [UserMessage::create('Generate creative text about AI')],
        temperature: 0.9,
        maxTokens: 150,
        options: [
            'top_p' => 0.9,
            'top_k' => 50,
            'frequency_penalty' => 0.5,
            'presence_penalty' => 0.5,
            'repetition_penalty' => 1.1,
            'seed' => 12345, // Reproducible outputs
        ],
    );

    echo "Response: {$response->content}\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Feature 6: Cost Tracking & Statistics
// ============================================================================
echo "6. Generation Statistics & Cost Tracking\n";
echo str_repeat('-', 50) . "\n";

try {
    $response = $openrouter->chat(
        model: 'openai/gpt-3.5-turbo',
        messages: [UserMessage::create('Hello! How are you?')],
    );

    echo "Generation ID: {$response->id}\n";
    echo "Model: {$response->model}\n";
    echo "Tokens: {$response->usage?->totalTokens}\n";

    // Fetch detailed stats (native token counts, actual cost)
    echo "\nFetching detailed statistics...\n";
    try {
        $stats = $openrouter->getGenerationStats($response->id);
        echo "Native prompt tokens: " . ($stats['native_tokens_prompt'] ?? 'N/A') . "\n";
        echo "Native completion tokens: " . ($stats['native_tokens_completion'] ?? 'N/A') . "\n";
        echo "Actual cost: $" . ($stats['total_cost'] ?? 'N/A') . "\n";
        echo "Provider: " . ($stats['provider_name'] ?? 'N/A') . "\n";
    } catch (\Exception $e) {
        echo "Stats not yet available (may take a moment to process)\n";
    }
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Feature 7: Streaming with Multiple Models
// ============================================================================
echo "7. Streaming Responses\n";
echo str_repeat('-', 50) . "\n";

try {
    echo "Question: Count from 1 to 10 slowly\n";
    echo "Streaming: ";

    foreach ($openrouter->streamChat(
        model: 'openai/gpt-3.5-turbo',
        messages: [UserMessage::create('Count from 1 to 10 slowly')],
    ) as $chunk) {
        echo $chunk;
        flush();
        usleep(50000); // Slow down for demo
    }
    echo "\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Feature 8: Structured Output (JSON)
// ============================================================================
echo "8. Structured JSON Output\n";
echo str_repeat('-', 50) . "\n";

try {
    $response = $openrouter->chat(
        model: 'openai/gpt-4o-mini',
        messages: [UserMessage::create('List 3 programming languages with their year of creation. Return your response as a JSON object with a "languages" array.')],
        options: [
            'response_format' => ['type' => 'json_object'],
        ],
    );

    echo "JSON Response:\n";
    $data = json_decode($response->content, true);
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Summary
// ============================================================================
echo str_repeat('=', 50) . "\n";
echo "✓ OpenRouter Advanced Features Demo Complete!\n\n";

echo "Key Features:\n";
echo "• Access to 100+ models from multiple providers\n";
echo "• Automatic fallback routing\n";
echo "• Provider preferences & routing\n";
echo "• Message transforms for optimization\n";
echo "• Advanced parameter support\n";
echo "• Detailed cost tracking & statistics\n";
echo "• Native token counting\n";
echo "• Streaming support\n";
echo "• JSON structured outputs\n\n";

echo "Perfect for:\n";
echo "• Multi-model applications\n";
echo "• High availability systems\n";
echo "• Cost optimization\n";
echo "• A/B testing different models\n";
echo "• Accessing latest models without code changes\n";
