<?php

declare(strict_types=1);

namespace AnyLLM\Agents;

use AnyLLM\Messages\Message;
use AnyLLM\Responses\Parts\Usage;

final readonly class AgentResult
{
    /**
     * @param array<Message> $messages
     * @param array<ToolExecution> $toolExecutions
     */
    public function __construct(
        public string $content,
        public array $messages,
        public array $toolExecutions,
        public int $iterations,
        public ?Usage $usage = null,
    ) {}
}
