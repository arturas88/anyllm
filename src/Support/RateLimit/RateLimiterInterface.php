<?php

declare(strict_types=1);

namespace AnyLLM\Support\RateLimit;

interface RateLimiterInterface
{
    /**
     * Attempt to execute a rate-limited operation.
     *
     * @throws \AnyLLM\Exceptions\RateLimitException
     */
    public function attempt(string $key, callable $callback, int $maxAttempts, int $decaySeconds): mixed;

    /**
     * Check if a key has remaining attempts.
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool;

    /**
     * Increment attempts for a key.
     */
    public function hit(string $key, int $decaySeconds = 60): int;

    /**
     * Get remaining attempts for a key.
     */
    public function remaining(string $key, int $maxAttempts): int;

    /**
     * Get seconds until key is available again.
     */
    public function availableIn(string $key): int;

    /**
     * Clear attempts for a key.
     */
    public function clear(string $key): void;

    /**
     * Reset all rate limits.
     */
    public function resetAll(): void;
}

