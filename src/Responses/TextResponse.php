<?php

declare(strict_types=1);

namespace AnyLLM\Responses;

use AnyLLM\Enums\FinishReason;
use AnyLLM\Responses\Parts\Usage;

final class TextResponse extends Response
{
    /**
     * @param array<string, mixed>|null $raw
     */
    public function __construct(
        public readonly string $text,
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
        $text = $data['text'] ?? $data['content'] ?? '';
        $textValue = is_string($text) ? $text : (is_scalar($text) ? (string) $text : '');
        return new self(
            text: $textValue,
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
            text: $data['text'] ?? 'Fake response',
            id: $data['id'] ?? 'fake-id',
            model: $data['model'] ?? 'fake-model',
            usage: isset($data['usage']) ? Usage::fromArray($data['usage']) : Usage::fromArray([
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'total_tokens' => 30,
            ]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'text' => $this->text,
            'finish_reason' => $this->finishReason?->value,
        ];
    }
}
