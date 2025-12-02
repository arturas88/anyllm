<?php

declare(strict_types=1);

namespace AnyLLM\Logging\Drivers;

use AnyLLM\Logging\LogEntry;

/**
 * Null logger that doesn't write anything.
 * Useful for testing or when logging is disabled.
 */
final class NullLogDriver implements LogDriverInterface
{
    public function write(LogEntry $entry): void
    {
        // Do nothing
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, LogEntry>
     */
    public function query(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function analyze(?string $provider = null, ?\DateTimeInterface $start = null, ?\DateTimeInterface $end = null): array
    {
        return [
            'overall' => [
                'total_requests' => 0,
                'failed_requests' => 0,
                'total_tokens' => 0,
                'total_cost' => 0,
                'avg_duration' => 0,
                'max_duration' => 0,
                'min_duration' => 0,
            ],
            'by_provider' => [],
        ];
    }

    public function prune(int $days = 30): int
    {
        return 0;
    }
}
