<?php

declare(strict_types=1);

namespace AnyLLM\Logging\Drivers;

use AnyLLM\Logging\LogEntry;

final class DatabaseLogDriver implements LogDriverInterface
{
    public function __construct(
        private \PDO $pdo,
        private string $logsTable = 'llm_log',
        private string $usageTable = 'llm_usage',
    ) {}

    public function write(LogEntry $entry): void
    {
        $this->pdo->beginTransaction();

        try {
            $data = $entry->toArray();
            
            // Write to logs table
            $stmt = $this->pdo->prepare(
                "INSERT INTO {$this->logsTable} 
                 (request_id, trace_id, parent_request_id, event_type, provider, method, model,
                  organization_id, team_id, user_id, session_id, environment,
                  request, response, context, duration, 
                  prompt_tokens, completion_tokens, total_tokens, cost,
                  ip_address, user_agent, api_key_id, created_at) 
                 VALUES (:request_id, :trace_id, :parent_request_id, :event_type, :provider, :method, :model,
                         :organization_id, :team_id, :user_id, :session_id, :environment,
                         :request, :response, :context, :duration,
                         :prompt_tokens, :completion_tokens, :total_tokens, :cost,
                         :ip_address, :user_agent, :api_key_id, NOW())"
            );

            $stmt->execute([
                'request_id' => $data['request_id'],
                'trace_id' => $data['trace_id'],
                'parent_request_id' => $data['parent_request_id'],
                'event_type' => $data['event_type'],
                'provider' => $data['provider'],
                'method' => $data['method'],
                'model' => $data['model'],
                'organization_id' => $data['organization_id'],
                'team_id' => $data['team_id'],
                'user_id' => $data['user_id'],
                'session_id' => $data['session_id'],
                'environment' => $data['environment'],
                'request' => json_encode($data['request']),
                'response' => json_encode($data['response']),
                'context' => json_encode($data['context']),
                'duration' => $data['duration'],
                'prompt_tokens' => $data['prompt_tokens'],
                'completion_tokens' => $data['completion_tokens'],
                'total_tokens' => $data['total_tokens'],
                'cost' => $data['cost'],
                'ip_address' => $data['ip_address'],
                'user_agent' => $data['user_agent'],
                'api_key_id' => $data['api_key_id'],
            ]);

            // Write to usage table for analytics
            if ($data['total_tokens'] > 0) {
                $this->writeUsage($entry);
            }

            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function query(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $where = [];
        $params = [];

        if (isset($filters['provider'])) {
            $where[] = 'provider = :provider';
            $params['provider'] = $filters['provider'];
        }

        if (isset($filters['model'])) {
            $where[] = 'model = :model';
            $params['model'] = $filters['model'];
        }

        if (isset($filters['method'])) {
            $where[] = 'method = :method';
            $params['method'] = $filters['method'];
        }

        if (isset($filters['has_error'])) {
            $where[] = 'error IS ' . ($filters['has_error'] ? 'NOT NULL' : 'NULL');
        }

        if (isset($filters['start_date'])) {
            $where[] = 'created_at >= :start_date';
            $params['start_date'] = $filters['start_date'];
        }

        if (isset($filters['end_date'])) {
            $where[] = 'created_at <= :end_date';
            $params['end_date'] = $filters['end_date'];
        }

        $whereClause = ! empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT * FROM {$this->logsTable} 
                {$whereClause} 
                ORDER BY created_at DESC 
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(fn($row) => $this->hydrateLogEntry($row), $rows);
    }

    public function analyze(?string $provider = null, ?\DateTimeInterface $start = null, ?\DateTimeInterface $end = null): array
    {
        $where = ['1=1'];
        $params = [];

        if ($provider) {
            $where[] = 'provider = :provider';
            $params['provider'] = $provider;
        }

        if ($start) {
            $where[] = 'created_at >= :start';
            $params['start'] = $start->format('Y-m-d H:i:s');
        }

        if ($end) {
            $where[] = 'created_at <= :end';
            $params['end'] = $end->format('Y-m-d H:i:s');
        }

        $whereClause = implode(' AND ', $where);

        // Overall statistics
        $stmt = $this->pdo->prepare(
            "SELECT 
                COUNT(*) as total_requests,
                COUNT(CASE WHEN event_type = 'error' THEN 1 END) as failed_requests,
                SUM(total_tokens) as total_tokens,
                SUM(cost) as total_cost,
                AVG(duration) as avg_duration,
                MAX(duration) as max_duration,
                MIN(duration) as min_duration
             FROM {$this->logsTable}
             WHERE {$whereClause}"
        );
        $stmt->execute($params);
        $overall = $stmt->fetch(\PDO::FETCH_ASSOC);

        // By provider
        $stmt = $this->pdo->prepare(
            "SELECT 
                provider,
                COUNT(*) as requests,
                SUM(total_tokens) as tokens,
                SUM(cost) as cost
             FROM {$this->logsTable}
             WHERE {$whereClause}
             GROUP BY provider
             ORDER BY requests DESC"
        );
        $stmt->execute($params);
        $byProvider = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // By model
        $stmt = $this->pdo->prepare(
            "SELECT 
                model,
                COUNT(*) as requests,
                SUM(total_tokens) as tokens,
                SUM(cost) as cost
             FROM {$this->logsTable}
             WHERE {$whereClause}
             GROUP BY model
             ORDER BY requests DESC
             LIMIT 10"
        );
        $stmt->execute($params);
        $byModel = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Error rate by provider
        $stmt = $this->pdo->prepare(
            "SELECT 
                provider,
                COUNT(*) as total,
                COUNT(CASE WHEN event_type = 'error' THEN 1 END) as errors,
                (COUNT(CASE WHEN event_type = 'error' THEN 1 END) * 100.0 / COUNT(*)) as error_rate
             FROM {$this->logsTable}
             WHERE {$whereClause}
             GROUP BY provider"
        );
        $stmt->execute($params);
        $errorRates = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'overall' => $overall,
            'by_provider' => $byProvider,
            'by_model' => $byModel,
            'error_rates' => $errorRates,
        ];
    }

    public function prune(int $days = 30): int
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM {$this->logsTable} 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)"
        );
        $stmt->execute(['days' => $days]);

        return $stmt->rowCount();
    }

    private function writeUsage(LogEntry $entry): void
    {
        $data = $entry->toArray();
        
        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->usageTable} 
             (provider, model, organization_id, team_id, user_id, request_id, environment,
              input_tokens, output_tokens, cached_tokens, total_tokens, cost, metadata, date, created_at) 
             VALUES (:provider, :model, :organization_id, :team_id, :user_id, :request_id, :environment,
                     :input_tokens, :output_tokens, :cached_tokens, :total_tokens, :cost, :metadata, CURDATE(), NOW())"
        );

        $stmt->execute([
            'provider' => $data['provider'],
            'model' => $data['model'],
            'organization_id' => $data['organization_id'],
            'team_id' => $data['team_id'],
            'user_id' => $data['user_id'],
            'request_id' => $data['request_id'],
            'environment' => $data['environment'],
            'input_tokens' => $data['prompt_tokens'],
            'output_tokens' => $data['completion_tokens'],
            'cached_tokens' => 0, // TODO: Add cached_tokens to LogEntry if provider supports it
            'total_tokens' => $data['total_tokens'],
            'cost' => $data['cost'],
            'metadata' => json_encode($data['context']),
        ]);
    }

    private function hydrateLogEntry(array $row): LogEntry
    {
        return LogEntry::fromArray($row);
    }
}

