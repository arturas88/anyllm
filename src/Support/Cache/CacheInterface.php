<?php

declare(strict_types=1);

namespace AnyLLM\Support\Cache;

interface CacheInterface
{
    /**
     * Retrieve an item from the cache.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Store an item in the cache.
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * Check if an item exists in the cache.
     */
    public function has(string $key): bool;

    /**
     * Delete an item from the cache.
     */
    public function delete(string $key): bool;

    /**
     * Clear all items from the cache.
     */
    public function clear(): bool;

    /**
     * Get multiple items from the cache.
     *
     * @param string[] $keys
     * @return array<string, mixed>
     */
    public function getMultiple(array $keys, mixed $default = null): array;

    /**
     * Store multiple items in the cache.
     *
     * @param array<string, mixed> $values
     */
    public function setMultiple(array $values, ?int $ttl = null): bool;

    /**
     * Delete multiple items from the cache.
     *
     * @param string[] $keys
     */
    public function deleteMultiple(array $keys): bool;

    /**
     * Get or store a value (retrieve from cache or compute and cache).
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed;

    /**
     * Store a value indefinitely.
     */
    public function forever(string $key, mixed $value): bool;

    /**
     * Increment a numeric value.
     */
    public function increment(string $key, int $value = 1): int|false;

    /**
     * Decrement a numeric value.
     */
    public function decrement(string $key, int $value = 1): int|false;
}

