<?php

declare(strict_types=1);

namespace AnyLLM\Responses;

use AnyLLM\Enums\FinishReason;
use AnyLLM\Responses\Parts\Usage;

final class TextResponse extends Response
{
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

    public static function fromArray(array $data): static
    {
        return new self(
            text: $data['text'] ?? $data['content'] ?? '',
            id: $data['id'] ?? null,
            model: $data['model'] ?? null,
            finishReason: isset($data['finish_reason']) 
                ? FinishReason::tryFrom($data['finish_reason']) 
                : null,
            usage: isset($data['usage']) ? Usage::fromArray($data['usage']) : null,
            raw: $data,
        );
    }

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

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'text' => $this->text,
            'finish_reason' => $this->finishReason?->value,
        ];
    }
}

