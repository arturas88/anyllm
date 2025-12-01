<?php

declare(strict_types=1);

namespace AnyLLM\Responses\Parts;

final readonly class Usage
{
    public function __construct(
        public int $promptTokens,
        public int $completionTokens,
        public int $totalTokens,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            promptTokens: $data['prompt_tokens'] ?? $data['promptTokens'] ?? 0,
            completionTokens: $data['completion_tokens'] ?? $data['completionTokens'] ?? 0,
            totalTokens: $data['total_tokens'] ?? $data['totalTokens'] ?? 0,
        );
    }

    public function toArray(): array
    {
        return [
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
        ];
    }
}

