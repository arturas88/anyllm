<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AnyLLM\AnyLLM;
use AnyLLM\Agents\Workflow\Workflow;
use AnyLLM\Agents\Workflow\StepResult;
use AnyLLM\Agents\Workflow\WorkflowContext;
use AnyLLM\StructuredOutput\Schema;
use AnyLLM\StructuredOutput\Attributes\{Description, ArrayOf};

echo "=== Workflow Human In The Loop Example ===\n\n";

// Initialize the LLM provider
$llm = AnyLLM::openai(apiKey: $_ENV['OPENAI_API_KEY'] ?? 'your-api-key');

// Example 1: Content Approval Workflow
echo "=== Example 1: Content Approval Workflow ===\n\n";

class ContentDraft
{
    #[Description('Draft title')]
    public string $title;

    #[Description('Draft content')]
    public string $content;

    #[Description('Suggested tags')]
    #[ArrayOf('string')]
    public array $tags;
}

$approvalWorkflow = Workflow::create(
    provider: $llm,
    defaultModel: 'gpt-4o-mini',
)
    ->addStep(
        name: 'draft',
        prompt: 'Create a blog post draft about: {{topic}}',
        outputSchema: Schema::fromClass(ContentDraft::class),
    )
    ->addStep(
        name: 'review',
        prompt: 'Review and improve the draft. Add more details and ensure quality. Draft: {{draft}}',
        outputSchema: Schema::fromClass(ContentDraft::class),
    )
    ->addStep(
        name: 'finalize',
        prompt: 'Create the final version based on the reviewed draft. Reviewed: {{review}}',
        outputSchema: Schema::fromClass(ContentDraft::class),
    )
    ->withBeforeStep(function (string $stepName, string $prompt, WorkflowContext $context): bool {
        // Require approval before executing certain steps
        $stepsRequiringApproval = ['review', 'finalize'];

        if (in_array($stepName, $stepsRequiringApproval)) {
            echo "\n⚠️  STEP APPROVAL REQUIRED\n";
            echo "Step: {$stepName}\n";
            echo "Prompt: " . substr($prompt, 0, 200) . "...\n";

            // Show context
            if ($stepName === 'review') {
                $draft = $context->getVariable('draft');
                if ($draft) {
                    echo "\nDraft to review:\n";
                    if (is_object($draft) && isset($draft->title)) {
                        echo "Title: {$draft->title}\n";
                        echo "Content: " . substr($draft->content, 0, 200) . "...\n";
                    }
                }
            }

            echo "\nDo you want to proceed with this step? (yes/no): ";

            // In a real application, this would be proper user input
            // For demo, we'll auto-approve
            echo "yes (auto-approved for demo)\n";
            return true;
        }

        return true; // Auto-approve other steps
    })
    ->withAfterStep(function (string $stepName, StepResult $result, WorkflowContext $context): ?StepResult {
        // Allow human review and modification of step results
        if ($stepName === 'draft') {
            echo "\n📝 DRAFT CREATED - Review Opportunity\n";
            $draft = $result->output;
            if (is_object($draft) && isset($draft->title)) {
                echo "Title: {$draft->title}\n";
                echo "Content Preview: " . substr($draft->content, 0, 300) . "...\n";
                echo "\nWould you like to modify the draft? (yes/no): ";

                // In a real application, user could provide modifications
                echo "no (using original for demo)\n";
            }
        }

        // Return null to use original result, or return modified StepResult
        return null;
    });

$topic = 'Introduction to Machine Learning';

echo "Topic: {$topic}\n\n";

$result = $approvalWorkflow->run(['topic' => $topic]);

echo "\nFinal Content:\n";
$final = $result->stepResults['finalize']->output;
if (is_object($final) && isset($final->title)) {
    echo "Title: {$final->title}\n\n";
    echo "Content:\n{$final->content}\n\n";
    echo "Tags: " . implode(', ', $final->tags) . "\n";
}

// Example 2: Quality Gate Workflow
echo "\n\n=== Example 2: Quality Gate Workflow ===\n\n";

class CodeReview
{
    #[Description('Code quality score (1-10)')]
    public int $qualityScore;

    #[Description('List of issues found')]
    #[ArrayOf('string')]
    public array $issues;

    #[Description('Suggestions for improvement')]
    #[ArrayOf('string')]
    public array $suggestions;
}

