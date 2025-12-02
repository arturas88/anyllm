<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AnyLLM\AnyLLM;
use AnyLLM\Conversations\ConversationManager;

echo "=== Conversation Management with Auto-Summarization Demo ===\n\n";

/**
 * This example demonstrates the ConversationManager which provides:
 * 1. Conversation persistence
 * 2. Automatic summarization to save tokens
 * 3. Cost tracking
 * 4. Smart context management
 */

// ============================================================================
// Setup
// ============================================================================

$llm = AnyLLM::openai(apiKey: $_ENV['OPENAI_API_KEY'] ?? 'your-key');

// Create conversation manager with configuration
$manager = new ConversationManager(
    provider: $llm,
    summaryModel: 'gpt-4o-mini', // Use cheap model for summaries
    defaultConfig: [
        'auto_summarize' => true,        // Enable auto-summarization
        'summarize_after_messages' => 10, // Summarize after 10 messages (normally 20)
        'keep_recent_messages' => 3,     // Keep last 3 messages unsummarized
    ]
);

// ============================================================================
// Example 1: Basic Conversation
// ============================================================================
echo "1. Basic Conversation\n";
echo str_repeat('-', 50) . "\n";

$conversationId = 'demo-' . uniqid();

// Create conversation
$conversation = $manager->conversation(
    id: $conversationId,
    userId: 'user-123',
    sessionId: 'session-' . uniqid(),
);

echo "Created conversation: {$conversationId}\n";

// Have a conversation
$messages = [
    "Hi! I'm planning a trip to Japan.",
    "What are the must-see places in Tokyo?",
    "How about food recommendations?",
];

foreach ($messages as $index => $message) {
    echo "\nUser (" . ($index + 1) . "): {$message}\n";

    $response = $manager->chat(
        conversationId: $conversationId,
        userMessage: $message,
        model: 'gpt-4o-mini',
    );

    echo "Assistant: " . substr($response->content, 0, 100) . "...\n";
    echo "Tokens: {$response->usage?->totalTokens}\n";
}

// Show stats
$stats = $manager->getStats($conversationId);
echo "\nConversation Stats:\n";
echo "Total messages: {$stats['total_messages']}\n";
echo "Total tokens used: {$stats['total_tokens_used']}\n";
echo "Total cost: $" . number_format($stats['total_cost'], 4) . "\n";
echo "\n";

// ============================================================================
// Example 2: Long Conversation with Auto-Summarization
// ============================================================================
echo "2. Long Conversation with Auto-Summarization\n";
echo str_repeat('-', 50) . "\n";

$longConvId = 'long-demo-' . uniqid();
$longConv = $manager->conversation(
    id: $longConvId,
    userId: 'user-456',
);

echo "Simulating a long conversation...\n";

// Simulate many messages to trigger summarization
$topics = [
    "Tell me about PHP.",
    "What are the best PHP frameworks?",
    "How does Laravel differ from Symfony?",
    "What's new in PHP 8.2?",
    "Explain PHP-FIG standards.",
    "What are PSR standards?",
    "Tell me about Composer.",
    "How do I use namespaces in PHP?",
    "What are traits in PHP?",
    "Explain dependency injection.",
    "What is the repository pattern?",
    "How do I write unit tests in PHP?",
];

foreach ($topics as $index => $topic) {
    echo "\nMessage " . ($index + 1) . ": {$topic}\n";

    $response = $manager->chat(
        conversationId: $longConvId,
        userMessage: $topic,
        model: 'gpt-4o-mini',
    );

    echo "Response received (" . ($response->usage?->totalTokens ?? 0) . " tokens)\n";

    // Check if conversation was summarized
    $conv = $manager->conversation($longConvId);
    if ($conv->hasSummary() && $index > 0) {
        $prevConv = $manager->conversation($longConvId);
        if ($prevConv->messagesSummarized > 0) {
            echo "✓ Conversation was summarized!\n";
            echo "  Summary: " . substr($conv->summary ?? '', 0, 80) . "...\n";
            echo "  Messages summarized: {$conv->messagesSummarized}\n";
            echo "  Summary tokens: {$conv->summaryTokenCount}\n";
        }
    }
}

// Final stats
$longStats = $manager->getStats($longConvId);
echo "\n" . str_repeat('=', 50) . "\n";
echo "Final Stats for Long Conversation:\n";
echo str_repeat('=', 50) . "\n";
echo "Total messages: {$longStats['total_messages']}\n";
echo "Messages summarized: {$longStats['messages_summarized']}\n";
echo "Has summary: " . ($longStats['summary'] ? 'Yes' : 'No') . "\n";
echo "Summary token count: {$longStats['summary_token_count']}\n";
echo "Token savings: {$longStats['token_savings']}\n";
echo "Cost savings: $" . number_format($longStats['cost_savings'], 4) . "\n";
echo "Total tokens used: {$longStats['total_tokens_used']}\n";
echo "Total cost: $" . number_format($longStats['total_cost'], 4) . "\n";

