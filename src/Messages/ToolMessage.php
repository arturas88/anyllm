<?php

declare(strict_types=1);

namespace AnyLLM\Messages;

use AnyLLM\Enums\Role;

final class ToolMessage extends Message
{
    public function __construct(
        string $content,
        public readonly string $toolCallId,
        ?string $name = null,
    ) {
        parent::__construct(Role::Tool, $content, $name);
    }

    protected static function getRole(): Role
    {
        return Role::Tool;
    }

    public static function create(string $toolCallId, string $content): self
    {
        return new self(
            content: $content,
            toolCallId: $toolCallId,
        );
    }

    public function toOpenAIFormat(): array
    {
        return [
            'role' => 'tool',
            'content' => $this->content,
            'tool_call_id' => $this->toolCallId,
        ];
    }
}

