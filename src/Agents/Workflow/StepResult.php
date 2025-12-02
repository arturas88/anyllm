<?php

declare(strict_types=1);

namespace AnyLLM\Agents\Workflow;

use AnyLLM\Responses\Parts\Usage;

final readonly class StepResult
{
    public function __construct(
        public string $step,
        public mixed $output,
        public ?Usage $usage = null,
    ) {}
}
