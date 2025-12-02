<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AnyLLM\AnyLLM;
use AnyLLM\Messages\{SystemMessage, UserMessage};
use AnyLLM\StructuredOutput\Schema;
use AnyLLM\StructuredOutput\Attributes\{Description, ArrayOf};
use AnyLLM\Tools\Tool;
use AnyLLM\Agents\Agent;

// Example 1: Simple text generation
echo "=== Example 1: Simple Text Generation ===\n";

$llm = AnyLLM::openai(apiKey: $_ENV['OPENAI_API_KEY'] ?? 'your-api-key');

$response = $llm->generateText(
    model: 'gpt-4o-mini',
    prompt: 'Explain what PHP is in one sentence.',
);

echo "Response: {$response->text}\n";
echo "Tokens used: {$response->usage?->totalTokens}\n\n";

// Example 2: Chat with conversation history
echo "=== Example 2: Chat Conversation ===\n";

$response = $llm->chat(
    model: 'gpt-4o-mini',
    messages: [
        SystemMessage::create('You are a helpful PHP expert.'),
        UserMessage::create('What are PHP 8.2 features?'),
    ],
);

echo "Assistant: {$response->content}\n\n";

// Example 3: Structured Output with Schema
echo "=== Example 3: Structured Output ===\n";

class Recipe
{
    #[Description('The name of the recipe')]
    public string $name;

    #[Description('Cooking time in minutes')]
    public int $cookingTimeMinutes;

    #[ArrayOf(Ingredient::class)]
    #[Description('List of ingredients')]
    public array $ingredients;

    /** @var array<string> */
    #[Description('Cooking steps')]
    public array $steps;
}

class Ingredient
{
    public string $name;
    public string $amount;
    public ?string $unit = null;
}

$response = $llm->generateObject(
    model: 'gpt-4o-mini',
    prompt: 'Generate a simple recipe for chocolate chip cookies',
    schema: Recipe::class,
);

$recipe = $response->object;
echo "Recipe: {$recipe->name}\n";
echo "Cooking time: {$recipe->cookingTimeMinutes} minutes\n";
echo "Ingredients:\n";
foreach ($recipe->ingredients as $ingredient) {
    echo "  - {$ingredient->amount} {$ingredient->unit} {$ingredient->name}\n";
}
echo "\n";

// Example 4: Tool/Function Calling
echo "=== Example 4: Tool Calling ===\n";

$tools = [
    Tool::fromCallable(
        name: 'get_weather',
        handler: function (
            #[Description('City name')]
            string $city,
            #[Description('Temperature unit')]
            string $unit = 'celsius'
        ): array {
            // Simulated weather API call
            return [
                'city' => $city,
                'temperature' => 22,
                'unit' => $unit,
                'condition' => 'sunny',
            ];
        },
        description: 'Get current weather for a city',
    ),
];

$response = $llm->chat(
    model: 'gpt-4o-mini',
    messages: [UserMessage::create('What is the weather in London?')],
    tools: $tools,
);

if ($response->hasToolCalls()) {
    echo "Tool calls detected!\n";
    foreach ($response->toolCalls as $toolCall) {
        echo "  Tool: {$toolCall->name}\n";
        echo "  Arguments: " . json_encode($toolCall->arguments) . "\n";
    }
}
echo "\n";

// Example 5: Agent with Tools
echo "=== Example 5: Agent System ===\n";

$agent = Agent::create(
    provider: $llm,
    model: 'gpt-4o-mini',
    systemPrompt: 'You are a helpful weather assistant.',
)->withTools(...$tools);

$result = $agent->run('What is the weather in Paris and London?');

echo "Agent response: {$result->content}\n";
echo "Tool executions: " . count($result->toolExecutions) . "\n";
echo "Iterations: {$result->iterations}\n\n";

// Example 6: Provider Switching (same code, different provider)
echo "=== Example 6: Provider Switching ===\n";

try {
    // Skip if no API key is set
    if (empty($_ENV['ANTHROPIC_API_KEY'])) {
        echo "Skipped (ANTHROPIC_API_KEY not set in .env)\n\n";
    } else {
        $anthropic = AnyLLM::anthropic(apiKey: $_ENV['ANTHROPIC_API_KEY']);

        $response = $anthropic->generateText(
            model: 'claude-haiku-4-5',
            prompt: 'Explain what PHP is in one sentence.',
        );

        echo "Claude says: {$response->text}\n\n";
    }
} catch (\AnyLLM\Exceptions\AuthenticationException $e) {
    echo "Skipped (Invalid Anthropic API key)\n\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// Example 7: Streaming
echo "=== Example 7: Streaming ===\n";

echo "Streaming response: ";
foreach ($llm->streamText(
    model: 'gpt-4o-mini',
    prompt: 'Count from 1 to 5 slowly.',
) as $chunk) {
    echo $chunk;
    flush();
}
echo "\n\n";

echo "All examples completed!\n";
