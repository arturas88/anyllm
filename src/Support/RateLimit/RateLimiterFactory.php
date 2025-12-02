<?php

declare(strict_types=1);

namespace AnyLLM\Support\RateLimit;

use AnyLLM\Exceptions\ValidationException;

final class RateLimiterFactory
{
    /**
     * Create a rate limiter instance from configuration.
     */
    public static function create(string $driver, array $config = []): RateLimiterInterface
    {
        return match ($driver) {
            'memory' => new MemoryRateLimiter(),
            'redis' => self::createRedisLimiter($config),
            'database' => self::createDatabaseLimiter($config),
            default => throw new ValidationException("Unsupported rate limiter driver: {$driver}"),
        };
    }

    private static function createRedisLimiter(array $config): RedisRateLimiter
    {
        $redis = new \Redis();

        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 6379;
        $timeout = $config['timeout'] ?? 0.0;

        $redis->connect($host, $port, $timeout);

        if (isset($config['password'])) {
            $redis->auth($config['password']);
        }

        if (isset($config['database'])) {
            $redis->select($config['database']);
        }

        return new RedisRateLimiter($redis);
    }

    private static function createDatabaseLimiter(array $config): DatabaseRateLimiter
    {
        $dsn = $config['dsn'] ?? self::buildDsn($config);
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        $options = $config['options'] ?? [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ];

        $pdo = new \PDO($dsn, $username, $password, $options);

        return new DatabaseRateLimiter(
            pdo: $pdo,
            table: $config['table'] ?? 'llm_rate_limits',
        );
    }

    private static function buildDsn(array $config): string
    {
        $driver = $config['driver'] ?? 'mysql';
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 3306;
        $database = $config['database'] ?? '';

        return match ($driver) {
            'mysql' => "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
            'pgsql' => "pgsql:host={$host};port={$port};dbname={$database}",
            'sqlite' => "sqlite:{$database}",
            default => throw new ValidationException("Unsupported database driver: {$driver}"),
        };
    }
}
