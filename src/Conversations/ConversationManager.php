<?php

declare(strict_types=1);

namespace AnyLLM\Conversations;

use AnyLLM\Contracts\ProviderInterface;
use AnyLLM\Conversations\Repository\ConversationRepositoryInterface;
use AnyLLM\Messages\Message;
use AnyLLM\Responses\ChatResponse;

/**
 * Manages conversations with automatic summarization to save tokens.
 *
 * This class handles:
 * - Storing conversation history (in-memory and persistent)
 * - Automatic summarization when messages exceed threshold
 * - Token optimization by replacing old messages with summaries
 * - Persistence via repository pattern (database, Redis, file)
 */
final class ConversationManager
{
    /** @var array<string, Conversation> In-memory cache */
    private array $conversations = [];

    public function __construct(
        private readonly ProviderInterface $provider,
        private readonly ?ConversationRepositoryInterface $repository = null,
        private readonly string $summaryModel = 'gpt-4o-mini',
        /** @var array<string, mixed> */
        private readonly array $defaultConfig = [],
    ) {}

    /**
     * Create a new conversation.
     */
    public function create(string $userId, string $title = 'New Conversation'): Conversation
    {
        $conversation = Conversation::create(
            id: uniqid('conv-', true),
            userId: $userId,
            config: ['title' => $title],
        );

        $this->conversations[$conversation->id] = $conversation;

        if ($this->repository) {
            $this->repository->save($conversation);
        }

        return $conversation;
    }

    /**
     * Find a conversation by ID.
     */
    public function find(string $id): ?Conversation
    {
        // Check in-memory cache first
        if (isset($this->conversations[$id])) {
            return $this->conversations[$id];
        }

        // Load from repository
        if ($this->repository) {
            $conversation = $this->repository->find($id);
            if ($conversation) {
                $this->conversations[$id] = $conversation;
            }
            return $conversation;
        }

        return null;
    }

    /**
     * Get or create a conversation.
     */
    public function getOrCreate(string $id, string $userId, string $title = 'New Conversation'): Conversation
    {
        $conversation = $this->find($id);

        if (! $conversation) {
            $conversation = Conversation::create(
                id: $id,
                userId: $userId,
                config: ['title' => $title],
            );
            $this->conversations[$id] = $conversation;

            if ($this->repository) {
                $this->repository->save($conversation);
            }
        }

        return $conversation;
    }

    /**
     * Save a conversation to persistent storage.
     */
    public function save(Conversation $conversation): void
    {
        $this->conversations[$conversation->id] = $conversation;

        if ($this->repository) {
            $this->repository->save($conversation);
        }
    }

    /**
     * Add a user message to a conversation.
     *
     * @param array<string, mixed> $metadata
     */
    public function addMessage(Conversation $conversation, string $role, string $content, array $metadata = []): ConversationMessage
    {
        $message = new ConversationMessage(
            role: $role,
            content: $content,
            id: null,
            conversationId: null,
            organizationId: $conversation->organizationId,
            userId: $conversation->userId,
            metadata: $metadata,
        );

        $conversation->addMessage($message);

        if ($this->repository) {
            $this->repository->save($conversation);
        }

        return $message;
    }

    /**
     * Send a message and get response with automatic summarization.
     *
     * @deprecated Use chat(string $conversationId, string $userMessage, ...) instead
     */
    /**
     * @param array<string, mixed> $options
     */
    public function chatWithConversation(
        Conversation $conversation,
        ProviderInterface $provider,
        string $model = 'gpt-4o',
        array $options = [],
    ): ChatResponse {
        // Check if summarization is needed
        if ($conversation->needsSummarization()) {
            $this->summarize($conversation, $provider);
        }

        // Get messages for LLM (includes summary optimization)
        $messages = $conversation->getMessagesForLLM();

        // Send to LLM
        // Extract known parameters from options
        $temperature = $options['temperature'] ?? null;
        $maxTokens = $options['maxTokens'] ?? null;
        $tools = $options['tools'] ?? null;
        $toolChoice = $options['toolChoice'] ?? null;

        // Remove extracted parameters from options
        $remainingOptions = array_diff_key($options, array_flip(['temperature', 'maxTokens', 'tools', 'toolChoice']));

        $response = $provider->chat(
            $model,
            $messages,
            $temperature,
            $maxTokens,
            $tools,
            $toolChoice,
            $remainingOptions,
        );

        // Add assistant response
        $this->addMessage(
            $conversation,
            'assistant',
            $response->content,
            [
                'model' => $model,
                'tokens' => $response->usage?->totalTokens ?? 0,
                'cost' => 0, // Calculate based on model pricing
            ]
        );

        return $response;
    }

    /**
     * Generate a summary of older messages.
     *
     * @throws \Throwable Re-throws exceptions from LLM calls
     */
    public function summarize(Conversation $conversation, ProviderInterface $provider): void
    {
        $messages = $conversation->getMessages();

        if (count($messages) < 5) {
            return; // Not enough messages to summarize
        }

        // Build conversation text
        $conversationText = '';
        foreach ($messages as $msg) {
            $conversationText .= "{$msg->role}: {$msg->content}\n\n";
        }

        // Generate summary
        $prompt = "Summarize the following conversation concisely, preserving key information, "
                  . "decisions, and context. Be brief but comprehensive:\n\n{$conversationText}";

        try {
            $response = $provider->generateText(
                model: $this->summaryModel,
                prompt: $prompt,
            );

            $conversation->summary = $response->text;
            $conversation->updatedAt = new \DateTimeImmutable();

            if ($this->repository) {
                $this->repository->save($conversation);
            }
        } catch (\Throwable $e) {
            // Log error but don't crash - conversation state remains unchanged
            error_log(
                "Failed to summarize conversation {$conversation->id}: {$e->getMessage()} "
                . "in {$e->getFile()}:{$e->getLine()}"
            );
            // Re-throw to allow caller to handle if needed
            throw $e;
        }
    }

