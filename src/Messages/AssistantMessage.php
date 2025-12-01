<?php

declare(strict_types=1);

namespace AnyLLM\Messages;

use AnyLLM\Enums\Role;
use AnyLLM\Responses\ChatResponse;
use AnyLLM\Responses\Parts\ToolCall;

final class AssistantMessage extends Message
{
    /**
     * @param array<ToolCall> $toolCalls
     */
    public function __construct(
        array|string $content,
        public readonly array $toolCalls = [],
        ?string $name = null,
    ) {
        parent::__construct(Role::Assistant, $content, $name);
    }

    protected static function getRole(): Role
    {
        return Role::Assistant;
    }

    public static function fromResponse(ChatResponse $response): self
    {
        return new self(
            content: $response->content,
            toolCalls: $response->toolCalls,
        );
    }

    public function toOpenAIFormat(): array
    {
        $formatted = parent::toOpenAIFormat();

        if (count($this->toolCalls) > 0) {
            $formatted['tool_calls'] = array_map(
                fn (ToolCall $tc) => [
                    'id' => $tc->id,
                    'type' => 'function',
                    'function' => [
                        'name' => $tc->name,
                        'arguments' => json_encode($tc->arguments),
                    ],
                ],
                $this->toolCalls
            );
        }

        return $formatted;
    }
}

