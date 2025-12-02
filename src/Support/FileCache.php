<?php

declare(strict_types=1);

namespace AnyLLM\Support;

final class FileCache
{
    private string $cacheDir;
    private int $defaultTtl;

    public function __construct(
        ?string $cacheDir = null,
        int $defaultTtl = 3600,
    ) {
        $this->cacheDir = $cacheDir ?? sys_get_temp_dir() . '/anyllm-cache';
        $this->defaultTtl = $defaultTtl;

        $this->ensureCacheDirectory();
    }

    /**
     * Store an item in the cache.
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $ttl ??= $this->defaultTtl;
        $expiresAt = time() + $ttl;

        $data = [
            'value' => $value,
            'expires_at' => $expiresAt,
        ];

        $path = $this->getPath($key);
        $json = json_encode($data);

        return file_put_contents($path, $json, LOCK_EX) !== false;
    }

    /**
     * Retrieve an item from the cache.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $path = $this->getPath($key);

        if (! file_exists($path)) {
            return $default;
        }

        $json = file_get_contents($path);
        $data = json_decode($json, true);

        if (! $data || ! isset($data['expires_at'])) {
            return $default;
        }

        // Check if expired
        if (time() > $data['expires_at']) {
            $this->delete($key);
            return $default;
        }

        return $data['value'] ?? $default;
    }

    /**
     * Check if an item exists in cache and is not expired.
     */
    public function has(string $key): bool
    {
        $path = $this->getPath($key);

        if (! file_exists($path)) {
            return false;
        }

        $json = file_get_contents($path);
        $data = json_decode($json, true);

        if (! $data || ! isset($data['expires_at'])) {
            return false;
        }

        if (time() > $data['expires_at']) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    /**
     * Delete an item from the cache.
     */
    public function delete(string $key): bool
    {
        $path = $this->getPath($key);

        if (file_exists($path)) {
            return unlink($path);
        }

        return false;
    }

    /**
     * Get multiple items from the cache.
     *
     * @param string[] $keys
     * @return array<string, mixed>
     */
    public function getMultiple(array $keys, mixed $default = null): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    /**
     * Store multiple items in the cache.
     *
     * @param array<string, mixed> $values
     */
    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    /**
     * Delete multiple items from the cache.
     *
     * @param string[] $keys
     */
    public function deleteMultiple(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    /**
     * Increment a numeric value.
     */
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

    /**
     * Decrement a numeric value.
     */
    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->increment($key, -$value);
    }

    /**
     * Clear all items from the cache.
     */
    public function clear(): bool
    {
        $files = glob($this->cacheDir . '/*');

        if ($files === false) {
            return false;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }

    /**
     * Get or store a value (retrieve from cache or compute and cache).
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Store a value indefinitely (very long TTL).
     */
    public function forever(string $key, mixed $value): bool
    {
        return $this->set($key, $value, 315360000); // 10 years
    }

    /**
     * Remove expired items from cache.
     */
    public function prune(): int
    {
        $pruned = 0;
        $files = glob($this->cacheDir . '/*');

        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            if (! is_file($file)) {
                continue;
            }

            $json = file_get_contents($file);
            $data = json_decode($json, true);

            if (! $data || ! isset($data['expires_at'])) {
                continue;
            }

            if (time() > $data['expires_at']) {
                unlink($file);
                $pruned++;
            }
        }

        return $pruned;
    }

    /**
     * Get cache statistics.
     */
    public function stats(): array
    {
        $files = glob($this->cacheDir . '/*');

        if ($files === false) {
            return [
                'total_items' => 0,
                'expired_items' => 0,
                'valid_items' => 0,
                'total_size' => 0,
            ];
        }

        $total = 0;
        $expired = 0;
        $size = 0;

        foreach ($files as $file) {
            if (! is_file($file)) {
                continue;
            }

            $total++;
            $size += filesize($file);

            $json = file_get_contents($file);
            $data = json_decode($json, true);

            if ($data && isset($data['expires_at']) && time() > $data['expires_at']) {
                $expired++;
            }
        }

        return [
            'total_items' => $total,
            'expired_items' => $expired,
            'valid_items' => $total - $expired,
            'total_size' => $size,
        ];
    }

    /**
     * Get the file path for a cache key.
     */
    private function getPath(string $key): string
    {
        $hash = md5($key);
        return $this->cacheDir . '/' . $hash . '.cache';
    }

    /**
     * Ensure the cache directory exists.
     */
    private function ensureCacheDirectory(): void
    {
        if (! is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0o755, true);
        }
    }

    /**
     * Create a cache key from multiple parts.
     */
    public static function key(...$parts): string
    {
        return implode(':', $parts);
    }
}
