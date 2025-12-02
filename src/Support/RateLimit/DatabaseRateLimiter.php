<?php

declare(strict_types=1);

namespace AnyLLM\Support\RateLimit;

use AnyLLM\Exceptions\RateLimitException;

final class DatabaseRateLimiter implements RateLimiterInterface
{
    public function __construct(
        private \PDO $pdo,
        private string $table = 'llm_rate_limits',
    ) {
        $this->ensureTable();
    }

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
        $attempts = $this->getAttempts($key);
        return $attempts >= $maxAttempts;
    }

    public function hit(string $key, int $decaySeconds = 60): int
    {
        $this->clearExpired();

        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->table} (rate_key, attempts, reset_at, created_at, updated_at)
             VALUES (:key, 1, :reset_at, NOW(), NOW())
             ON DUPLICATE KEY UPDATE 
                attempts = attempts + 1,
                updated_at = NOW()"
        );

        $resetAt = date('Y-m-d H:i:s', time() + $decaySeconds);
        $stmt->execute([
            'key' => $key,
            'reset_at' => $resetAt,
        ]);

        return $this->getAttempts($key);
    }

    public function remaining(string $key, int $maxAttempts): int
    {
        $this->clearExpired();
        $attempts = $this->getAttempts($key);
        return max(0, $maxAttempts - $attempts);
    }

    public function availableIn(string $key): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT TIMESTAMPDIFF(SECOND, NOW(), reset_at) as seconds
             FROM {$this->table}
             WHERE rate_key = :key AND reset_at > NOW()"
        );
        $stmt->execute(['key' => $key]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ? max(0, (int) $result['seconds']) : 0;
    }

    public function clear(string $key): void
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM {$this->table} WHERE rate_key = :key"
        );
        $stmt->execute(['key' => $key]);
    }

    public function resetAll(): void
    {
        $this->pdo->exec("TRUNCATE TABLE {$this->table}");
    }

    private function getAttempts(string $key): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT attempts FROM {$this->table} 
             WHERE rate_key = :key AND reset_at > NOW()"
        );
        $stmt->execute(['key' => $key]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ? (int) $result['attempts'] : 0;
    }

    private function clearExpired(): void
    {
        $this->pdo->exec(
            "DELETE FROM {$this->table} WHERE reset_at <= NOW()"
        );
    }

    private function ensureTable(): void
    {
        // Create table if it doesn't exist
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->table} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                rate_key VARCHAR(255) NOT NULL UNIQUE,
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                reset_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_reset_at (reset_at),
                INDEX idx_key_reset (rate_key, reset_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
