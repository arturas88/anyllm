<?php

declare(strict_types=1);

namespace AnyLLM\Support\Cache;

/**
 * In-memory array cache (useful for testing).
 */
final class ArrayCache implements CacheInterface
{
    /** @var array<string, array{value: mixed, expires_at: int}> */
    private array $cache = [];

    public function __construct(
        private int $defaultTtl = 3600,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $this->clearExpired();

        if (! isset($this->cache[$key])) {
            return $default;
        }

        return $this->cache[$key]['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $ttl ??= $this->defaultTtl;

        $this->cache[$key] = [
            'value' => $value,
            'expires_at' => time() + $ttl,
        ];

        return true;
    }

    public function has(string $key): bool
    {
        $this->clearExpired();
        return isset($this->cache[$key]);
    }

    public function delete(string $key): bool
    {
        unset($this->cache[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->cache = [];
        return true;
    }

    public function getMultiple(array $keys, mixed $default = null): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    public function deleteMultiple(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->set($key, $value, 315360000); // 10 years
    }

    public function increment(string $key, int $value = 1): int|false
    {
        $current = $this->get($key, 0);

        if (! is_numeric($current)) {
            return false;
        }

        $newValue = (int) $current + $value;
        $this->set($key, $newValue);

        return $newValue;
    }

    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->increment($key, -$value);
    }

    private function clearExpired(): void
    {
        $now = time();
        foreach ($this->cache as $key => $data) {
            if ($data['expires_at'] <= $now) {
                unset($this->cache[$key]);
            }
        }
    }
}
