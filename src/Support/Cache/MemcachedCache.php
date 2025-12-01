<?php

declare(strict_types=1);

namespace AnyLLM\Support\Cache;

final class MemcachedCache implements CacheInterface
{
    private const KEY_PREFIX = 'anyllm_cache_';

    public function __construct(
        private \Memcached $memcached,
        private int $defaultTtl = 3600,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->memcached->get($this->prefixKey($key));
        
        if ($this->memcached->getResultCode() === \Memcached::RES_NOTFOUND) {
            return $default;
        }

        return $value;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $prefixedKey = $this->prefixKey($key);

        return $this->memcached->set($prefixedKey, $value, $ttl);
    }

    public function has(string $key): bool
    {
        $this->memcached->get($this->prefixKey($key));
        return $this->memcached->getResultCode() !== \Memcached::RES_NOTFOUND;
    }

    public function delete(string $key): bool
    {
        return $this->memcached->delete($this->prefixKey($key));
    }

    public function clear(): bool
    {
        return $this->memcached->flush();
    }

    public function getMultiple(array $keys, mixed $default = null): array
    {
        $prefixedKeys = array_map(fn($k) => $this->prefixKey($k), $keys);
        $values = $this->memcached->getMulti($prefixedKeys);

        $result = [];
        foreach ($keys as $key) {
            $prefixedKey = $this->prefixKey($key);
            $result[$key] = $values[$prefixedKey] ?? $default;
        }

        return $result;
    }

    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;

        $prefixedValues = [];
        foreach ($values as $key => $value) {
            $prefixedValues[$this->prefixKey($key)] = $value;
        }

        return $this->memcached->setMulti($prefixedValues, $ttl);
    }

    public function deleteMultiple(array $keys): bool
    {
        $prefixedKeys = array_map(fn($k) => $this->prefixKey($k), $keys);
        $results = $this->memcached->deleteMulti($prefixedKeys);

        // deleteMulti returns an array of results
        return ! in_array(false, $results, true);
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
        return $this->memcached->set($this->prefixKey($key), $value, 0);
    }

    public function increment(string $key, int $value = 1): int|false
    {
        $result = $this->memcached->increment($this->prefixKey($key), $value);
        return $result !== false ? (int) $result : false;
    }

    public function decrement(string $key, int $value = 1): int|false
    {
        $result = $this->memcached->decrement($this->prefixKey($key), $value);
        return $result !== false ? (int) $result : false;
    }

    private function prefixKey(string $key): string
    {
        return self::KEY_PREFIX . $key;
    }
}

