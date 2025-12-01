<?php

declare(strict_types=1);

namespace AnyLLM\Streaming;

use AnyLLM\Support\TokenCounter;

/**
 * Wraps a stream to count tokens in real-time.
 */
final class TokenCountingStream
{
    private int $totalTokens = 0;
    private int $estimatedTokens = 0;
    private string $model;

    public function __construct(
        private StreamController $controller,
        string $model = 'gpt-4',
    ) {
        $this->model = $model;

        // Intercept chunks to count tokens
        $this->controller->onChunk(function($content, $chunkNumber, $tokens) {
            if ($tokens > 0) {
                $this->totalTokens += $tokens;
            } else {
                // Estimate if not provided
                $estimated = TokenCounter::estimate($content, $this->model);
                $this->estimatedTokens += $estimated;
            }
        });
    }

    /**
     * Get total tokens (actual if available, estimated otherwise).
     */
    public function getTotalTokens(): int
    {
        return $this->totalTokens > 0 ? $this->totalTokens : $this->estimatedTokens;
    }

    /**
     * Get actual tokens (from API).
     */
    public function getActualTokens(): int
    {
        return $this->totalTokens;
    }

    /**
     * Get estimated tokens (from counter).
     */
    public function getEstimatedTokens(): int
    {
        return $this->estimatedTokens;
    }

    /**
     * Check if we have actual token counts.
     */
    public function hasActualTokens(): bool
    {
        return $this->totalTokens > 0;
    }

    /**
     * Get token statistics.
     */
    public function getStats(): array
    {
        return [
            'actual_tokens' => $this->totalTokens,
            'estimated_tokens' => $this->estimatedTokens,
            'total_tokens' => $this->getTotalTokens(),
            'has_actual' => $this->hasActualTokens(),
            'model' => $this->model,
        ];
    }
}