$qualityWorkflow = Workflow::create(
    provider: $llm,
    defaultModel: 'gpt-4o-mini',
)
    ->addStep(
        name: 'analyze',
        prompt: 'Analyze this code: {{code}}. Identify potential issues and improvements.',
        outputSchema: Schema::fromClass(CodeReview::class),
    )
    ->addStep(
        name: 'suggest_fixes',
        prompt: 'Based on the analysis, suggest specific fixes. Analysis: {{analyze}}',
    )
    ->addStep(
        name: 'generate_improved',
        prompt: 'Generate an improved version of the code. Original: {{code}}, Analysis: {{analyze}}, Suggestions: {{suggest_fixes}}',
    )
    ->withAfterStep(function (string $stepName, StepResult $result, WorkflowContext $context): ?StepResult {
        // Quality gate: Check if quality score meets threshold
        if ($stepName === 'analyze') {
            $review = $result->output;
            if (is_object($review) && isset($review->qualityScore)) {
                echo "\n📊 Quality Score: {$review->qualityScore}/10\n";

                $threshold = 7;
                if ($review->qualityScore < $threshold) {
                    echo "⚠️  Quality score below threshold ({$threshold})\n";
                    echo "Issues found: " . count($review->issues) . "\n";
                    echo "\nDo you want to continue despite low quality score? (yes/no): ";

                    // In a real application, this would wait for user input
                    echo "yes (continuing for demo)\n";
                } else {
                    echo "✅ Quality score meets threshold. Proceeding...\n";
                }
            }
        }

        return null;
    });

$code = <<<'PHP'
    function calculateTotal($items) {
        $total = 0;
        foreach ($items as $item) {
            $total += $item['price'];
        }
        return $total;
    }
    PHP;

echo "Original Code:\n{$code}\n\n";

$codeResult = $qualityWorkflow->run(['code' => $code]);

$review = $codeResult->stepResults['analyze']->output;
if (is_object($review) && isset($review->qualityScore)) {
    echo "\nCode Review:\n";
    echo "Quality Score: {$review->qualityScore}/10\n";
    echo "Issues:\n";
    foreach ($review->issues as $issue) {
        echo "  - {$issue}\n";
    }
    echo "\nSuggestions:\n";
    foreach ($review->suggestions as $suggestion) {
        echo "  - {$suggestion}\n";
    }
}

echo "\n\nImproved Code:\n{$codeResult->stepResults['generate_improved']->output}\n";

// Example 3: Conditional Workflow with Human Decision
echo "\n\n=== Example 3: Conditional Workflow ===\n\n";

$conditionalWorkflow = Workflow::create(
    provider: $llm,
    defaultModel: 'gpt-4o-mini',
)
    ->addStep(
        name: 'analyze_request',
        prompt: 'Analyze this user request and determine if it requires human approval: {{request}}',
    )
    ->withBeforeStep(function (string $stepName, string $prompt, WorkflowContext $context): bool {
        // Check if this is a sensitive request
        $request = $context->getVariable('request') ?? '';

        $sensitiveKeywords = ['delete', 'remove', 'cancel', 'refund', 'modify payment'];
        $requiresApproval = false;

        foreach ($sensitiveKeywords as $keyword) {
            if (stripos($request, $keyword) !== false) {
                $requiresApproval = true;
                break;
            }
        }

        if ($requiresApproval && $stepName !== 'analyze_request') {
            echo "\n⚠️  SENSITIVE OPERATION DETECTED\n";
            echo "Request: {$request}\n";
            echo "This operation requires human approval.\n";
            echo "\nDo you want to proceed? (yes/no): ";

            // In a real application, this would wait for approval
            echo "yes (auto-approved for demo)\n";
        }

        return true;
    });

$sensitiveRequest = 'Please delete my account and all associated data';

echo "Request: {$sensitiveRequest}\n\n";

$requestResult = $conditionalWorkflow->run(['request' => $sensitiveRequest]);

echo "Analysis:\n{$requestResult->stepResults['analyze_request']->output}\n";

echo "\n\n=== Summary ===\n";
echo "Human In The Loop features in Workflows:\n";
echo "1. ✅ Before Step: Request approval before executing specific steps\n";
echo "2. ✅ After Step: Review and modify step results\n";
echo "3. ✅ Quality Gates: Enforce quality thresholds\n";
echo "4. ✅ Conditional Execution: Skip steps based on human decisions\n";
echo "\nUse cases:\n";
echo "- Content moderation and approval workflows\n";
echo "- Code review and quality assurance\n";
echo "- Financial transaction approvals\n";
echo "- Data processing pipelines with human oversight\n";

echo "\nAll examples completed!\n";
