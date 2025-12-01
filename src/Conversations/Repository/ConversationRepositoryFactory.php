<?php

declare(strict_types=1);

namespace AnyLLM\Conversations\Repository;

use Any LLM\Exceptions\ValidationException;

final class ConversationRepositoryFactory
{
    /**
     * Create a repository instance from configuration.
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

    private static function createDatabaseRepository(array $config): DatabaseConversationRepository
    {
        // Create PDO instance
        $dsn = $config['dsn'] ?? self::buildDsn($config);
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        $options = $config['options'] ?? [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ];

        $pdo = new \PDO($dsn, $username, $password, $options);

        return new DatabaseConversationRepository(
            pdo: $pdo,
            conversationsTable: $config['conversations_table'] ?? 'llm_conversation',
            messagesTable: $config['messages_table'] ?? 'llm_message',
        );
    }

    private static function createRedisRepository(array $config): RedisConversationRepository
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

        return new RedisConversationRepository($redis);
    }

    private static function createFileRepository(array $config): FileConversationRepository
    {
        $storagePath = $config['storage_path'] ?? null;
        
        return new FileConversationRepository($storagePath);
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

