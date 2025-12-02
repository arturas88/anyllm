<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AnyLLM\AnyLLM;
use AnyLLM\Agents\Agent;
use AnyLLM\Tools\Tool;
use AnyLLM\StructuredOutput\Attributes\Description;

echo "=== Agent with Tools Example ===\n\n";

// Initialize the LLM provider
$llm = AnyLLM::openai(apiKey: $_ENV['OPENAI_API_KEY'] ?? 'your-api-key');

// Define tools that the agent can use
$calculator = Tool::fromCallable(
    name: 'calculator',
    handler: function (
        #[Description('Mathematical expression to evaluate (e.g., "2 + 2", "10 * 5")')]
        string $expression
    ): array {
        // Simple calculator - in production, use a proper math parser
        $expression = str_replace([' ', '×', '÷'], ['', '*', '/'], $expression);

        // Security: Only allow safe mathematical expressions
        if (!preg_match('/^[\d\+\-\*\/\(\)\.\s]+$/', $expression)) {
            return ['error' => 'Invalid expression', 'result' => null];
        }

        try {
            eval("\$result = {$expression};");
            return ['expression' => $expression, 'result' => $result];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage(), 'result' => null];
        }
    },
    description: 'Performs basic mathematical calculations. Accepts expressions like "2 + 2", "10 * 5", etc.',
);

$weatherTool = Tool::fromCallable(
    name: 'get_weather',
    handler: function (
        #[Description('City name')]
        string $city,
        #[Description('Temperature unit: celsius or fahrenheit')]
        string $unit = 'celsius'
    ): array {
        // Simulated weather API
        $temperatures = [
            'london' => ['celsius' => 15, 'fahrenheit' => 59],
            'paris' => ['celsius' => 18, 'fahrenheit' => 64],
            'new york' => ['celsius' => 22, 'fahrenheit' => 72],
            'tokyo' => ['celsius' => 25, 'fahrenheit' => 77],
        ];

        $cityKey = strtolower($city);
        $temp = $temperatures[$cityKey] ?? ['celsius' => 20, 'fahrenheit' => 68];

        return [
            'city' => ucfirst($city),
            'temperature' => $temp[$unit] ?? $temp['celsius'],
            'unit' => $unit,
            'condition' => 'sunny',
            'humidity' => rand(40, 80),
        ];
    },
    description: 'Get current weather information for a city',
);

$databaseTool = Tool::fromCallable(
    name: 'query_database',
    handler: function (
        #[Description('SQL query to execute')]
        string $query
    ): array {
        // Simulated database query
        // In production, this would connect to a real database
        $results = [
            'SELECT * FROM users LIMIT 5' => [
                ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
                ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'],
                ['id' => 3, 'name' => 'Charlie', 'email' => 'charlie@example.com'],
            ],
        ];

        return [
            'query' => $query,
            'results' => $results[$query] ?? [],
            'rowCount' => count($results[$query] ?? []),
        ];
    },
    description: 'Execute a SQL query and return results',
);

// Create an agent with tools
$agent = Agent::create(
    provider: $llm,
    model: 'gpt-4o-mini',
    systemPrompt: 'You are a helpful assistant with access to various tools. Use them when needed to help the user.',
)->withTools($calculator, $weatherTool, $databaseTool);

echo "=== Example 1: Agent Using Calculator Tool ===\n\n";
echo "Question: What is 25 * 4 + 100?\n";
$result1 = $agent->run('What is 25 * 4 + 100?');

echo "Agent Response:\n{$result1->content}\n\n";
echo "Tool Executions: " . count($result1->toolExecutions) . "\n";
foreach ($result1->toolExecutions as $execution) {
    echo "  - Tool: {$execution->name}\n";
    echo "    Arguments: " . json_encode($execution->arguments) . "\n";
    echo "    Result: " . json_encode($execution->result) . "\n";
    echo "    Duration: " . number_format($execution->duration * 1000, 2) . "ms\n";
}
echo "\n";

echo "=== Example 2: Agent Using Multiple Tools ===\n\n";
echo "Question: What's the weather in London and Paris? Also calculate 15 * 8.\n";
$result2 = $agent->run("What's the weather in London and Paris? Also calculate 15 * 8.");

echo "Agent Response:\n{$result2->content}\n\n";
echo "Tool Executions: " . count($result2->toolExecutions) . "\n";
foreach ($result2->toolExecutions as $execution) {
    echo "  - Tool: {$execution->name}\n";
}
echo "\n";

echo "=== Example 3: Complex Multi-Step Task ===\n\n";
echo "Question: Get the weather for New York, then calculate the temperature in Fahrenheit times 2.\n";
$result3 = $agent->run('Get the weather for New York, then calculate the temperature in Fahrenheit times 2.');

echo "Agent Response:\n{$result3->content}\n\n";
echo "Iterations: {$result3->iterations}\n";
echo "Total Tool Executions: " . count($result3->toolExecutions) . "\n";
if ($result3->usage) {
    echo "Total Tokens: {$result3->usage->totalTokens}\n";
}

echo "\nAll examples completed!\n";
