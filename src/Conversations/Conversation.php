<?php

declare(strict_types=1);

namespace AnyLLM\Conversations;

use AnyLLM\Messages\Message;

final class Conversation
{
    /** @var array<ConversationMessage> */
    private array $messages = [];
    
    private int $totalTokensUsed = 0;
    private float $totalCost = 0.0;

    public function __construct(
        public readonly string $id,
        public readonly string $uuid,
        
        // Multi-tenancy
        public ?string $organizationId = null,
        public ?string $teamId = null,
        public ?string $userId = null,
        public ?string $sessionId = null,
        
        // Environment
        public string $environment = 'production',
        
        // Basic info
        public ?string $title = null,
        public array $metadata = [],
        
        // Summary management
        public ?string $summary = null,
        public int $summaryTokenCount = 0,
        public ?string $summarizedAt = null,
        public int $messagesSummarized = 0,
        
        // Configuration
        public bool $autoSummarize = true,
        public int $summarizeAfterMessages = 20,
        public int $keepRecentMessages = 5,
        
        // Timestamps
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $updatedAt = null,
        public ?\DateTimeImmutable $deletedAt = null,
    ) {
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new \DateTimeImmutable();
    }

    public static function create(
        string $id,
        ?string $userId = null,
        ?string $sessionId = null,
        array $config = [],
    ): self {
        return new self(
            id: $id,
            uuid: self::generateUuid(),
            organizationId: $config['organization_id'] ?? null,
            teamId: $config['team_id'] ?? null,
            userId: $userId,
            sessionId: $sessionId,
            environment: $config['environment'] ?? 'production',
            autoSummarize: $config['auto_summarize'] ?? true,
            summarizeAfterMessages: $config['summarize_after_messages'] ?? 20,
            keepRecentMessages: $config['keep_recent_messages'] ?? 5,
            metadata: $config['metadata'] ?? [],
        );
    }
    
    private static function generateUuid(): string
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

    public function addMessage(ConversationMessage $message): void
    {
        $this->messages[] = $message;
        $this->totalTokensUsed += $message->totalTokens;
        $this->totalCost += $message->cost ?? 0.0;
    }

    /**
     * @return array<ConversationMessage>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @return array<ConversationMessage>
     */
    public function getUnsummarizedMessages(): array
    {
        return array_filter(
            $this->messages,
            fn ($msg) => ! $msg->includedInSummary
        );
    }

    public function getTotalMessages(): int
    {
        return count($this->messages);
    }

    public function getTotalTokensUsed(): int
    {
        return $this->totalTokensUsed;
    }

    public function getTotalCost(): float
    {
        return $this->totalCost;
    }

    public function needsSummarization(): bool
    {
        if (! $this->autoSummarize) {
            return false;
        }

        $unsummarized = $this->getUnsummarizedMessages();
        return count($unsummarized) >= $this->summarizeAfterMessages;
    }

    public function hasSummary(): bool
    {
        return $this->summary !== null;
    }

    public function setSummary(string $summary, int $tokenCount): void
    {
        $this->summary = $summary;
        $this->summaryTokenCount = $tokenCount;
        $this->summarizedAt = date('Y-m-d H:i:s');
        
        // Mark messages as summarized (except recent ones)
        $total = count($this->messages);
        $keepRecent = $this->keepRecentMessages;
        $summarizeCount = 0;
        
        foreach ($this->messages as $index => $message) {
            if ($index < $total - $keepRecent && ! $message->includedInSummary) {
                $message->includedInSummary = true;
                $message->summarizedAt = $this->summarizedAt;
                $summarizeCount++;
            }
        }
        
        $this->messagesSummarized = $summarizeCount;
    }

    /**
     * Get messages for sending to LLM (with summary optimization).
     * 
     * @return array<Message>
     */
    public function getMessagesForLLM(): array
    {
        $messages = [];

        // If we have a summary, include it as system message
        if ($this->hasSummary()) {
            $messages[] = \AnyLLM\Messages\SystemMessage::create(
                "Previous conversation summary: {$this->summary}"
            );
        }

        // Add unsummarized messages
        $unsummarized = $this->getUnsummarizedMessages();
        foreach ($unsummarized as $msg) {
            $messages[] = $msg->toMessage();
        }

        return $messages;
    }

    /**
     * Get token savings from summarization.
     */
    public function getTokenSavings(): int
    {
        if (! $this->hasSummary()) {
            return 0;
        }

        // Calculate tokens that would have been used without summary
        $summarizedMessages = array_filter(
            $this->messages,
            fn ($msg) => $msg->includedInSummary
        );
        
        $originalTokens = array_reduce(
            $summarizedMessages,
            fn ($sum, $msg) => $sum + $msg->totalTokens,
            0
        );

        return max(0, $originalTokens - $this->summaryTokenCount);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'organization_id' => $this->organizationId,
            'team_id' => $this->teamId,
            'user_id' => $this->userId,
            'session_id' => $this->sessionId,
            'environment' => $this->environment,
            'title' => $this->title,
            'metadata' => $this->metadata,
            'summary' => $this->summary,
            'summary_token_count' => $this->summaryTokenCount,
            'summarized_at' => $this->summarizedAt,
            'messages_summarized' => $this->messagesSummarized,
            'total_messages' => $this->getTotalMessages(),
            'total_tokens_used' => $this->totalTokensUsed,
            'total_cost' => $this->totalCost,
            'auto_summarize' => $this->autoSummarize,
            'summarize_after_messages' => $this->summarizeAfterMessages,
            'keep_recent_messages' => $this->keepRecentMessages,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deletedAt?->format('Y-m-d H:i:s'),
        ];
    }
}

