<?php

declare(strict_types=1);

namespace AnyLLM\Streaming;

final class StreamController
{
    private bool $paused = false;
    private bool $cancelled = false;
    private array $chunkCallbacks = [];
    private array $completeCallbacks = [];
    private array $errorCallbacks = [];
    private array $progressCallbacks = [];
    
    private int $chunksProcessed = 0;
    private int $totalTokens = 0;
    private string $accumulatedContent = '';
    private float $startTime = 0.0;

    public function __construct()
    {
        $this->startTime = microtime(true);
    }

    /**
     * Register a callback for each chunk.
     */
    public function onChunk(callable $callback): self
    {
        $this->chunkCallbacks[] = $callback;
        return $this;
    }

    /**
     * Register a callback for completion.
     */
    public function onComplete(callable $callback): self
    {
        $this->completeCallbacks[] = $callback;
        return $this;
    }

    /**
     * Register a callback for errors.
     */
    public function onError(callable $callback): self
    {
        $this->errorCallbacks[] = $callback;
        return $this;
    }

    /**
     * Register a callback for progress updates.
     */
    public function onProgress(callable $callback): self
    {
        $this->progressCallbacks[] = $callback;
        return $this;
    }

    /**
     * Pause the stream.
     */
    public function pause(): self
    {
        $this->paused = true;
        return $this;
    }

    /**
     * Resume the stream.
     */
    public function resume(): self
    {
        $this->paused = false;
        return $this;
    }

    /**
     * Cancel the stream.
     */
    public function cancel(): self
    {
        $this->cancelled = true;
        return $this;
    }

    /**
     * Check if paused.
     */
    public function isPaused(): bool
    {
        return $this->paused;
    }

    /**
     * Check if cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    /**
     * Process a chunk.
     */
    public function processChunk(string $content, int $tokens = 0): void
    {
        if ($this->cancelled) {
            return;
        }

        // Wait while paused
        while ($this->paused && ! $this->cancelled) {
            usleep(100000); // 100ms
        }

        $this->chunksProcessed++;
        $this->totalTokens += $tokens;
        $this->accumulatedContent .= $content;

        // Trigger chunk callbacks
        foreach ($this->chunkCallbacks as $callback) {
            $callback($content, $this->chunksProcessed, $tokens);
        }

        // Trigger progress callbacks
        foreach ($this->progressCallbacks as $callback) {
            $callback($this->getProgress());
        }
    }

    /**
     * Mark stream as complete.
     */
    public function complete(): void
    {
        if ($this->cancelled) {
            return;
        }

        foreach ($this->completeCallbacks as $callback) {
            $callback($this->accumulatedContent, $this->totalTokens, $this->chunksProcessed);
        }
    }

    /**
     * Handle an error.
     */
    public function error(\Throwable $error): void
    {
        foreach ($this->errorCallbacks as $callback) {
            $callback($error, $this->accumulatedContent, $this->chunksProcessed);
        }
    }

    /**
     * Get current progress information.
     */
    public function getProgress(): array
    {
        return [
            'chunks_processed' => $this->chunksProcessed,
            'total_tokens' => $this->totalTokens,
            'content_length' => strlen($this->accumulatedContent),
            'elapsed_seconds' => microtime(true) - $this->startTime,
            'paused' => $this->paused,
            'cancelled' => $this->cancelled,
        ];
    }

    /**
     * Get accumulated content.
     */
    public function getContent(): string
    {
        return $this->accumulatedContent;
    }

    /**
     * Get total tokens processed.
     */
    public function getTotalTokens(): int
    {
        return $this->totalTokens;
    }

    /**
     * Get elapsed time in seconds.
     */
    public function getElapsedTime(): float
    {
        return microtime(true) - $this->startTime;
    }

    /**
     * Reset the controller for reuse.
     */
    public function reset(): void
    {
        $this->paused = false;
        $this->cancelled = false;
        $this->chunksProcessed = 0;
        $this->totalTokens = 0;
        $this->accumulatedContent = '';
        $this->startTime = microtime(true);
    }
}

