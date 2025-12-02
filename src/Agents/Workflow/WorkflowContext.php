<?php

declare(strict_types=1);

namespace AnyLLM\Agents\Workflow;

final class WorkflowContext
{
    /**
     * @param array<string, mixed> $variables
     */
    public function __construct(
        private array $variables = [],
    ) {}

    public function setVariable(string $name, mixed $value): void
    {
        $this->variables[$name] = $value;
    }

    public function getVariable(string $name): mixed
    {
        return $this->variables[$name] ?? null;
    }

    public function hasVariable(string $name): bool
    {
        return array_key_exists($name, $this->variables);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAllVariables(): array
    {
        return $this->variables;
    }
}
