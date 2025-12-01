<?php

declare(strict_types=1);

namespace AnyLLM\Agents\Workflow;

final readonly class WorkflowResult
{
    /**
     * @param array<string, StepResult> $stepResults
     */
    public function __construct(
        public array $stepResults,
        public mixed $finalOutput,
        public WorkflowContext $context,
    ) {}
}

