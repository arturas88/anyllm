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
    /** @var array<string, mixed> */
    private array $variables = [];
    /** @var callable|null */
    private $beforeStepCallback = null;
    /** @var callable|null */
    private $afterStepCallback = null;

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

    /**
     * @param Schema<mixed>|null $outputSchema
     * @param array<Tool>|null $tools
     */
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

    /**
     * Set a callback that will be called before each step execution.
     * The callback receives: string $stepName, string $prompt, WorkflowContext $context
     * Return true to proceed, false to skip, or throw an exception to abort.
     *
     * @param callable(string, string, WorkflowContext): bool $callback
     */
    public function withBeforeStep(callable $callback): self
    {
        $clone = clone $this;
        $clone->beforeStepCallback = $callback;
        return $clone;
    }

    /**
     * Set a callback that will be called after each step execution.
     * The callback receives: string $stepName, StepResult $result, WorkflowContext $context
     * Return a modified StepResult, or null to use original.
     *
     * @param callable(string, StepResult, WorkflowContext): StepResult|null $callback
     */
    public function withAfterStep(callable $callback): self
    {
        $clone = clone $this;
        $clone->afterStepCallback = $callback;
        return $clone;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function run(array $input = []): WorkflowResult
    {
        $context = new WorkflowContext(
            variables: [...$this->variables, ...$input],
        );

        $stepResults = [];

        foreach ($this->steps as $step) {
            // Human in the loop: request approval before step execution
            if ($this->beforeStepCallback !== null) {
                $prompt = $this->interpolate($step->prompt, $context);
                $shouldProceed = ($this->beforeStepCallback)($step->name, $prompt, $context);
                if ($shouldProceed === false) {
                    // Skip this step - set variable to null so subsequent steps don't break
                    $context->setVariable($step->name, null);
                    // Create a StepResult indicating it was skipped
                    $stepResults[$step->name] = new StepResult(
                        step: $step->name,
                        output: null,
                        usage: null,
                    );
                    continue;
                }
            }

            $result = $this->executeStep($step, $context);

            // Human in the loop: allow review/modification after step execution
            if ($this->afterStepCallback !== null) {
                $modifiedResult = ($this->afterStepCallback)($step->name, $result, $context);
                if ($modifiedResult !== null) {
                    $result = $modifiedResult;
                }
            }

            $stepResults[$step->name] = $result;
            $context->setVariable($step->name, $result->output);
        }

        // Check if stepResults is empty before accessing
        if (empty($stepResults)) {
            throw new \RuntimeException('Workflow completed with no executed steps. All steps were skipped or workflow has no steps.');
        }

        $lastStep = end($stepResults);
        return new WorkflowResult(
            stepResults: $stepResults,
            finalOutput: $lastStep->output,
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
        $result = preg_replace_callback(
            '/\{\{\s*(\w+(?:\.\w+)*)\s*\}\}/',
            function ($matches) use ($context) {
                $varPath = $matches[1];
                $value = $this->getVariableValue($context, $varPath);
                return $this->valueToString($value);
            },
            $template,
        );
        return $result ?? $template;
    }

    private function getVariableValue(WorkflowContext $context, string $varPath): mixed
    {
        $parts = explode('.', $varPath);
        $varName = $parts[0];
        $value = $context->getVariable($varName);

        if ($value === null) {
            return null;
        }

        // Handle nested property access (e.g., analyze.insights)
        for ($i = 1; $i < count($parts); $i++) {
            if (is_object($value)) {
                $property = $parts[$i];
                if (property_exists($value, $property)) {
                    $value = $value->$property;
                } else {
                    // Try camelCase to snake_case conversion
                    $snakeProperty = $this->camelToSnake($property);
                    if (property_exists($value, $snakeProperty)) {
                        $value = $value->$snakeProperty;
                    } else {
                        return null;
                    }
                }
            } elseif (is_array($value) && isset($value[$parts[$i]])) {
                $value = $value[$parts[$i]];
            } else {
                return null;
            }
        }

        return $value;
    }

    private function valueToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value) || is_numeric($value) || is_bool($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return $json !== false ? $json : '[]';
        }

        if (is_object($value)) {
            // Try to convert object to JSON
            $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($json !== false) {
                return $json;
            }
            // If JSON encoding fails, try to get a string representation
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }
            // Fallback: serialize object properties
            $json = json_encode((array) $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return $json !== false ? $json : '{}';
        }

        return '';
    }

    private function camelToSnake(string $str): string
    {
        $replaced = preg_replace('/([a-z])([A-Z])/', '$1_$2', $str);
        return strtolower($replaced ?? $str);
    }
}
