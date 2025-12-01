<?php

declare(strict_types=1);

namespace AnyLLM\Support\Cache;

use AnyLLM\Exceptions\ValidationException;
use AnyLLM\Support\FileCache;

final class CacheFactory
{
    /**
     * Create a cache instance from configuration.
     */
    public static function create(string $driver, array $config = []): CacheInterface
    {
        return match ($driver) {
            'redis' => self::createRedisCache($config),
            'memcached' => self::createMemcachedCache($config),
            'database' => self::createDatabaseCache($config),
            'file' => self::createFileCache($config),
            'array' => new ArrayCache($config['default_ttl'] ?? 3600),
            default => throw new ValidationException("Unsupported cache driver: {$driver}"),
        };
    }

    private static function createRedisCache(array $config): RedisCache
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

        return new RedisCache(
            redis: $redis,
            defaultTtl: $config['default_ttl'] ?? 3600,
        );
    }

    private static function createMemcachedCache(array $config): MemcachedCache
    {
        $memcached = new \Memcached();
        
        $servers = $config['servers'] ?? [['127.0.0.1', 11211]];
        
        foreach ($servers as $server) {
            $host = $server[0] ?? $server['host'] ?? '127.0.0.1';
            $port = $server[1] ?? $server['port'] ?? 11211;
            $weight = $server[2] ?? $server['weight'] ?? 0;
            
            $memcached->addServer($host, $port, $weight);
        }

        if (isset($config['username']) && isset($config['password'])) {
            $memcached->setSaslAuthData($config['username'], $config['password']);
        }

        return new MemcachedCache(
            memcached: $memcached,
            defaultTtl: $config['default_ttl'] ?? 3600,
        );
    }

    private static function createDatabaseCache(array $config): DatabaseCache
    {
        $dsn = $config['dsn'] ?? self::buildDsn($config);
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        $options = $config['options'] ?? [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ];

        $pdo = new \PDO($dsn, $username, $password, $options);

        return new DatabaseCache(
            pdo: $pdo,
            table: $config['table'] ?? 'llm_cache',
            defaultTtl: $config['default_ttl'] ?? 3600,
        );
    }

    private static function createFileCache(array $config): FileCache
    {
        return new FileCache(
            cacheDir: $config['cache_dir'] ?? null,
            defaultTtl: $config['default_ttl'] ?? 3600,
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

