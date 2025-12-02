<?php

declare(strict_types=1);

namespace AnyLLM\Conversations\Repository;

use AnyLLM\Exceptions\ValidationException;

final class ConversationRepositoryFactory
{
    /**
     * Create a repository instance from configuration.
     *
     * @param array<string, mixed> $config
     */
    public static function create(string $driver, array $config = []): ConversationRepositoryInterface
    {
        return match ($driver) {
            'database' => self::createDatabaseRepository($config),
            'redis' => self::createRedisRepository($config),
            'file' => self::createFileRepository($config),
            default => throw new ValidationException("Unsupported repository driver: {$driver}"),
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function createDatabaseRepository(array $config): DatabaseConversationRepository
    {
        // Create PDO instance
        $dsn = is_string($config['dsn'] ?? null) ? $config['dsn'] : self::buildDsn($config);
        $username = is_string($config['username'] ?? null) ? $config['username'] : '';
        $password = is_string($config['password'] ?? null) ? $config['password'] : '';
        $options = is_array($config['options'] ?? null) ? $config['options'] : [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ];

        $pdo = new \PDO($dsn, $username !== '' ? $username : null, $password !== '' ? $password : null, $options);

        $conversationsTable = is_string($config['conversations_table'] ?? null) ? $config['conversations_table'] : 'llm_conversation';
        $messagesTable = is_string($config['messages_table'] ?? null) ? $config['messages_table'] : 'llm_message';
        return new DatabaseConversationRepository(
            pdo: $pdo,
            conversationsTable: $conversationsTable,
            messagesTable: $messagesTable,
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function createRedisRepository(array $config): RedisConversationRepository
    {
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

        return new RedisConversationRepository($redis);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function createFileRepository(array $config): FileConversationRepository
    {
        $storagePath = (is_string($config['storage_path'] ?? null) || $config['storage_path'] === null) ? $config['storage_path'] : null;

        return new FileConversationRepository($storagePath);
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
