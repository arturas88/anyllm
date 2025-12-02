<?php

declare(strict_types=1);

namespace AnyLLM\Logging;

use AnyLLM\Exceptions\ValidationException;
use AnyLLM\Logging\Drivers\DatabaseLogDriver;
use AnyLLM\Logging\Drivers\FileLogDriver;
use AnyLLM\Logging\Drivers\LogDriverInterface;
use AnyLLM\Logging\Drivers\NullLogDriver;

final class LoggerFactory
{
    /**
     * Create a logger instance from configuration.
     */
    public static function create(string $driver, array $config = []): LogDriverInterface
    {
        return match ($driver) {
            'database' => self::createDatabaseLogger($config),
            'file' => self::createFileLogger($config),
            'null' => new NullLogDriver(),
            default => throw new ValidationException("Unsupported log driver: {$driver}"),
        };
    }

    private static function createDatabaseLogger(array $config): DatabaseLogDriver
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

        return new DatabaseLogDriver(
            pdo: $pdo,
            logsTable: $config['logs_table'] ?? 'llm_log',
            usageTable: $config['usage_table'] ?? 'llm_usage',
        );
    }

    private static function createFileLogger(array $config): FileLogDriver
    {
        return new FileLogDriver(
            logPath: $config['log_path'] ?? null,
            maxFileSize: $config['max_file_size'] ?? 10485760, // 10MB
            maxFiles: $config['max_files'] ?? 5,
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
