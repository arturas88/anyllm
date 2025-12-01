<?php

declare(strict_types=1);

namespace AnyLLM\Agents\Workflow;

use AnyLLM\StructuredOutput\Schema;

final readonly class WorkflowStep
{
    /**
     * @param array<Tool>|null $tools
     */
    public function __construct(
        public string $name,
        public string $prompt,
        public string $model,
        public ?Schema $outputSchema = null,
        public ?array $tools = null,
    ) {}
}

