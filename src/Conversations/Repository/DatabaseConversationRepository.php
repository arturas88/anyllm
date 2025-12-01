<?php

declare(strict_types=1);

namespace AnyLLM\Conversations\Repository;

use AnyLLM\Conversations\Conversation;
use AnyLLM\Conversations\ConversationMessage;
use AnyLLM\Exceptions\AnyLLMException;

final class DatabaseConversationRepository implements ConversationRepositoryInterface
{
    public function __construct(
        private \PDO $pdo,
        private string $conversationsTable = 'llm_conversation',
        private string $messagesTable = 'llm_message',
    ) {}

    public function save(Conversation $conversation): void
    {
        $this->pdo->beginTransaction();

        try {
            // Check if conversation exists
            if ($this->exists($conversation->id)) {
                $this->update($conversation);
            } else {
                $this->insert($conversation);
            }

            // Save messages
            $this->saveMessages($conversation);

            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw new AnyLLMException("Failed to save conversation: {$e->getMessage()}", 0, $e);
        }
    }

    public function find(string $id): ?Conversation
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->conversationsTable} WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (! $row) {
            return null;
        }

        return $this->hydrateConversation($row);
    }

    public function findByUserId(string $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->conversationsTable} 
             WHERE user_id = :user_id 
             ORDER BY created_at DESC"
        );
        $stmt->execute(['user_id' => $userId]);

        return array_map(
            fn($row) => $this->hydrateConversation($row),
            $stmt->fetchAll(\PDO::FETCH_ASSOC)
        );
    }

    public function findByMetadata(string $key, mixed $value): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->conversationsTable} 
             WHERE JSON_EXTRACT(metadata, '$.{$key}') = :value 
             ORDER BY created_at DESC"
        );
        $stmt->execute(['value' => $value]);

        return array_map(
            fn($row) => $this->hydrateConversation($row),
            $stmt->fetchAll(\PDO::FETCH_ASSOC)
        );
    }

    public function delete(string $id): bool
    {
        $this->pdo->beginTransaction();

        try {
            // Delete messages first (foreign key constraint)
            $stmt = $this->pdo->prepare(
                "DELETE FROM {$this->messagesTable} WHERE conversation_id = :id"
            );
            $stmt->execute(['id' => $id]);

            // Delete conversation
            $stmt = $this->pdo->prepare(
                "DELETE FROM {$this->conversationsTable} WHERE id = :id"
            );
            $stmt->execute(['id' => $id]);

            $this->pdo->commit();

            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw new AnyLLMException("Failed to delete conversation: {$e->getMessage()}", 0, $e);
        }
    }

    public function exists(string $id): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM {$this->conversationsTable} WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetchColumn() > 0;
    }

    public function count(?string $userId = null): int
    {
        if ($userId) {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM {$this->conversationsTable} WHERE user_id = :user_id"
            );
            $stmt->execute(['user_id' => $userId]);
        } else {
            $stmt = $this->pdo->query(
                "SELECT COUNT(*) FROM {$this->conversationsTable}"
            );
        }

        return (int) $stmt->fetchColumn();
    }

    public function paginate(int $page = 1, int $perPage = 20, ?string $userId = null): array
    {
        $offset = ($page - 1) * $perPage;
        
        if ($userId) {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM {$this->conversationsTable} 
                 WHERE user_id = :user_id 
                 ORDER BY created_at DESC 
                 LIMIT :limit OFFSET :offset"
            );
            $stmt->bindValue('user_id', $userId);
            $stmt->bindValue('limit', $perPage, \PDO::PARAM_INT);
            $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
        } else {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM {$this->conversationsTable} 
                 ORDER BY created_at DESC 
                 LIMIT :limit OFFSET :offset"
            );
            $stmt->bindValue('limit', $perPage, \PDO::PARAM_INT);
            $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
        }

        $stmt->execute();
        $conversations = array_map(
            fn($row) => $this->hydrateConversation($row),
            $stmt->fetchAll(\PDO::FETCH_ASSOC)
        );

        return [
            'conversations' => $conversations,
            'total' => $this->count($userId),
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    public function search(string $query, ?string $userId = null): array
    {
        $searchTerm = "%{$query}%";

        if ($userId) {
            $stmt = $this->pdo->prepare(
                "SELECT DISTINCT c.* FROM {$this->conversationsTable} c
                 LEFT JOIN {$this->messagesTable} m ON c.id = m.conversation_id
                 WHERE c.user_id = :user_id 
                 AND (c.title LIKE :query OR m.content LIKE :query)
                 ORDER BY c.created_at DESC"
            );
            $stmt->execute(['user_id' => $userId, 'query' => $searchTerm]);
        } else {
            $stmt = $this->pdo->prepare(
                "SELECT DISTINCT c.* FROM {$this->conversationsTable} c
                 LEFT JOIN {$this->messagesTable} m ON c.id = m.conversation_id
                 WHERE c.title LIKE :query OR m.content LIKE :query
                 ORDER BY c.created_at DESC"
            );
            $stmt->execute(['query' => $searchTerm]);
        }

        return array_map(
            fn($row) => $this->hydrateConversation($row),
            $stmt->fetchAll(\PDO::FETCH_ASSOC)
        );
    }

    public function updateMetadata(string $id, array $metadata): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE {$this->conversationsTable} 
             SET metadata = :metadata, updated_at = :updated_at 
             WHERE id = :id"
        );

        return $stmt->execute([
            'id' => $id,
            'metadata' => json_encode($metadata),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function findByDateRange(\DateTimeInterface $start, \DateTimeInterface $end, ?string $userId = null): array
    {
        if ($userId) {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM {$this->conversationsTable} 
                 WHERE user_id = :user_id 
                 AND created_at BETWEEN :start AND :end 
                 ORDER BY created_at DESC"
            );
            $stmt->execute([
                'user_id' => $userId,
                'start' => $start->format('Y-m-d H:i:s'),
                'end' => $end->format('Y-m-d H:i:s'),
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM {$this->conversationsTable} 
                 WHERE created_at BETWEEN :start AND :end 
                 ORDER BY created_at DESC"
            );
            $stmt->execute([
                'start' => $start->format('Y-m-d H:i:s'),
                'end' => $end->format('Y-m-d H:i:s'),
            ]);
        }

        return array_map(
            fn($row) => $this->hydrateConversation($row),
            $stmt->fetchAll(\PDO::FETCH_ASSOC)
        );
    }

    private function insert(Conversation $conversation): void
    {
        $data = $conversation->toArray();
        
        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->conversationsTable} 
             (id, uuid, organization_id, team_id, user_id, session_id, environment,
              title, metadata, summary, summary_token_count, summarized_at, messages_summarized,
              total_messages, total_tokens_used, total_cost, 
              auto_summarize, summarize_after_messages, keep_recent_messages,
              created_at, updated_at) 
             VALUES (:id, :uuid, :organization_id, :team_id, :user_id, :session_id, :environment,
                     :title, :metadata, :summary, :summary_token_count, :summarized_at, :messages_summarized,
                     :total_messages, :total_tokens_used, :total_cost,
                     :auto_summarize, :summarize_after_messages, :keep_recent_messages,
                     :created_at, :updated_at)"
        );

        $stmt->execute([
            'id' => $data['id'],
            'uuid' => $data['uuid'],
            'organization_id' => $data['organization_id'],
            'team_id' => $data['team_id'],
            'user_id' => $data['user_id'],
            'session_id' => $data['session_id'],
            'environment' => $data['environment'],
            'title' => $data['title'],
            'metadata' => json_encode($data['metadata']),
            'summary' => $data['summary'],
            'summary_token_count' => $data['summary_token_count'],
            'summarized_at' => $data['summarized_at'],
            'messages_summarized' => $data['messages_summarized'],
            'total_messages' => $data['total_messages'],
            'total_tokens_used' => $data['total_tokens_used'],
            'total_cost' => $data['total_cost'],
            'auto_summarize' => $data['auto_summarize'],
            'summarize_after_messages' => $data['summarize_after_messages'],
            'keep_recent_messages' => $data['keep_recent_messages'],
            'created_at' => $data['created_at'],
            'updated_at' => $data['updated_at'],
        ]);
    }

    private function update(Conversation $conversation): void
    {
        $data = $conversation->toArray();
        
        $stmt = $this->pdo->prepare(
            "UPDATE {$this->conversationsTable} 
             SET title = :title, metadata = :metadata, summary = :summary, 
                 summary_token_count = :summary_token_count, summarized_at = :summarized_at,
                 messages_summarized = :messages_summarized, total_messages = :total_messages,
                 total_tokens_used = :total_tokens_used, total_cost = :total_cost,
                 auto_summarize = :auto_summarize, summarize_after_messages = :summarize_after_messages,
                 keep_recent_messages = :keep_recent_messages, updated_at = NOW()
             WHERE id = :id"
        );

        $stmt->execute([
            'id' => $data['id'],
            'title' => $data['title'],
            'metadata' => json_encode($data['metadata']),
            'summary' => $data['summary'],
            'summary_token_count' => $data['summary_token_count'],
            'summarized_at' => $data['summarized_at'],
            'messages_summarized' => $data['messages_summarized'],
            'total_messages' => $data['total_messages'],
            'total_tokens_used' => $data['total_tokens_used'],
            'total_cost' => $data['total_cost'],
            'auto_summarize' => $data['auto_summarize'],
            'summarize_after_messages' => $data['summarize_after_messages'],
            'keep_recent_messages' => $data['keep_recent_messages'],
        ]);
    }

    private function saveMessages(Conversation $conversation): void
    {
        // Delete existing messages for this conversation
        $stmt = $this->pdo->prepare(
            "DELETE FROM {$this->messagesTable} WHERE conversation_id = :conversation_id"
        );
        $stmt->execute(['conversation_id' => $conversation->id]);

        // Insert current messages
        $messages = $conversation->getMessages();
        if (empty($messages)) {
            return;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->messagesTable} 
             (conversation_id, organization_id, user_id, role, content, metadata,
              prompt_tokens, completion_tokens, total_tokens, cost,
              model, provider, finish_reason, tool_calls, tool_call_id,
              included_in_summary, summarized_at, created_at, updated_at) 
             VALUES (:conversation_id, :organization_id, :user_id, :role, :content, :metadata,
                     :prompt_tokens, :completion_tokens, :total_tokens, :cost,
                     :model, :provider, :finish_reason, :tool_calls, :tool_call_id,
                     :included_in_summary, :summarized_at, :created_at, :updated_at)"
        );

        foreach ($messages as $message) {
            $data = $message->toArray();
            $stmt->execute([
                'conversation_id' => $conversation->id,
                'organization_id' => $conversation->organizationId,
                'user_id' => $conversation->userId,
                'role' => $data['role'],
                'content' => $data['content'],
                'metadata' => json_encode($data['metadata']),
                'prompt_tokens' => $data['prompt_tokens'],
                'completion_tokens' => $data['completion_tokens'],
                'total_tokens' => $data['total_tokens'],
                'cost' => $data['cost'],
                'model' => $data['model'],
                'provider' => $data['provider'],
                'finish_reason' => $data['finish_reason'],
                'tool_calls' => $data['tool_calls'],
                'tool_call_id' => $data['tool_call_id'],
                'included_in_summary' => $data['included_in_summary'],
                'summarized_at' => $data['summarized_at'],
                'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
                'updated_at' => $data['updated_at'] ?? date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function hydrateConversation(array $row): Conversation
    {
        $conversation = new Conversation(
            id: $row['id'],
            uuid: $row['uuid'],
            organizationId: $row['organization_id'] ?? null,
            teamId: $row['team_id'] ?? null,
            userId: $row['user_id'] ?? null,
            sessionId: $row['session_id'] ?? null,
            environment: $row['environment'] ?? 'production',
            title: $row['title'] ?? null,
            metadata: json_decode($row['metadata'] ?? '[]', true),
            summary: $row['summary'] ?? null,
            summaryTokenCount: (int) ($row['summary_token_count'] ?? 0),
            summarizedAt: $row['summarized_at'] ?? null,
            messagesSummarized: (int) ($row['messages_summarized'] ?? 0),
            autoSummarize: (bool) ($row['auto_summarize'] ?? true),
            summarizeAfterMessages: (int) ($row['summarize_after_messages'] ?? 20),
            keepRecentMessages: (int) ($row['keep_recent_messages'] ?? 5),
            createdAt: isset($row['created_at']) ? new \DateTimeImmutable($row['created_at']) : null,
            updatedAt: isset($row['updated_at']) ? new \DateTimeImmutable($row['updated_at']) : null,
            deletedAt: isset($row['deleted_at']) ? new \DateTimeImmutable($row['deleted_at']) : null,
        );

        // Load messages
        $messages = $this->loadMessages($conversation->id);
        foreach ($messages as $message) {
            $conversation->addMessage($message);
        }

        return $conversation;
    }

    /**
     * @return ConversationMessage[]
     */
    private function loadMessages(string $conversationId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->messagesTable} 
             WHERE conversation_id = :conversation_id 
             ORDER BY created_at ASC"
        );
        $stmt->execute(['conversation_id' => $conversationId]);

        return array_map(
            fn($row) => new ConversationMessage(
                id: $row['id'] ?? null,
                conversationId: (int) $row['conversation_id'],
                organizationId: $row['organization_id'] ?? null,
                userId: $row['user_id'] ?? null,
                role: $row['role'],
                content: $row['content'],
                metadata: json_decode($row['metadata'] ?? '[]', true),
                promptTokens: (int) ($row['prompt_tokens'] ?? 0),
                completionTokens: (int) ($row['completion_tokens'] ?? 0),
                totalTokens: (int) ($row['total_tokens'] ?? 0),
                cost: isset($row['cost']) ? (float) $row['cost'] : null,
                model: $row['model'] ?? null,
                provider: $row['provider'] ?? null,
                finishReason: $row['finish_reason'] ?? null,
                toolCalls: isset($row['tool_calls']) ? json_decode($row['tool_calls'], true) : null,
                toolCallId: $row['tool_call_id'] ?? null,
                includedInSummary: (bool) ($row['included_in_summary'] ?? false),
                summarizedAt: $row['summarized_at'] ?? null,
                createdAt: isset($row['created_at']) ? new \DateTimeImmutable($row['created_at']) : null,
                updatedAt: isset($row['updated_at']) ? new \DateTimeImmutable($row['updated_at']) : null,
            ),
            $stmt->fetchAll(\PDO::FETCH_ASSOC)
        );
    }
}

