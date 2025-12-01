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
        private readonly array $defaultConfig = [],
    ) {}

    /**
     * Create a new conversation.
     */
    public function create(string $userId, string $title = 'New Conversation'): Conversation
    {
        $conversation = new Conversation(
            userId: $userId,
            title: $title,
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
            $conversation = new Conversation(
                userId: $userId,
                title: $title,
                id: $id,
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
     */
    public function addMessage(Conversation $conversation, string $role, string $content, array $metadata = []): ConversationMessage
    {
        $message = new ConversationMessage(
            role: $role,
            content: $content,
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
     */
    public function chat(
        Conversation $conversation,
        ProviderInterface $provider,
        string $model = 'gpt-4o',
        array $options = [],
    ): ChatResponse {
        // Check if summarization is needed
        if ($conversation->shouldSummarize()) {
            $this->summarize($conversation, $provider);
        }

        // Get messages for LLM
        $messages = $conversation->getMessages();

        // Send to LLM
        $response = $provider->chat(
            model: $model,
            messages: $messages,
            ...$options,
        );

        // Add assistant response
        $this->addMessage(
            $conversation,
            'assistant',
            $response->content(),
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
     */
    public function summarize(Conversation $conversation, ProviderInterface $provider): void
    {
        $messages = $conversation->messages;
        
        if (count($messages) < 5) {
            return; // Not enough messages to summarize
        }

        // Build conversation text
        $conversationText = '';
        foreach ($messages as $msg) {
            $conversationText .= "{$msg->role}: {$msg->content}\n\n";
        }

        // Generate summary
        $prompt = "Summarize the following conversation concisely, preserving key information, " .
                  "decisions, and context. Be brief but comprehensive:\n\n{$conversationText}";

        $response = $provider->generateText(
            model: $this->summaryModel,
            prompt: $prompt,
        );

        $conversation->summary = $response->text;
        $conversation->updatedAt = new \DateTimeImmutable();

        if ($this->repository) {
            $this->repository->save($conversation);
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

        return array_filter($conversations, function($conversation) use ($query) {
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
}

