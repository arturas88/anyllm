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

    /**
     * @return array<string, mixed>
     */
    public function toOpenAIFormat(): array
    {
        return [
            'role' => 'tool',
            'content' => $this->content,
            'tool_call_id' => $this->toolCallId,
        ];
    }
}
