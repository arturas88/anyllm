<?php

declare(strict_types=1);

namespace AnyLLM\Http;

use AnyLLM\Exceptions\ProviderException;
use AnyLLM\Exceptions\RateLimitException;

final class RetryHandler
{
    public function __construct(
        private int $maxRetries = 3,
        private int $initialDelayMs = 1000,
        private float $multiplier = 2.0,
        private int $maxDelayMs = 32000,
    ) {}

    /**
     * Execute an operation with automatic retry on failures.
     */
    public function retry(callable $operation): mixed
    {
        $attempt = 0;
        $delay = $this->initialDelayMs;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            try {
                return $operation();
            } catch (\Throwable $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt >= $this->maxRetries) {
                    break;
                }

                if (! $this->shouldRetry($e)) {
                    throw $e;
                }

                // Wait before retry
                usleep($delay * 1000);

                // Calculate next delay with exponential backoff
                $delay = (int) min($delay * $this->multiplier, $this->maxDelayMs);
            }
        }

        throw $lastException;
    }

    /**
     * Determine if an exception is retryable.
     */
    private function shouldRetry(\Throwable $e): bool
    {
        // Retry on rate limits and server errors (5xx)
        if ($e instanceof RateLimitException) {
            return true;
        }

        if ($e instanceof ProviderException) {
            $code = $e->getCode();
            return $code >= 500 && $code < 600;
        }

        // Retry on network errors
        if ($e instanceof \RuntimeException) {
            $message = strtolower($e->getMessage());
            return str_contains($message, 'timeout')
                || str_contains($message, 'connection')
                || str_contains($message, 'network');
        }

        return false;
    }

    public function withMaxRetries(int $maxRetries): self
    {
        return new self(
            maxRetries: $maxRetries,
            initialDelayMs: $this->initialDelayMs,
            multiplier: $this->multiplier,
            maxDelayMs: $this->maxDelayMs,
        );
    }

    public function withDelay(int $initialDelayMs, float $multiplier = 2.0): self
    {
        return new self(
            maxRetries: $this->maxRetries,
            initialDelayMs: $initialDelayMs,
            multiplier: $multiplier,
            maxDelayMs: $this->maxDelayMs,
        );
    }
}
