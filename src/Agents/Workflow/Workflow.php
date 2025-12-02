<?php

declare(strict_types=1);

namespace AnyLLM\Agents\Workflow;

use AnyLLM\Contracts\ProviderInterface;
use AnyLLM\StructuredOutput\Schema;
use AnyLLM\Tools\Tool;

final class Workflow
{
    /** @var array<WorkflowStep> */
    private array $steps = [];
    private array $variables = [];

    public function __construct(
        private readonly ProviderInterface $provider,
        private readonly string $defaultModel,
    ) {}

    public static function create(
        ProviderInterface $provider,
        string $defaultModel,
    ): self {
        return new self($provider, $defaultModel);
    }

    public function addStep(
        string $name,
        string $prompt,
        ?string $model = null,
        ?Schema $outputSchema = null,
        ?array $tools = null,
    ): self {
        $this->steps[] = new WorkflowStep(
            name: $name,
            prompt: $prompt,
            model: $model ?? $this->defaultModel,
            outputSchema: $outputSchema,
            tools: $tools,
        );

        return $this;
    }

    public function withVariable(string $name, mixed $value): self
    {
        $this->variables[$name] = $value;
        return $this;
    }

    public function run(array $input = []): WorkflowResult
    {
        $context = new WorkflowContext(
            variables: [...$this->variables, ...$input],
        );

        $stepResults = [];

        foreach ($this->steps as $step) {
            $result = $this->executeStep($step, $context);
            $stepResults[$step->name] = $result;
            $context->setVariable($step->name, $result->output);
        }

        return new WorkflowResult(
            stepResults: $stepResults,
            finalOutput: end($stepResults)->output,
            context: $context,
        );
    }

    private function executeStep(WorkflowStep $step, WorkflowContext $context): StepResult
    {
        // Interpolate variables in prompt
        $prompt = $this->interpolate($step->prompt, $context);

        if ($step->outputSchema) {
            $response = $this->provider->generateObject(
                model: $step->model,
                prompt: $prompt,
                schema: $step->outputSchema,
            );

            return new StepResult(
                step: $step->name,
                output: $response->object,
                usage: $response->usage,
            );
        }

        $response = $this->provider->generateText(
            model: $step->model,
            prompt: $prompt,
        );

        return new StepResult(
            step: $step->name,
            output: $response->text,
            usage: $response->usage,
        );
    }

    private function interpolate(string $template, WorkflowContext $context): string
    {
        return preg_replace_callback(
            '/\{\{\s*(\w+)\s*\}\}/',
            fn($matches) => (string) $context->getVariable($matches[1]),
            $template,
        );
    }
}
