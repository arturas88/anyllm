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
     *
     * @param array<string, mixed> $config
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

    /**
     * @param array<string, mixed> $config
     */
    private static function createDatabaseLogger(array $config): DatabaseLogDriver
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

        $logsTable = is_string($config['logs_table'] ?? null) ? $config['logs_table'] : 'llm_log';
        $usageTable = is_string($config['usage_table'] ?? null) ? $config['usage_table'] : 'llm_usage';
        return new DatabaseLogDriver(
            pdo: $pdo,
            logsTable: $logsTable,
            usageTable: $usageTable,
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function createFileLogger(array $config): FileLogDriver
    {
        $logPath = (is_string($config['log_path'] ?? null) || $config['log_path'] === null) ? $config['log_path'] : null;
        $maxFileSize = is_int($config['max_file_size'] ?? null) ? $config['max_file_size'] : 10485760;
        $maxFiles = is_int($config['max_files'] ?? null) ? $config['max_files'] : 5;
        return new FileLogDriver(
            logPath: $logPath,
            maxFileSize: $maxFileSize,
            maxFiles: $maxFiles,
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