if ($longStats['summary']) {
    echo "\nGenerated Summary:\n";
    echo $longStats['summary'] . "\n";
}
echo "\n";

// ============================================================================
// Example 3: Custom Configuration per Conversation
// ============================================================================
echo "3. Custom Configuration\n";
echo str_repeat('-', 50) . "\n";

$customConvId = 'custom-' . uniqid();
$customConv = $manager->conversation(
    id: $customConvId,
    userId: 'user-789',
    config: [
        'auto_summarize' => true,
        'summarize_after_messages' => 5,  // Aggressive summarization
        'keep_recent_messages' => 2,      // Keep only last 2 messages
    ]
);

echo "Created conversation with aggressive summarization (every 5 messages)\n";
echo "This is useful for long-running chats or memory-constrained scenarios\n\n";

// ============================================================================
// Example 4: Manual Summarization
// ============================================================================
echo "4. Manual Summarization\n";
echo str_repeat('-', 50) . "\n";

$manualConvId = 'manual-' . uniqid();
$manualConv = $manager->conversation(
    id: $manualConvId,
    config: ['auto_summarize' => false] // Disable auto-summarization
);

echo "Adding messages without auto-summarization...\n";
for ($i = 1; $i <= 5; $i++) {
    $manager->chat(
        conversationId: $manualConvId,
        userMessage: "Message {$i}",
        model: 'gpt-4o-mini',
    );
}

echo "Messages added: {$manualConv->getTotalMessages()}\n";
echo "Has summary: " . ($manualConv->hasSummary() ? 'Yes' : 'No') . "\n";

// Manually trigger summarization
echo "\nManually summarizing conversation...\n";
$manager->summarize($manualConv, $llm);

echo "Summary created: " . substr($manualConv->summary ?? '', 0, 100) . "...\n";
echo "Summary tokens: {$manualConv->summaryTokenCount}\n";
echo "\n";

// ============================================================================
// Example 5: Cost Optimization Comparison
// ============================================================================
echo "5. Cost Optimization Demonstration\n";
echo str_repeat('-', 50) . "\n";

echo "Comparing token usage:\n\n";

echo "WITHOUT summarization:\n";
echo "• 20 messages × ~500 tokens each = 10,000 tokens\n";
echo "• Sending all messages each time = expensive\n\n";

echo "WITH summarization:\n";
echo "• First 15 messages → Summary (~200 tokens)\n";
echo "• Keep last 5 messages (~2,500 tokens)\n";
echo "• Total: 2,700 tokens (73% reduction!)\n\n";

echo "💰 Token savings translate directly to cost savings!\n";
echo "\n";

// ============================================================================
// Example 6: User Conversation History
// ============================================================================
echo "6. User Conversation History\n";
echo str_repeat('-', 50) . "\n";

$userId = 'demo-user-' . uniqid();

// Create multiple conversations for the same user
for ($i = 1; $i <= 3; $i++) {
    $convId = "user-conv-{$i}-" . uniqid();
    $conv = $manager->conversation(
        id: $convId,
        userId: $userId,
    );

    $manager->chat(
        conversationId: $convId,
        userMessage: "Conversation {$i} message",
        model: 'gpt-4o-mini',
    );
}

$userConversations = $manager->getUserConversations($userId);
echo "User {$userId} has " . count($userConversations) . " conversations\n";

foreach ($userConversations as $conv) {
    echo "  - {$conv->id} ({$conv->getTotalMessages()} messages)\n";
}
echo "\n";

// ============================================================================
// Summary
// ============================================================================
echo str_repeat('=', 50) . "\n";
echo "✓ Conversation Management Demo Complete!\n\n";

echo "Key Features:\n";
echo "• Automatic conversation tracking\n";
echo "• Smart summarization to save tokens\n";
echo "• Configurable summarization thresholds\n";
echo "• Cost tracking and optimization\n";
echo "• Per-user conversation management\n";
echo "• Manual or automatic summarization\n";
echo "• Token and cost savings reporting\n\n";

echo "Use Cases:\n";
echo "• Long-running chat applications\n";
echo "• Customer support bots\n";
echo "• AI assistants with memory\n";
echo "• Multi-turn conversations\n";
echo "• Cost-optimized LLM applications\n\n";

echo "Configuration Options:\n";
echo "• auto_summarize: Enable/disable auto-summarization\n";
echo "• summarize_after_messages: Message threshold for summarization\n";
echo "• keep_recent_messages: How many recent messages to preserve\n";
echo "• summary_model: Which model to use for summarization\n";
