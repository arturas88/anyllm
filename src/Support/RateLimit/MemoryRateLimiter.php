<?php

declare(strict_types=1);

namespace AnyLLM\Support\RateLimit;

use AnyLLM\Exceptions\RateLimitException;

final class MemoryRateLimiter implements RateLimiterInterface
{
    /** @var array<string, array{attempts: int, reset_at: int}> */
    private array $cache = [];

    public function attempt(string $key, callable $callback, int $maxAttempts, int $decaySeconds): mixed
    {
        if ($this->tooManyAttempts($key, $maxAttempts)) {
            $availableIn = $this->availableIn($key);
            throw new RateLimitException(
                "Too many attempts for key '{$key}'. Try again in {$availableIn} seconds.",
                429
            );
        }

        $this->hit($key, $decaySeconds);

        return $callback();
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $this->clearExpired();

        if (! isset($this->cache[$key])) {
            return false;
        }

        return $this->cache[$key]['attempts'] >= $maxAttempts;
    }

    public function hit(string $key, int $decaySeconds = 60): int
    {
        $this->clearExpired();

        if (! isset($this->cache[$key])) {
            $this->cache[$key] = [
                'attempts' => 0,
                'reset_at' => time() + $decaySeconds,
            ];
        }

        $this->cache[$key]['attempts']++;

        return $this->cache[$key]['attempts'];
    }

    public function remaining(string $key, int $maxAttempts): int
    {
        $this->clearExpired();

        if (! isset($this->cache[$key])) {
            return $maxAttempts;
        }

        return max(0, $maxAttempts - $this->cache[$key]['attempts']);
    }

    public function availableIn(string $key): int
    {
        $this->clearExpired();

        if (! isset($this->cache[$key])) {
            return 0;
        }

        return max(0, $this->cache[$key]['reset_at'] - time());
    }

    public function clear(string $key): void
    {
        unset($this->cache[$key]);
    }

    public function resetAll(): void
    {
        $this->cache = [];
    }

    private function clearExpired(): void
    {
        $now = time();

        foreach ($this->cache as $key => $data) {
            if ($data['reset_at'] <= $now) {
                unset($this->cache[$key]);
            }
        }
    }
}
