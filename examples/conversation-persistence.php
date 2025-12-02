<?php

require __DIR__ . '/../bootstrap.php';

use AnyLLM\AnyLLM;
use AnyLLM\Conversations\ConversationManager;
use AnyLLM\Conversations\Repository\ConversationRepositoryFactory;
use AnyLLM\Conversations\Repository\DatabaseConversationRepository;
use AnyLLM\Conversations\Repository\RedisConversationRepository;
use AnyLLM\Conversations\Repository\FileConversationRepository;
use AnyLLM\Enums\Provider;

echo "=== Conversation Persistence Examples ===\n\n";

// Create LLM provider
$llm = AnyLLM::openai(apiKey: getenv('OPENAI_API_KEY'));

// =============================================
// Example 1: Database Persistence
// =============================================
echo "=== 1. Database Persistence ===\n\n";

try {
    // Create database repository
    $dbRepository = ConversationRepositoryFactory::create('database', [
        'driver' => 'mysql',
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: 3306,
        'database' => getenv('DB_DATABASE') ?: 'anyllm',
        'username' => getenv('DB_USERNAME') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
    ]);

    $manager = new ConversationManager($llm, $dbRepository);

    // Create a new conversation
    $conversation = $manager->create('user-123', 'Database Example');
    echo "Created conversation: {$conversation->id}\n";

    // Add messages
    $manager->addMessage($conversation, 'user', 'Hello! Tell me about PHP.');
    echo "Added user message\n";

    // Get response from LLM
    $response = $manager->chatWithConversation($conversation, $llm);
    echo "Assistant: " . substr($response->content(), 0, 100) . "...\n\n";

    // Find conversation later
    $loaded = $manager->find($conversation->id);
    echo "Loaded conversation from database:\n";
    echo "- ID: {$loaded->id}\n";
    echo "- Title: {$loaded->title}\n";
    echo "- Messages: " . $loaded->getTotalMessages() . "\n";
    echo "- Total tokens: {$loaded->getTotalTokensUsed()}\n\n";

    // Search conversations
    $results = $manager->search('PHP', 'user-123');
    echo "Found " . count($results) . " conversations matching 'PHP'\n\n";

    // Paginate user's conversations
    $page = $manager->paginate(1, 10, 'user-123');
    echo "Page 1 of conversations: " . count($page['conversations']) . " / {$page['total']} total\n\n";

} catch (\Exception $e) {
    echo "Database error (expected if not configured): {$e->getMessage()}\n\n";
}

// =============================================
// Example 2: Redis Persistence
// =============================================
echo "=== 2. Redis Persistence ===\n\n";

try {
    // Create Redis repository
    $redisRepository = ConversationRepositoryFactory::create('redis', [
        'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
        'port' => getenv('REDIS_PORT') ?: 6379,
        'password' => getenv('REDIS_PASSWORD') ?: null,
        'database' => 0,
    ]);

    $manager = new ConversationManager($llm, $redisRepository);

    // Create conversation
    $conversation = $manager->create('user-456', 'Redis Example');
    echo "Created conversation in Redis: {$conversation->id}\n";

    // Add messages
    $manager->addMessage($conversation, 'user', 'What is Redis?');
    $manager->addMessage($conversation, 'assistant', 'Redis is an in-memory data structure store...');

    // Retrieve from Redis
    $loaded = $manager->find($conversation->id);
    echo "Loaded conversation from Redis: {$loaded->title}\n";
    echo "Messages: " . $loaded->getTotalMessages() . "\n\n";

    // Find all user conversations
    $userConversations = $manager->findByUserId('user-456');
    echo "User has " . count($userConversations) . " conversations\n\n";

} catch (\Exception $e) {
    echo "Redis error (expected if not configured): {$e->getMessage()}\n\n";
}

// =============================================
// Example 3: File Persistence
// =============================================
echo "=== 3. File Persistence ===\n\n";

// Create file repository
$fileRepository = new FileConversationRepository(__DIR__ . '/../storage/conversations');

$manager = new ConversationManager($llm, $fileRepository);

// Create conversation
$conversation = $manager->create('user-789', 'File Example');
echo "Created conversation in file: {$conversation->id}\n";

