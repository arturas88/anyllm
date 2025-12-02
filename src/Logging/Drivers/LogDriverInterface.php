<?php

declare(strict_types=1);

namespace AnyLLM\Logging\Drivers;

use AnyLLM\Logging\LogEntry;

interface LogDriverInterface
{
    /**
     * Write a log entry.
     */
    public function write(LogEntry $entry): void;

    /**
     * Query log entries.
     *
     * @param array<string, mixed> $filters
     * @return array<int, LogEntry>
     */
    public function query(array $filters = [], int $limit = 100, int $offset = 0): array;

    /**
     * Get analytics/statistics from logs.
     *
     * @return array<string, mixed>
     */
    public function analyze(?string $provider = null, ?\DateTimeInterface $start = null, ?\DateTimeInterface $end = null): array;

    /**
     * Clear old log entries.
     */
    public function prune(int $days = 30): int;
}
