<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AnyLLM\AnyLLM;
use AnyLLM\Agents\Workflow\Workflow;
use AnyLLM\StructuredOutput\Schema;
use AnyLLM\StructuredOutput\Attributes\{Description, ArrayOf};

echo "=== Workflow Basic Example ===\n\n";

// Initialize the LLM provider
$llm = AnyLLM::openai(apiKey: $_ENV['OPENAI_API_KEY'] ?? 'your-api-key');

// Example 1: Simple multi-step workflow
echo "=== Example 1: Simple Text Processing Workflow ===\n\n";

$workflow1 = Workflow::create(
    provider: $llm,
    defaultModel: 'gpt-4o-mini',
)
    ->addStep(
        name: 'analyze',
        prompt: 'Analyze the following text and identify the main topic: {{input}}',
    )
    ->addStep(
        name: 'summarize',
        prompt: 'Based on the analysis from the previous step, create a concise summary. Analysis: {{analyze}}',
    )
    ->addStep(
        name: 'recommend',
        prompt: 'Based on the summary, provide 3 actionable recommendations. Summary: {{summarize}}',
    );

$input = 'PHP 8.3 introduces several new features including typed class constants, readonly anonymous classes, and improved JIT compilation. These features enhance type safety, performance, and developer experience.';

echo "Input: {$input}\n\n";

$result1 = $workflow1->run(['input' => $input]);

echo "Step Results:\n";
foreach ($result1->stepResults as $stepName => $stepResult) {
    echo "\n--- {$stepName} ---\n";
    echo $stepResult->output . "\n";
}

echo "\nFinal Output:\n{$result1->finalOutput}\n\n";

// Example 2: Workflow with structured output
echo "=== Example 2: Structured Output Workflow ===\n\n";

class ProductAnalysis
{
    #[Description('The product name')]
    public string $productName;

    #[Description('List of key features')]
    #[ArrayOf('string')]
    public array $features;

    #[Description('Target audience')]
    public string $targetAudience;

    #[Description('Price range')]
    public string $priceRange;
}

class MarketingPlan
{
    #[Description('Marketing strategy')]
    public string $strategy;

    #[Description('Key marketing channels')]
    #[ArrayOf('string')]
    public array $channels;

    #[Description('Budget estimate')]
    public string $budget;
}

$workflow2 = Workflow::create(
    provider: $llm,
    defaultModel: 'gpt-4o-mini',
)
    ->addStep(
        name: 'analyze_product',
        prompt: 'Analyze this product description: {{product_description}}',
        outputSchema: Schema::fromClass(ProductAnalysis::class),
    )
    ->addStep(
        name: 'create_marketing_plan',
        prompt: 'Create a marketing plan for this product: {{analyze_product}}. Focus on the target audience: {{analyze_product.targetAudience}}',
        outputSchema: Schema::fromClass(MarketingPlan::class),
    );

$productDesc = 'A mobile app that helps users track their daily water intake with personalized reminders and hydration goals.';

echo "Product Description: {$productDesc}\n\n";

$result2 = $workflow2->run(['product_description' => $productDesc]);

echo "Product Analysis:\n";
$analysis = $result2->stepResults['analyze_product']->output;
echo "  Product: {$analysis->productName}\n";
echo "  Features: " . implode(', ', $analysis->features) . "\n";
echo "  Target Audience: {$analysis->targetAudience}\n";
echo "  Price Range: {$analysis->priceRange}\n\n";

echo "Marketing Plan:\n";
$plan = $result2->stepResults['create_marketing_plan']->output;
echo "  Strategy: {$plan->strategy}\n";
echo "  Channels: " . implode(', ', $plan->channels) . "\n";
echo "  Budget: {$plan->budget}\n\n";

// Example 3: Workflow with variables
echo "=== Example 3: Workflow with Pre-set Variables ===\n\n";

$workflow3 = Workflow::create(
    provider: $llm,
    defaultModel: 'gpt-4o-mini',
)
    ->withVariable('company', 'TechCorp')
    ->withVariable('industry', 'Software Development')
    ->addStep(
        name: 'generate_proposal',
        prompt: 'Generate a project proposal for {{company}} in the {{industry}} industry. Project: {{project_name}}',
    )
    ->addStep(
        name: 'create_timeline',
        prompt: 'Based on the proposal, create a project timeline. Proposal: {{generate_proposal}}',
    );

$result3 = $workflow3->run(['project_name' => 'Build a customer portal']);

echo "Proposal:\n{$result3->stepResults['generate_proposal']->output}\n\n";
echo "Timeline:\n{$result3->stepResults['create_timeline']->output}\n\n";

echo "All examples completed!\n";
