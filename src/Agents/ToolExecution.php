<?php

declare(strict_types=1);

namespace AnyLLM\Agents;

final readonly class ToolExecution
{
    public function __construct(
        public string $name,
        public array $arguments,
        public mixed $result,
        public float $duration,
    ) {}
}