    /**
     * Delete a conversation.
     */
    public function delete(string $id): bool
    {
        unset($this->conversations[$id]);

        if ($this->repository) {
            return $this->repository->delete($id);
        }

        return true;
    }

    /**
     * Get all conversations for a user.
     *
     * @return Conversation[]
     */
    public function findByUserId(string $userId): array
    {
        if ($this->repository) {
            $conversations = $this->repository->findByUserId($userId);

            // Cache in memory
            foreach ($conversations as $conversation) {
                $this->conversations[$conversation->id] = $conversation;
            }

            return $conversations;
        }

        // Fall back to in-memory only
        return array_values(array_filter(
            $this->conversations,
            fn($conv) => $conv->userId === $userId
        ));
    }

    /**
     * Search conversations.
     *
     * @return Conversation[]
     */
    public function search(string $query, ?string $userId = null): array
    {
        if ($this->repository) {
            return $this->repository->search($query, $userId);
        }

        // Fall back to in-memory search
        $conversations = $userId
            ? $this->findByUserId($userId)
            : array_values($this->conversations);

        return array_filter($conversations, function ($conversation) use ($query) {
            return str_contains(strtolower($conversation->title), strtolower($query));
        });
    }

    /**
     * Get conversations with pagination.
     *
     * @return array{conversations: Conversation[], total: int, page: int, perPage: int}
     */
    public function paginate(int $page = 1, int $perPage = 20, ?string $userId = null): array
    {
        if ($this->repository) {
            return $this->repository->paginate($page, $perPage, $userId);
        }

        // Fall back to in-memory pagination
        $all = $userId ? $this->findByUserId($userId) : array_values($this->conversations);
        $offset = ($page - 1) * $perPage;
        $conversations = array_slice($all, $offset, $perPage);

        return [
            'conversations' => $conversations,
            'total' => count($all),
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * Convenience method to get or create a conversation.
     *
     * @param array<string, mixed> $config
     */
    public function conversation(
        string $id,
        ?string $userId = null,
        ?string $sessionId = null,
        array $config = [],
    ): Conversation {
        // Merge with default config
        $mergedConfig = array_merge($this->defaultConfig, $config);

        $conversation = $this->find($id);

        if (! $conversation) {
            $conversation = Conversation::create(
                id: $id,
                userId: $userId,
                sessionId: $sessionId,
                config: $mergedConfig,
            );
            $this->conversations[$id] = $conversation;

            if ($this->repository) {
                $this->repository->save($conversation);
            }
        }

        return $conversation;
    }

    /**
     * Convenience method to send a chat message.
     *
     * @param array<string, mixed> $options
     */
    public function chat(
        string $conversationId,
        string $userMessage,
        string $model = 'gpt-4o',
        array $options = [],
    ): ChatResponse {
        // Get or create conversation (will need userId from somewhere)
        $conversation = $this->find($conversationId);
        if (! $conversation) {
            throw new \RuntimeException("Conversation {$conversationId} not found. Create it first using conversation().");
        }

        // Add user message
        $this->addMessage($conversation, 'user', $userMessage);

        // Check if summarization is needed
        if ($conversation->needsSummarization()) {
            $this->summarize($conversation, $this->provider);
        }

        // Get messages for LLM (includes summary optimization)
        $messages = $conversation->getMessagesForLLM();

        // Extract known parameters from options
        $temperature = $options['temperature'] ?? null;
        $maxTokens = $options['maxTokens'] ?? null;
        $tools = $options['tools'] ?? null;
        $toolChoice = $options['toolChoice'] ?? null;

        // Remove extracted parameters from options
        $remainingOptions = array_diff_key($options, array_flip(['temperature', 'maxTokens', 'tools', 'toolChoice']));

        // Send to LLM
        $response = $this->provider->chat(
            $model,
            $messages,
            $temperature,
            $maxTokens,
            $tools,
            $toolChoice,
            $remainingOptions,
        );

        // Add assistant response
        $this->addMessage(
            $conversation,
            'assistant',
            $response->content,
            [
                'model' => $model,
                'tokens' => $response->usage?->totalTokens ?? 0,
                'cost' => 0, // Calculate based on model pricing
            ]
        );

        return $response;
    }

    /**
     * Get statistics for a conversation.
     *
     * @return array{total_messages: int, messages_summarized: int, summary: ?string, summary_token_count: int, token_savings: int, cost_savings: float, total_tokens_used: int, total_cost: float}
     */
    public function getStats(string $conversationId): array
    {
        $conversation = $this->find($conversationId);
        if (! $conversation) {
            throw new \RuntimeException("Conversation {$conversationId} not found.");
        }

        $tokenSavings = $conversation->getTokenSavings();
        // Estimate cost savings (rough calculation)
        $costSavings = $tokenSavings * 0.000001; // Rough estimate

        return [
            'total_messages' => $conversation->getTotalMessages(),
            'messages_summarized' => $conversation->messagesSummarized,
            'summary' => $conversation->summary,
            'summary_token_count' => $conversation->summaryTokenCount,
            'token_savings' => $tokenSavings,
            'cost_savings' => $costSavings,
            'total_tokens_used' => $conversation->getTotalTokensUsed(),
            'total_cost' => $conversation->getTotalCost(),
        ];
    }

    /**
     * Get all conversations for a user (alias for findByUserId).
     *
     * @return Conversation[]
     */
    public function getUserConversations(string $userId): array
    {
        return $this->findByUserId($userId);
    }
}
