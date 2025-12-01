<?php

declare(strict_types=1);

namespace AnyLLM\Logging;

final readonly class LogEntry
{
    public function __construct(
        // Core fields
        public string $provider,
        public string $model,
        public string $method,
        public array $request,
        public array $response,
        
        // Tracing
        public ?string $requestId = null,
        public ?string $traceId = null,
        public ?string $parentRequestId = null,
        
        // Event info
        public string $eventType = 'request', // request, response, error, stream_chunk
        
        // Multi-tenancy
        public ?string $organizationId = null,
        public ?string $teamId = null,
        public ?string $userId = null,
        public ?string $sessionId = null,
        
        // Environment
        public string $environment = 'production',
        
        // Metrics
        public ?float $duration = null, // milliseconds
        public int $promptTokens = 0,
        public int $completionTokens = 0,
        public int $totalTokens = 0,
        public float $cost = 0.0,
        
        // Audit
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?string $apiKeyId = null,
        
        // Additional data
        public ?string $error = null,
        public array $context = [],
        public ?int $durationMs = null, // Deprecated, use duration
        public ?int $tokensUsed = null, // Deprecated, use totalTokens
        public array $metadata = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            provider: $data['provider'],
            model: $data['model'] ?? '',
            method: $data['method'],
            request: json_decode($data['request'] ?? '{}', true),
            response: json_decode($data['response'] ?? '{}', true),
            requestId: $data['request_id'] ?? null,
            traceId: $data['trace_id'] ?? null,
            parentRequestId: $data['parent_request_id'] ?? null,
            eventType: $data['event_type'] ?? 'request',
            organizationId: $data['organization_id'] ?? null,
            teamId: $data['team_id'] ?? null,
            userId: $data['user_id'] ?? null,
            sessionId: $data['session_id'] ?? null,
            environment: $data['environment'] ?? 'production',
            duration: $data['duration'] ?? null,
            promptTokens: $data['prompt_tokens'] ?? 0,
            completionTokens: $data['completion_tokens'] ?? 0,
            totalTokens: $data['total_tokens'] ?? 0,
            cost: $data['cost'] ?? 0.0,
            ipAddress: $data['ip_address'] ?? null,
            userAgent: $data['user_agent'] ?? null,
            apiKeyId: $data['api_key_id'] ?? null,
            error: $data['error'] ?? null,
            context: json_decode($data['context'] ?? '{}', true),
        );
    }

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'model' => $this->model,
            'method' => $this->method,
            'request' => $this->request,
            'response' => $this->response,
            'request_id' => $this->requestId ?? $this->generateRequestId(),
            'trace_id' => $this->traceId,
            'parent_request_id' => $this->parentRequestId,
            'event_type' => $this->eventType,
            'organization_id' => $this->organizationId,
            'team_id' => $this->teamId,
            'user_id' => $this->userId,
            'session_id' => $this->sessionId,
            'environment' => $this->environment,
            'duration' => $this->duration ?? $this->durationMs,
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens ?: $this->tokensUsed ?: 0,
            'cost' => $this->cost,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'api_key_id' => $this->apiKeyId,
            'error' => $this->error,
            'context' => $this->context,
        ];
    }

    private function generateRequestId(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

