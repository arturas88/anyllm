<?php

declare(strict_types=1);

namespace AnyLLM\Logging\Drivers;

use AnyLLM\Logging\LogEntry;

final class FileLogDriver implements LogDriverInterface
{
    private string $logPath;
    private int $maxFileSize;
    private int $maxFiles;

    public function __construct(
        ?string $logPath = null,
        int $maxFileSize = 10485760, // 10MB
        int $maxFiles = 5,
    ) {
        $this->logPath = $logPath ?? sys_get_temp_dir() . '/anyllm-logs';
        $this->maxFileSize = $maxFileSize;
        $this->maxFiles = $maxFiles;
        
        $this->ensureLogDirectory();
    }

    public function write(LogEntry $entry): void
    {
        $logFile = $this->getCurrentLogFile();

        // Rotate if needed
        if (file_exists($logFile) && filesize($logFile) >= $this->maxFileSize) {
            $this->rotate();
            $logFile = $this->getCurrentLogFile();
        }

        $timestamp = date('Y-m-d H:i:s');
        $level = $entry->error ? 'ERROR' : 'INFO';
        
        // Format: [timestamp] LEVEL provider/model method - duration: Xms tokens: Y cost: $Z
        $line = sprintf(
            "[%s] %s %s/%s %s - duration: %dms tokens: %d cost: $%.4f\n",
            $timestamp,
            $level,
            $entry->provider,
            $entry->model,
            $entry->method,
            $entry->durationMs,
            $entry->tokensUsed,
            $entry->cost
        );

        if ($entry->error) {
            $line .= "  Error: {$entry->error}\n";
        }

        // Append metadata if present
        if (! empty($entry->metadata)) {
            $line .= "  Metadata: " . json_encode($entry->metadata) . "\n";
        }

        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);

        // Also write detailed JSON log
        $this->writeDetailedLog($entry);
    }

    public function query(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $entries = [];
        $detailFiles = glob($this->logPath . '/details-*.jsonl');
        
        if ($detailFiles === false) {
            return [];
        }

        // Sort by modification time (newest first)
        usort($detailFiles, fn($a, $b) => filemtime($b) - filemtime($a));

        $count = 0;
        $skipped = 0;

        foreach ($detailFiles as $file) {
            if ($count >= $limit) {
                break;
            }

            $handle = fopen($file, 'r');
            if (! $handle) {
                continue;
            }

            // Read file line by line (each line is a JSON log entry)
            while (($line = fgets($handle)) !== false) {
                if ($count >= $limit) {
                    break;
                }

                $data = json_decode($line, true);
                if (! $data) {
                    continue;
                }

                // Apply filters
                if (! $this->matchesFilters($data, $filters)) {
                    continue;
                }

                // Handle offset
                if ($skipped < $offset) {
                    $skipped++;
                    continue;
                }

                $entries[] = $this->hydrateLogEntry($data);
                $count++;
            }

            fclose($handle);
        }

        return $entries;
    }

    public function analyze(?string $provider = null, ?\DateTimeInterface $start = null, ?\DateTimeInterface $end = null): array
    {
        $entries = $this->query();

        // Filter by criteria
        $filtered = array_filter($entries, function($entry) use ($provider, $start, $end) {
            if ($provider && $entry->provider !== $provider) {
                return false;
            }
            
            // Note: We don't have timestamps in LogEntry, would need to add them
            return true;
        });

        // Calculate statistics
        $total = count($filtered);
        $errors = count(array_filter($filtered, fn($e) => $e->error !== null));
        $tokens = array_sum(array_map(fn($e) => $e->tokensUsed, $filtered));
        $cost = array_sum(array_map(fn($e) => $e->cost, $filtered));
        $durations = array_map(fn($e) => $e->durationMs, $filtered);

        $byProvider = [];
        foreach ($filtered as $entry) {
            if (! isset($byProvider[$entry->provider])) {
                $byProvider[$entry->provider] = [
                    'requests' => 0,
                    'tokens' => 0,
                    'cost' => 0,
                ];
            }
            $byProvider[$entry->provider]['requests']++;
            $byProvider[$entry->provider]['tokens'] += $entry->tokensUsed;
            $byProvider[$entry->provider]['cost'] += $entry->cost;
        }

        return [
            'overall' => [
                'total_requests' => $total,
                'failed_requests' => $errors,
                'total_tokens' => $tokens,
                'total_cost' => $cost,
                'avg_duration' => $total > 0 ? array_sum($durations) / $total : 0,
                'max_duration' => $total > 0 ? max($durations) : 0,
                'min_duration' => $total > 0 ? min($durations) : 0,
            ],
            'by_provider' => array_values($byProvider),
        ];
    }

    public function prune(int $days = 30): int
    {
        $cutoff = time() - ($days * 86400);
        $deleted = 0;

        $files = glob($this->logPath . '/*');
        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    private function getCurrentLogFile(): string
    {
        return $this->logPath . '/anyllm-' . date('Y-m-d') . '.log';
    }

    private function getCurrentDetailFile(): string
    {
        return $this->logPath . '/details-' . date('Y-m-d') . '.jsonl';
    }

    private function rotate(): void
    {
        $currentFile = $this->getCurrentLogFile();
        
        // Rename current file
        $timestamp = date('YmdHis');
        $rotatedFile = $this->logPath . '/anyllm-' . $timestamp . '.log';
        rename($currentFile, $rotatedFile);

        // Remove old files if we exceed max files
        $files = glob($this->logPath . '/anyllm-*.log');
        if ($files && count($files) > $this->maxFiles) {
            usort($files, fn($a, $b) => filemtime($a) - filemtime($b));
            $toDelete = array_slice($files, 0, count($files) - $this->maxFiles);
            foreach ($toDelete as $file) {
                unlink($file);
            }
        }
    }

    private function writeDetailedLog(LogEntry $entry): void
    {
        $detailFile = $this->getCurrentDetailFile();
        
        $data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'provider' => $entry->provider,
            'model' => $entry->model,
            'method' => $entry->method,
            'request' => $entry->request,
            'response' => $entry->response,
            'error' => $entry->error,
            'duration_ms' => $entry->durationMs,
            'tokens_used' => $entry->tokensUsed,
            'cost' => $entry->cost,
            'metadata' => $entry->metadata,
        ];

        file_put_contents($detailFile, json_encode($data) . "\n", FILE_APPEND | LOCK_EX);
    }

    private function matchesFilters(array $data, array $filters): bool
    {
        if (isset($filters['provider']) && $data['provider'] !== $filters['provider']) {
            return false;
        }

        if (isset($filters['model']) && $data['model'] !== $filters['model']) {
            return false;
        }

        if (isset($filters['method']) && $data['method'] !== $filters['method']) {
            return false;
        }

        if (isset($filters['has_error'])) {
            $hasError = ! empty($data['error']);
            if ($hasError !== $filters['has_error']) {
                return false;
            }
        }

        return true;
    }

    private function hydrateLogEntry(array $data): LogEntry
    {
        return new LogEntry(
            provider: $data['provider'],
            model: $data['model'],
            method: $data['method'],
            request: $data['request'] ?? [],
            response: $data['response'] ?? [],
            error: $data['error'] ?? null,
            durationMs: $data['duration_ms'] ?? 0,
            tokensUsed: $data['tokens_used'] ?? 0,
            cost: $data['cost'] ?? 0.0,
            metadata: $data['metadata'] ?? [],
        );
    }

    private function ensureLogDirectory(): void
    {
        if (! is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }
}

