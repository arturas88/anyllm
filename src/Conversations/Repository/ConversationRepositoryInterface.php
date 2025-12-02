<?php

declare(strict_types=1);

namespace AnyLLM\Conversations\Repository;

use AnyLLM\Conversations\Conversation;

interface ConversationRepositoryInterface
{
    /**
     * Save a conversation to storage.
     */
    public function save(Conversation $conversation): void;

    /**
     * Find a conversation by ID.
     */
    public function find(string $id): ?Conversation;

    /**
     * Find all conversations for a user.
     *
     * @return Conversation[]
     */
    public function findByUserId(string $userId): array;

    /**
     * Find conversations by metadata.
     *
     * @return Conversation[]
     */
    public function findByMetadata(string $key, mixed $value): array;

    /**
     * Delete a conversation.
     */
    public function delete(string $id): bool;

    /**
     * Check if a conversation exists.
     */
    public function exists(string $id): bool;

    /**
     * Get the total number of conversations.
     */
    public function count(?string $userId = null): int;

    /**
     * Get conversations with pagination.
     *
     * @return array{conversations: Conversation[], total: int, page: int, perPage: int}
     */
    public function paginate(int $page = 1, int $perPage = 20, ?string $userId = null): array;

    /**
     * Search conversations by title or content.
     *
     * @return Conversation[]
     */
    public function search(string $query, ?string $userId = null): array;

    /**
     * Update conversation metadata.
     *
     * @param array<string, mixed> $metadata
     */
    public function updateMetadata(string $id, array $metadata): bool;

    /**
     * Get conversations created within a date range.
     *
     * @return Conversation[]
     */
    public function findByDateRange(\DateTimeInterface $start, \DateTimeInterface $end, ?string $userId = null): array;
}
