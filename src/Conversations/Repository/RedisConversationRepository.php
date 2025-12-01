<?php

declare(strict_types=1);

namespace AnyLLM\Conversations\Repository;

use AnyLLM\Conversations\Conversation;
use AnyLLM\Conversations\ConversationMessage;
use AnyLLM\Exceptions\AnyLLMException;

final class RedisConversationRepository implements ConversationRepositoryInterface
{
    private const KEY_PREFIX = 'anyllm:conversation:';
    private const INDEX_PREFIX = 'anyllm:user:conversations:';
    private const ALL_KEY = 'anyllm:conversations:all';

    public function __construct(
        private \Redis $redis,
    ) {}

    public function save(Conversation $conversation): void
    {
        $key = $this->getKey($conversation->id);
        $data = $this->serialize($conversation);

        // Save conversation
        $this->redis->set($key, $data);

        // Add to user index
        $userKey = $this->getUserKey($conversation->userId);
        $this->redis->zAdd($userKey, time(), $conversation->id);

        // Add to global index
        $this->redis->zAdd(self::ALL_KEY, time(), $conversation->id);
    }

    public function find(string $id): ?Conversation
    {
        $key = $this->getKey($id);
        $data = $this->redis->get($key);

        if ($data === false) {
            return null;
        }

        return $this->deserialize($data);
    }

    public function findByUserId(string $userId): array
    {
        $userKey = $this->getUserKey($userId);
        $ids = $this->redis->zRevRange($userKey, 0, -1);

        return array_filter(array_map(
            fn($id) => $this->find($id),
            $ids
        ));
    }

    public function findByMetadata(string $key, mixed $value): array
    {
        // Get all conversations and filter by metadata
        $ids = $this->redis->zRevRange(self::ALL_KEY, 0, -1);
        
        return array_filter(array_map(function($id) use ($key, $value) {
            $conversation = $this->find($id);
            if ($conversation && isset($conversation->metadata[$key]) && $conversation->metadata[$key] === $value) {
                return $conversation;
            }
            return null;
        }, $ids));
    }

    public function delete(string $id): bool
    {
        $conversation = $this->find($id);
        if (! $conversation) {
            return false;
        }

        $key = $this->getKey($id);
        $userKey = $this->getUserKey($conversation->userId);

        // Delete conversation
        $this->redis->del($key);

        // Remove from user index
        $this->redis->zRem($userKey, $id);

        // Remove from global index
        $this->redis->zRem(self::ALL_KEY, $id);

        return true;
    }

    public function exists(string $id): bool
    {
        return $this->redis->exists($this->getKey($id)) > 0;
    }

    public function count(?string $userId = null): int
    {
        if ($userId) {
            return $this->redis->zCard($this->getUserKey($userId));
        }

        return $this->redis->zCard(self::ALL_KEY);
    }

    public function paginate(int $page = 1, int $perPage = 20, ?string $userId = null): array
    {
        $offset = ($page - 1) * $perPage;
        $key = $userId ? $this->getUserKey($userId) : self::ALL_KEY;

        $ids = $this->redis->zRevRange($key, $offset, $offset + $perPage - 1);
        $conversations = array_filter(array_map(
            fn($id) => $this->find($id),
            $ids
        ));

        return [
            'conversations' => array_values($conversations),
            'total' => $this->count($userId),
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    public function search(string $query, ?string $userId = null): array
    {
        $ids = $userId 
            ? $this->redis->zRevRange($this->getUserKey($userId), 0, -1)
            : $this->redis->zRevRange(self::ALL_KEY, 0, -1);

        $query = strtolower($query);

        return array_values(array_filter(array_map(function($id) use ($query) {
            $conversation = $this->find($id);
            if (! $conversation) {
                return null;
            }

            // Search in title
            if (str_contains(strtolower($conversation->title), $query)) {
                return $conversation;
            }

            // Search in messages
            foreach ($conversation->messages as $message) {
                if (str_contains(strtolower($message->content), $query)) {
                    return $conversation;
                }
            }

            return null;
        }, $ids)));
    }

    public function updateMetadata(string $id, array $metadata): bool
    {
        $conversation = $this->find($id);
        if (! $conversation) {
            return false;
        }

        $conversation->metadata = $metadata;
        $conversation->updatedAt = new \DateTimeImmutable();
        
        $this->save($conversation);
        
        return true;
    }

    public function findByDateRange(\DateTimeInterface $start, \DateTimeInterface $end, ?string $userId = null): array
    {
        $key = $userId ? $this->getUserKey($userId) : self::ALL_KEY;
        $ids = $this->redis->zRevRange($key, 0, -1);

        return array_values(array_filter(array_map(function($id) use ($start, $end) {
            $conversation = $this->find($id);
            if (! $conversation) {
                return null;
            }

            if ($conversation->createdAt >= $start && $conversation->createdAt <= $end) {
                return $conversation;
            }

            return null;
        }, $ids)));
    }

    private function getKey(string $id): string
    {
        return self::KEY_PREFIX . $id;
    }

    private function getUserKey(string $userId): string
    {
        return self::INDEX_PREFIX . $userId;
    }

    private function serialize(Conversation $conversation): string
    {
        $data = [
            'id' => $conversation->id,
            'userId' => $conversation->userId,
            'title' => $conversation->title,
            'summary' => $conversation->summary,
            'totalTokens' => $conversation->totalTokens,
            'totalCost' => $conversation->totalCost,
            'metadata' => $conversation->metadata,
            'createdAt' => $conversation->createdAt->format('Y-m-d H:i:s'),
            'updatedAt' => $conversation->updatedAt->format('Y-m-d H:i:s'),
            'messages' => array_map(fn($msg) => [
                'id' => $msg->id,
                'role' => $msg->role,
                'content' => $msg->content,
                'tokens' => $msg->tokens,
                'cost' => $msg->cost,
                'metadata' => $msg->metadata,
                'createdAt' => $msg->createdAt->format('Y-m-d H:i:s'),
            ], $conversation->messages),
        ];

        return json_encode($data);
    }

    private function deserialize(string $data): Conversation
    {
        $array = json_decode($data, true);

        $conversation = new Conversation(
            userId: $array['userId'],
            title: $array['title'],
            id: $array['id'],
        );

        $conversation->summary = $array['summary'];
        $conversation->totalTokens = $array['totalTokens'];
        $conversation->totalCost = $array['totalCost'];
        $conversation->metadata = $array['metadata'];
        $conversation->createdAt = new \DateTimeImmutable($array['createdAt']);
        $conversation->updatedAt = new \DateTimeImmutable($array['updatedAt']);

        $conversation->messages = array_map(
            fn($msg) => new ConversationMessage(
                role: $msg['role'],
                content: $msg['content'],
                tokens: $msg['tokens'],
                cost: $msg['cost'],
                metadata: $msg['metadata'],
                id: $msg['id'],
                createdAt: new \DateTimeImmutable($msg['createdAt']),
            ),
            $array['messages'] ?? []
        );

        return $conversation;
    }
}

