<?php

declare(strict_types=1);

namespace AnyLLM\Support\Cache;

final class RedisCache implements CacheInterface
{
    private const KEY_PREFIX = 'anyllm:cache:';

    public function __construct(
        private \Redis $redis,
        private int $defaultTtl = 3600,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($this->prefixKey($key));

        if ($value === false) {
            return $default;
        }

        if (!is_string($value)) {
            return $default;
        }

        $unserialized = unserialize($value);
        return $unserialized !== false ? $unserialized : $default;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $ttl ??= $this->defaultTtl;
        $prefixedKey = $this->prefixKey($key);
        $serialized = serialize($value);

        if ($ttl > 0) {
            return $this->redis->setex($prefixedKey, $ttl, $serialized);
        }

        return $this->redis->set($prefixedKey, $serialized);
    }

    public function has(string $key): bool
    {
        return $this->redis->exists($this->prefixKey($key)) > 0;
    }

    public function delete(string $key): bool
    {
        return $this->redis->del($this->prefixKey($key)) > 0;
    }

    public function clear(): bool
    {
        $pattern = self::KEY_PREFIX . '*';
        $keys = $this->redis->keys($pattern);

        if (empty($keys)) {
            return true;
        }

        return $this->redis->del(...$keys) > 0;
    }

    public function getMultiple(array $keys, mixed $default = null): array
    {
        $prefixedKeys = array_map(fn($k) => $this->prefixKey($k), $keys);
        $values = $this->redis->mGet($prefixedKeys);

        $result = [];
        foreach ($keys as $i => $key) {
            $result[$key] = $values[$i] !== false
                ? unserialize($values[$i])
                : $default;
        }

        return $result;
    }

    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        $ttl ??= $this->defaultTtl;

        foreach ($values as $key => $value) {
            if (! $this->set($key, $value, $ttl)) {
                return false;
            }
        }

        return true;
    }

    public function deleteMultiple(array $keys): bool
    {
        $prefixedKeys = array_map(fn($k) => $this->prefixKey($k), $keys);
        return $this->redis->del(...$prefixedKeys) > 0;
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
        $prefixedKey = $this->prefixKey($key);
        $serialized = serialize($value);

        return $this->redis->set($prefixedKey, $serialized);
    }

    public function increment(string $key, int $value = 1): int|false
    {
        $result = $this->redis->incrBy($this->prefixKey($key), $value);
        return $result !== false ? (int) $result : false;
    }

    public function decrement(string $key, int $value = 1): int|false
    {
        $result = $this->redis->decrBy($this->prefixKey($key), $value);
        return $result !== false ? (int) $result : false;
    }

    private function prefixKey(string $key): string
    {
        return self::KEY_PREFIX . $key;
    }
}
