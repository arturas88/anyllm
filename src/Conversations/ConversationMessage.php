<?php

declare(strict_types=1);

namespace AnyLLM\Conversations;

use AnyLLM\Enums\Role;
use AnyLLM\Messages\AssistantMessage;
use AnyLLM\Messages\Message;
use AnyLLM\Messages\SystemMessage;
use AnyLLM\Messages\ToolMessage;
use AnyLLM\Messages\UserMessage;
use AnyLLM\Responses\Parts\ToolCall;

final class ConversationMessage
{
    public function __construct(
        // Required parameters first
        public readonly string $role,
        public readonly string $content,

        // Optional parameters after required ones
        public readonly ?int $id = null,
        public readonly ?int $conversationId = null,

        // Multi-tenancy (denormalized for fast queries)
        public readonly ?string $organizationId = null,
        public readonly ?string $userId = null,

        // Message metadata
        /** @var array<string, mixed> */
        public readonly array $metadata = [],

        // Token tracking per message
        public readonly int $promptTokens = 0,
        public readonly int $completionTokens = 0,
        public readonly int $totalTokens = 0,
        public readonly ?float $cost = null,

        // Model info
        public readonly ?string $model = null,
        public readonly ?string $provider = null,
        public readonly ?string $finishReason = null,

        // Tool calls
        /** @var array<int, array<string, mixed>>|null */
        public readonly ?array $toolCalls = null,
        public readonly ?string $toolCallId = null,

        // Summary tracking
        public bool $includedInSummary = false,
        public ?string $summarizedAt = null,

        // Timestamps
        public readonly ?\DateTimeImmutable $createdAt = null,
        public readonly ?\DateTimeImmutable $updatedAt = null,
    ) {}

    public static function fromMessage(
        Message $message,
        int $promptTokens = 0,
        int $completionTokens = 0,
        ?string $model = null,
        ?string $provider = null,
        ?int $conversationId = null,
        ?string $organizationId = null,
        ?string $userId = null,
    ): self {
        $content = is_string($message->content) ? $message->content : json_encode($message->content);
        if ($content === false) {
            $content = '';
        }

        return new self(
            role: $message->role->value,
            content: $content,
            id: null,
            conversationId: $conversationId,
            organizationId: $organizationId,
            userId: $userId,
            metadata: [],
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            totalTokens: $promptTokens + $completionTokens,
            model: $model,
            provider: $provider,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );
    }

    public function toMessage(): Message
    {
        $role = Role::from($this->role);

        return match ($role) {
            Role::System => SystemMessage::create($this->content),
            Role::User => UserMessage::create($this->content),
            Role::Assistant => new AssistantMessage(
                content: $this->content,
                toolCalls: $this->toolCalls === null ? [] : array_map(
                    fn($tc) => ToolCall::fromArray($tc),
                    $this->toolCalls
                ),
            ),
            Role::Tool => new ToolMessage(
                content: $this->content,
                toolCallId: $this->toolCallId ?? '',
            ),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversationId,
            'organization_id' => $this->organizationId,
            'user_id' => $this->userId,
            'role' => $this->role,
            'content' => $this->content,
            'metadata' => $this->metadata,
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
            'cost' => $this->cost,
            'model' => $this->model,
            'provider' => $this->provider,
            'finish_reason' => $this->finishReason,
            'tool_calls' => $this->toolCalls ? json_encode($this->toolCalls) : null,
            'tool_call_id' => $this->toolCallId,
            'included_in_summary' => $this->includedInSummary,
            'summarized_at' => $this->summarizedAt,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
