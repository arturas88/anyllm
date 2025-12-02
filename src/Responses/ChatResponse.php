<?php

declare(strict_types=1);

namespace AnyLLM\Responses;

use AnyLLM\Enums\FinishReason;
use AnyLLM\Messages\Message;
use AnyLLM\Responses\Parts\ToolCall;
use AnyLLM\Responses\Parts\Usage;

final class ChatResponse extends Response
{
    /**
     * @param array<Message> $messages
     * @param array<ToolCall> $toolCalls
     * @param array<string, mixed>|null $raw
     */
    public function __construct(
        public readonly string $content,
        public readonly array $messages = [],
        public readonly array $toolCalls = [],
        ?string $id = null,
        ?string $model = null,
        public readonly ?FinishReason $finishReason = null,
        ?Usage $usage = null,
        ?array $raw = null,
    ) {
        parent::__construct($id, $model, $usage, $raw);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): static
    {
        $toolCalls = [];
        if (isset($data['tool_calls']) && is_array($data['tool_calls'])) {
            $toolCalls = array_map(
                fn($tc) => ToolCall::fromArray($tc),
                $data['tool_calls']
            );
        }

        $content = $data['content'] ?? '';
        return new self(
            content: is_string($content) ? $content : (string) $content,
            messages: is_array($data['messages'] ?? null) ? $data['messages'] : [],
            toolCalls: $toolCalls,
            id: $data['id'] ?? null,
            model: $data['model'] ?? null,
            finishReason: isset($data['finish_reason'])
                ? FinishReason::tryFrom($data['finish_reason'])
                : null,
            usage: isset($data['usage']) ? Usage::fromArray($data['usage']) : null,
            raw: $data,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fake(array $data = []): self
    {
        return new self(
            content: $data['content'] ?? 'Fake chat response',
            messages: $data['messages'] ?? [],
            toolCalls: $data['tool_calls'] ?? [],
            id: $data['id'] ?? 'fake-id',
            model: $data['model'] ?? 'fake-model',
            usage: isset($data['usage']) ? Usage::fromArray($data['usage']) : Usage::fromArray([
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'total_tokens' => 30,
            ]),
        );
    }

    public function hasToolCalls(): bool
    {
        return count($this->toolCalls) > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'content' => $this->content,
            'tool_calls' => array_map(fn($tc) => $tc->toArray(), $this->toolCalls),
            'finish_reason' => $this->finishReason?->value,
        ];
    }
}
