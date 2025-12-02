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

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $promptTokens = $data['prompt_tokens'] ?? $data['promptTokens'] ?? 0;
        $completionTokens = $data['completion_tokens'] ?? $data['completionTokens'] ?? 0;
        $totalTokens = $data['total_tokens'] ?? $data['totalTokens'] ?? 0;
        $promptTokensValue = is_int($promptTokens) ? $promptTokens : (is_numeric($promptTokens) ? (int) $promptTokens : 0);
        $completionTokensValue = is_int($completionTokens) ? $completionTokens : (is_numeric($completionTokens) ? (int) $completionTokens : 0);
        $totalTokensValue = is_int($totalTokens) ? $totalTokens : (is_numeric($totalTokens) ? (int) $totalTokens : 0);
        return new self(
            promptTokens: $promptTokensValue,
            completionTokens: $completionTokensValue,
            totalTokens: $totalTokensValue,
        );
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
        ];
    }
}
