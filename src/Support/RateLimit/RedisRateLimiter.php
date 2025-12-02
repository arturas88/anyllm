<?php

declare(strict_types=1);

namespace AnyLLM\Support\RateLimit;

use AnyLLM\Exceptions\RateLimitException;

final class RedisRateLimiter implements RateLimiterInterface
{
    private const KEY_PREFIX = 'anyllm:ratelimit:';

    public function __construct(
        private \Redis $redis,
    ) {}

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
        $attempts = $this->getAttempts($key);
        return $attempts >= $maxAttempts;
    }

    public function hit(string $key, int $decaySeconds = 60): int
    {
        $redisKey = $this->getKey($key);

        $attempts = $this->redis->incr($redisKey);
        if ($attempts === false) {
            return 0;
        }

        if ($attempts === 1) {
            $this->redis->expire($redisKey, $decaySeconds);
        }

        return (int) $attempts;
    }

    public function remaining(string $key, int $maxAttempts): int
    {
        $attempts = $this->getAttempts($key);
        return max(0, $maxAttempts - $attempts);
    }

    public function availableIn(string $key): int
    {
        $redisKey = $this->getKey($key);
        $ttl = $this->redis->ttl($redisKey);
        if ($ttl === false) {
            return 0;
        }

        return max(0, (int) $ttl);
    }

    public function clear(string $key): void
    {
        $this->redis->del($this->getKey($key));
    }

    public function resetAll(): void
    {
        $pattern = self::KEY_PREFIX . '*';
        $keys = $this->redis->keys($pattern);

        if (! empty($keys)) {
            $this->redis->del(...$keys);
        }
    }

    private function getAttempts(string $key): int
    {
        $value = $this->redis->get($this->getKey($key));
        if ($value === false || !is_string($value)) {
            return 0;
        }
        return (int) $value;
    }

    private function getKey(string $key): string
    {
        return self::KEY_PREFIX . $key;
    }
}
