<?php

declare(strict_types=1);

namespace AnyLLM\Conversations\Repository;

use AnyLLM\Conversations\Conversation;
use AnyLLM\Conversations\ConversationMessage;
use AnyLLM\Exceptions\AnyLLMException;

final class FileConversationRepository implements ConversationRepositoryInterface
{
    private string $storagePath;

    public function __construct(?string $storagePath = null)
    {
        $this->storagePath = $storagePath ?? sys_get_temp_dir() . '/anyllm-conversations';
        $this->ensureStorageDirectory();
    }

    public function save(Conversation $conversation): void
    {
        $path = $this->getPath($conversation->id);
        $data = $this->serialize($conversation);

        if (file_put_contents($path, $data, LOCK_EX) === false) {
            throw new AnyLLMException("Failed to save conversation to file: {$path}");
        }

        // Update user index
        $this->updateUserIndex($conversation->userId, $conversation->id);
    }

    public function find(string $id): ?Conversation
    {
        $path = $this->getPath($id);

        if (! file_exists($path)) {
            return null;
        }

        $data = file_get_contents($path);
        if ($data === false) {
            return null;
        }

        return $this->deserialize($data);
    }

    public function findByUserId(string $userId): array
    {
        $ids = $this->getUserConversationIds($userId);
        
        return array_values(array_filter(array_map(
            fn($id) => $this->find($id),
            $ids
        )));
    }

    public function findByMetadata(string $key, mixed $value): array
    {
        $files = glob($this->storagePath . '/*.json');
        if ($files === false) {
            return [];
        }

        $results = [];
        foreach ($files as $file) {
            if (str_ends_with($file, '-index.json')) {
                continue;
            }

            $conversation = $this->find(basename($file, '.json'));
            if ($conversation && isset($conversation->metadata[$key]) && $conversation->metadata[$key] === $value) {
                $results[] = $conversation;
            }
        }

        return $results;
    }

    public function delete(string $id): bool
    {
        $conversation = $this->find($id);
        if (! $conversation) {
            return false;
        }

        $path = $this->getPath($id);
        
        if (file_exists($path)) {
            unlink($path);
        }

        // Update user index
        $this->removeFromUserIndex($conversation->userId, $id);

        return true;
    }

    public function exists(string $id): bool
    {
        return file_exists($this->getPath($id));
    }

    public function count(?string $userId = null): int
    {
        if ($userId) {
            return count($this->getUserConversationIds($userId));
        }

        $files = glob($this->storagePath . '/*.json');
        if ($files === false) {
            return 0;
        }

        // Exclude index files
        return count(array_filter($files, fn($file) => ! str_ends_with($file, '-index.json')));
    }

    public function paginate(int $page = 1, int $perPage = 20, ?string $userId = null): array
    {
        $all = $userId ? $this->findByUserId($userId) : $this->findAll();
        
        // Sort by creation date
        usort($all, fn($a, $b) => $b->createdAt <=> $a->createdAt);

        $offset = ($page - 1) * $perPage;
        $conversations = array_slice($all, $offset, $perPage);

        return [
            'conversations' => $conversations,
            'total' => count($all),
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    public function search(string $query, ?string $userId = null): array
    {
        $conversations = $userId ? $this->findByUserId($userId) : $this->findAll();
        $query = strtolower($query);

        return array_values(array_filter($conversations, function($conversation) use ($query) {
            // Search in title
            if (str_contains(strtolower($conversation->title), $query)) {
                return true;
            }

            // Search in messages
            foreach ($conversation->messages as $message) {
                if (str_contains(strtolower($message->content), $query)) {
                    return true;
                }
            }

            return false;
        }));
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
        $conversations = $userId ? $this->findByUserId($userId) : $this->findAll();

        return array_values(array_filter($conversations, function($conversation) use ($start, $end) {
            return $conversation->createdAt >= $start && $conversation->createdAt <= $end;
        }));
    }

    private function findAll(): array
    {
        $files = glob($this->storagePath . '/*.json');
        if ($files === false) {
            return [];
        }

        $conversations = [];
        foreach ($files as $file) {
            if (str_ends_with($file, '-index.json')) {
                continue;
            }

            $conversation = $this->find(basename($file, '.json'));
            if ($conversation) {
                $conversations[] = $conversation;
            }
        }

        return $conversations;
    }

    private function getPath(string $id): string
    {
        return $this->storagePath . '/' . $id . '.json';
    }

    private function getUserIndexPath(string $userId): string
    {
        return $this->storagePath . '/user-' . md5($userId) . '-index.json';
    }

    private function getUserConversationIds(string $userId): array
    {
        $path = $this->getUserIndexPath($userId);
        
        if (! file_exists($path)) {
            return [];
        }

        $data = file_get_contents($path);
        if ($data === false) {
            return [];
        }

        return json_decode($data, true) ?? [];
    }

    private function updateUserIndex(string $userId, string $conversationId): void
    {
        $ids = $this->getUserConversationIds($userId);
        
        if (! in_array($conversationId, $ids, true)) {
            $ids[] = $conversationId;
        }

        $path = $this->getUserIndexPath($userId);
        file_put_contents($path, json_encode($ids), LOCK_EX);
    }

    private function removeFromUserIndex(string $userId, string $conversationId): void
    {
        $ids = $this->getUserConversationIds($userId);
        $ids = array_values(array_filter($ids, fn($id) => $id !== $conversationId));

        $path = $this->getUserIndexPath($userId);
        file_put_contents($path, json_encode($ids), LOCK_EX);
    }

    private function ensureStorageDirectory(): void
    {
        if (! is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
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

        return json_encode($data, JSON_PRETTY_PRINT);
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