// Add some messages
$manager->addMessage($conversation, 'user', 'Tell me a joke');
$manager->addMessage($conversation, 'assistant', 'Why do programmers prefer dark mode? Because light attracts bugs!');

// The conversation is automatically saved to disk
echo "Conversation saved to: storage/conversations/{$conversation->id}.json\n";

// Load it back
$loaded = $manager->find($conversation->id);
echo "Loaded conversation from file: {$loaded->title}\n";
echo "Messages: " . $loaded->getTotalMessages() . "\n\n";

// Search in files
$results = $manager->search('joke');
echo "Found " . count($results) . " conversations about jokes\n\n";

// =============================================
// Example 4: In-Memory Only (No Persistence)
// =============================================
echo "=== 4. In-Memory Only (No Persistence) ===\n\n";

$manager = new ConversationManager($llm, null); // No repository

$conversation = $manager->create('user-999', 'Temporary');
echo "Created in-memory conversation: {$conversation->id}\n";

$manager->addMessage($conversation, 'user', 'This is temporary');
echo "Added message (will be lost when script ends)\n\n";

// =============================================
// Example 5: Advanced Operations
// =============================================
echo "=== 5. Advanced Operations ===\n\n";

$repository = new FileConversationRepository(__DIR__ . '/../storage/conversations');
$manager = new ConversationManager($llm, $repository);

// Create multiple conversations
for ($i = 1; $i <= 5; $i++) {
    $conv = $manager->create('user-advanced', "Conversation #{$i}");
    $manager->addMessage($conv, 'user', "Message in conversation {$i}");
}

echo "Created 5 conversations\n";

// Get all user conversations
$userConvs = $manager->findByUserId('user-advanced');
echo "User has " . count($userConvs) . " conversations\n";

// Paginate
$page1 = $manager->paginate(1, 2, 'user-advanced');
echo "Page 1: " . count($page1['conversations']) . " conversations\n";
echo "Total pages: " . ceil($page1['total'] / $page1['perPage']) . "\n\n";

// Delete a conversation
$toDelete = $userConvs[0];
$manager->delete($toDelete->id);
echo "Deleted conversation: {$toDelete->id}\n";

// Verify deletion
$deleted = $manager->find($toDelete->id);
echo "Conversation exists after deletion: " . ($deleted ? 'Yes' : 'No') . "\n\n";

// =============================================
// Example 6: Metadata and Search
// =============================================
echo "=== 6. Metadata and Search ===\n\n";

$conversation = $manager->create('user-metadata', 'Metadata Example');
$conversation->metadata = [
    'tags' => ['php', 'programming'],
    'category' => 'technical',
    'priority' => 'high',
];
$manager->save($conversation);

echo "Saved conversation with metadata\n";

// Search by metadata (if repository supports it)
if (method_exists($repository, 'findByMetadata')) {
    $results = $repository->findByMetadata('category', 'technical');
    echo "Found " . count($results) . " technical conversations\n";
}

// Update metadata
$repository->updateMetadata($conversation->id, [
    'tags' => ['php', 'programming', 'advanced'],
    'category' => 'technical',
    'priority' => 'urgent',
]);
echo "Updated conversation metadata\n\n";

// =============================================
// Example 7: Date Range Queries
// =============================================
echo "=== 7. Date Range Queries ===\n\n";

$yesterday = new \DateTime('-1 day');
$tomorrow = new \DateTime('+1 day');

$recent = $repository->findByDateRange($yesterday, $tomorrow);
echo "Conversations from last 24 hours: " . count($recent) . "\n\n";

echo "=== All examples completed! ===\n\n";

echo "Summary:\n";
echo "- Database: Full-featured SQL persistence\n";
echo "- Redis: Fast in-memory persistence with persistence\n";
echo "- File: Simple JSON file storage\n";
echo "- In-Memory: No persistence, lost on restart\n\n";

echo "Choose based on your needs:\n";
echo "- Production apps: Database or Redis\n";
echo "- Development/testing: File or In-Memory\n";
echo "- High performance: Redis\n";
echo "- Complex queries: Database\n";
