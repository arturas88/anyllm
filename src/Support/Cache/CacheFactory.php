<?php

declare(strict_types=1);

namespace AnyLLM\Support\Cache;

use AnyLLM\Exceptions\ValidationException;
use AnyLLM\Support\FileCache;

final class CacheFactory
{
    /**
     * Create a cache instance from configuration.
     *
     * @param array<string, mixed> $config
     */
    public static function create(string $driver, array $config = []): CacheInterface
    {
        return match ($driver) {
            'redis' => self::createRedisCache($config),
            'memcached' => self::createMemcachedCache($config),
            'database' => self::createDatabaseCache($config),
            'file' => self::createFileCache($config),
            'array' => new ArrayCache(is_int($config['default_ttl'] ?? null) ? $config['default_ttl'] : 3600),
            default => throw new ValidationException("Unsupported cache driver: {$driver}"),
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function createRedisCache(array $config): RedisCache
    {
        if (! extension_loaded('redis')) {
            throw new ValidationException('Redis extension is not installed. Install it with: pecl install redis');
        }

        $redis = new \Redis();

        $host = is_string($config['host'] ?? null) ? $config['host'] : '127.0.0.1';
        $port = is_int($config['port'] ?? null) ? $config['port'] : 6379;
        $timeout = is_float($config['timeout'] ?? null) ? $config['timeout'] : 0.0;

        $redis->connect($host, $port, $timeout);

        if (isset($config['password'])) {
            $password = is_string($config['password']) ? $config['password'] : (string) $config['password'];
            $redis->auth($password);
        }

        if (isset($config['database'])) {
            $database = is_int($config['database']) ? $config['database'] : (int) $config['database'];
            $redis->select($database);
        }

        $defaultTtl = is_int($config['default_ttl'] ?? null) ? $config['default_ttl'] : 3600;
        return new RedisCache(
            redis: $redis,
            defaultTtl: $defaultTtl,
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function createMemcachedCache(array $config): MemcachedCache
    {
        if (! extension_loaded('memcached')) {
            throw new ValidationException('Memcached extension is not installed. Install it with: pecl install memcached');
        }

        $memcached = new \Memcached();

        $servers = $config['servers'] ?? [['127.0.0.1', 11211]];
        if (!is_array($servers)) {
            $servers = [['127.0.0.1', 11211]];
        }

        foreach ($servers as $server) {
            if (!is_array($server)) {
                continue;
            }
            $host = (is_array($server) && isset($server[0]) && is_string($server[0]))
                ? $server[0]
                : ((is_array($server) && isset($server['host']) && is_string($server['host'])) ? $server['host'] : '127.0.0.1');
            $port = (is_array($server) && isset($server[1]) && is_int($server[1]))
                ? $server[1]
                : ((is_array($server) && isset($server['port']) && is_int($server['port'])) ? $server['port'] : 11211);
            $weight = (is_array($server) && isset($server[2]) && is_int($server[2]))
                ? $server[2]
                : ((is_array($server) && isset($server['weight']) && is_int($server['weight'])) ? $server['weight'] : 0);

            $memcached->addServer($host, $port, $weight);
        }

        if (isset($config['username']) && isset($config['password'])) {
            $username = is_string($config['username']) ? $config['username'] : (string) $config['username'];
            $password = is_string($config['password']) ? $config['password'] : (string) $config['password'];
            $memcached->setSaslAuthData($username, $password);
        }

        $defaultTtl = is_int($config['default_ttl'] ?? null) ? $config['default_ttl'] : 3600;
        return new MemcachedCache(
            memcached: $memcached,
            defaultTtl: $defaultTtl,
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function createDatabaseCache(array $config): DatabaseCache
    {
        $dsn = is_string($config['dsn'] ?? null) ? $config['dsn'] : self::buildDsn($config);
        $username = is_string($config['username'] ?? null) ? $config['username'] : '';
        $password = is_string($config['password'] ?? null) ? $config['password'] : '';
        $options = is_array($config['options'] ?? null) ? $config['options'] : [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ];

        $pdo = new \PDO($dsn, $username !== '' ? $username : null, $password !== '' ? $password : null, $options);

        $table = is_string($config['table'] ?? null) ? $config['table'] : 'llm_cache';
        $defaultTtl = is_int($config['default_ttl'] ?? null) ? $config['default_ttl'] : 3600;
        return new DatabaseCache(
            pdo: $pdo,
            table: $table,
            defaultTtl: $defaultTtl,
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function createFileCache(array $config): FileCache
    {
        $cacheDir = (is_string($config['cache_dir'] ?? null) || $config['cache_dir'] === null) ? $config['cache_dir'] : null;
        $defaultTtl = is_int($config['default_ttl'] ?? null) ? $config['default_ttl'] : 3600;
        return new FileCache(
            cacheDir: $cacheDir,
            defaultTtl: $defaultTtl,
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function buildDsn(array $config): string
    {
        $driver = is_string($config['driver'] ?? null) ? $config['driver'] : 'mysql';
        $host = is_string($config['host'] ?? null) ? $config['host'] : 'localhost';
        $port = is_int($config['port'] ?? null) ? $config['port'] : 3306;
        $database = is_string($config['database'] ?? null) ? $config['database'] : '';

        return match ($driver) {
            'mysql' => "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
            'pgsql' => "pgsql:host={$host};port={$port};dbname={$database}",
            'sqlite' => "sqlite:{$database}",
            default => throw new ValidationException("Unsupported database driver: {$driver}"),
        };
    }
}
