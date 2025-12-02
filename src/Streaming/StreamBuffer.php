<?php

declare(strict_types=1);

namespace AnyLLM\Streaming;

final class StreamBuffer
{
    private array $buffer = [];
    private int $maxSize;
    private int $flushThreshold;

    public function __construct(
        int $maxSize = 1000,
        int $flushThreshold = 100,
    ) {
        $this->maxSize = $maxSize;
        $this->flushThreshold = $flushThreshold;
    }

    /**
     * Add a chunk to the buffer.
     */
    public function add(string $content, int $tokens = 0): void
    {
        $this->buffer[] = [
            'content' => $content,
            'tokens' => $tokens,
            'timestamp' => microtime(true),
        ];

        // Auto-flush if threshold reached
        if (count($this->buffer) >= $this->flushThreshold) {
            $this->flush();
        }
    }

    /**
     * Get buffered content.
     */
    public function getContent(): string
    {
        return implode('', array_column($this->buffer, 'content'));
    }

    /**
     * Get total tokens in buffer.
     */
    public function getTotalTokens(): int
    {
        return array_sum(array_column($this->buffer, 'tokens'));
    }

    /**
     * Get buffer size.
     */
    public function size(): int
    {
        return count($this->buffer);
    }

    /**
     * Check if buffer is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->buffer);
    }

    /**
     * Check if buffer is full.
     */
    public function isFull(): bool
    {
        return count($this->buffer) >= $this->maxSize;
    }

    /**
     * Flush the buffer and return contents.
     */
    public function flush(): array
    {
        $contents = $this->buffer;
        $this->buffer = [];
        return $contents;
    }

    /**
     * Get the last N chunks.
     */
    public function getLast(int $n): array
    {
        return array_slice($this->buffer, -$n);
    }

    /**
     * Get chunks within a time window.
     */
    public function getRecent(float $seconds): array
    {
        $threshold = microtime(true) - $seconds;

        return array_filter($this->buffer, function ($chunk) use ($threshold) {
            return $chunk['timestamp'] >= $threshold;
        });
    }

    /**
     * Clear the buffer.
     */
    public function clear(): void
    {
        $this->buffer = [];
    }
}
