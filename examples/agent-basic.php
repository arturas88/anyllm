<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AnyLLM\AnyLLM;
use AnyLLM\Agents\Agent;

echo "=== Agent Basic Example ===\n\n";

// Initialize the LLM provider
$llm = AnyLLM::openai(apiKey: $_ENV['OPENAI_API_KEY'] ?? 'your-api-key');

// Create a simple agent with a system prompt
$agent = Agent::create(
    provider: $llm,
    model: 'gpt-4o-mini',
    systemPrompt: 'You are a helpful assistant that provides clear and concise answers.',
);

// Run the agent with a simple query
echo "Question: What are the main benefits of using PHP?\n";
$result = $agent->run('What are the main benefits of using PHP?');

echo "Agent Response:\n{$result->content}\n\n";
echo "Iterations: {$result->iterations}\n";
echo "Tool Executions: " . count($result->toolExecutions) . "\n";
if ($result->usage) {
    echo "Tokens Used: {$result->usage->totalTokens}\n";
}

echo "\n=== Example 2: Multi-turn Conversation ===\n\n";

// Agents can handle follow-up questions naturally
$result2 = $agent->run('Can you explain the first benefit in more detail?');

echo "Follow-up Response:\n{$result2->content}\n\n";

echo "=== Example 3: Agent with Context ===\n\n";

// Create an agent specialized for code review
$codeReviewAgent = Agent::create(
    provider: $llm,
    model: 'gpt-4o-mini',
    systemPrompt: 'You are an expert code reviewer. Analyze code and provide constructive feedback.',
);

$code = <<<'PHP'
function calculateTotal($items) {
    $total = 0;
    foreach ($items as $item) {
        $total += $item['price'];
    }
    return $total;
}
PHP;

$review = $codeReviewAgent->run("Review this PHP code:\n\n{$code}");

echo "Code Review:\n{$review->content}\n\n";

echo "All examples completed!\n";

