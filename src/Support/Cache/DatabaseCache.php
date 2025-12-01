<?php

declare(strict_types=1);

namespace AnyLLM\Support\Cache;

final class DatabaseCache implements CacheInterface
{
    public function __construct(
        private \PDO $pdo,
        private string $table = 'llm_cache',
        private int $defaultTtl = 3600,
    ) {
        $this->ensureTable();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->pruneExpired();

        $stmt = $this->pdo->prepare(
            "SELECT value FROM {$this->table} 
             WHERE cache_key = :key AND expires_at > NOW()"
        );
        $stmt->execute(['key' => $key]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (! $result) {
            return $default;
        }

        return unserialize($result['value']);
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);
        $serialized = serialize($value);

        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->table} (cache_key, value, expires_at, created_at, updated_at)
             VALUES (:key, :value, :expires_at, NOW(), NOW())
             ON DUPLICATE KEY UPDATE 
                value = :value, 
                expires_at = :expires_at,
                updated_at = NOW()"
        );

        return $stmt->execute([
            'key' => $key,
            'value' => $serialized,
            'expires_at' => $expiresAt,
        ]);
    }

    public function has(string $key): bool
    {
        $this->pruneExpired();

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM {$this->table} 
             WHERE cache_key = :key AND expires_at > NOW()"
        );
        $stmt->execute(['key' => $key]);

        return $stmt->fetchColumn() > 0;
    }

    public function delete(string $key): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM {$this->table} WHERE cache_key = :key"
        );

        return $stmt->execute(['key' => $key]);
    }

    public function clear(): bool
    {
        return $this->pdo->exec("TRUNCATE TABLE {$this->table}") !== false;
    }

    public function getMultiple(array $keys, mixed $default = null): array
    {
        if (empty($keys)) {
            return [];
        }

        $this->pruneExpired();

        $placeholders = str_repeat('?,', count($keys) - 1) . '?';
        $stmt = $this->pdo->prepare(
            "SELECT cache_key, value FROM {$this->table} 
             WHERE cache_key IN ({$placeholders}) AND expires_at > NOW()"
        );
        $stmt->execute($keys);

        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[$row['cache_key']] = unserialize($row['value']);
        }

        // Fill in defaults for missing keys
        foreach ($keys as $key) {
            if (! isset($results[$key])) {
                $results[$key] = $default;
            }
        }

        return $results;
    }

    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            if (! $this->set($key, $value, $ttl)) {
                return false;
            }
        }

        return true;
    }

    public function deleteMultiple(array $keys): bool
    {
        if (empty($keys)) {
            return true;
        }

        $placeholders = str_repeat('?,', count($keys) - 1) . '?';
        $stmt = $this->pdo->prepare(
            "DELETE FROM {$this->table} WHERE cache_key IN ({$placeholders})"
        );

        return $stmt->execute($keys);
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

    private function pruneExpired(): void
    {
        // Only prune occasionally (10% chance)
        if (rand(1, 10) !== 1) {
            return;
        }

        $this->pdo->exec(
            "DELETE FROM {$this->table} WHERE expires_at <= NOW()"
        );
    }

    private function ensureTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->table} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                cache_key VARCHAR(255) NOT NULL UNIQUE,
                value LONGTEXT NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_expires_at (expires_at),
                INDEX idx_key_expires (cache_key, expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}

