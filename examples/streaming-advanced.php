<?php

require __DIR__ . '/../bootstrap.php';

use AnyLLM\Streaming\SSEFormatter;
use AnyLLM\Streaming\StreamBuffer;
use AnyLLM\Streaming\StreamController;
use AnyLLM\Streaming\TokenCountingStream;

echo "=== Advanced Streaming Examples ===\n\n";

// =============================================
// Example 1: Stream Controller with Callbacks
// =============================================
echo "=== 1. Stream Controller with Callbacks ===\n\n";

$controller = new StreamController();

$controller
    ->onChunk(function ($content, $chunkNum, $tokens) {
        echo "Chunk #{$chunkNum}: {$content}";
        if ($tokens > 0) {
            echo " ({$tokens} tokens)";
        }
        echo "\n";
    })
    ->onProgress(function ($progress) {
        // You could send this to a progress bar
    })
    ->onComplete(function ($fullContent, $totalTokens, $chunks) {
        echo "\nComplete! Total: {$totalTokens} tokens in {$chunks} chunks\n";
    })
    ->onError(function ($error) {
        echo "\nError: {$error->getMessage()}\n";
    });

// Simulate streaming
$chunks = ['Hello', ' ', 'world', '!', ' How', ' are', ' you', '?'];
foreach ($chunks as $chunk) {
    $controller->processChunk($chunk, rand(1, 5));
    usleep(100000); // 100ms delay
}

$controller->complete();

echo "\n";

// =============================================
// Example 2: Pause and Resume
// =============================================
echo "=== 2. Pause and Resume ===\n\n";

$controller = new StreamController();

$controller->onChunk(function ($content) {
    echo $content;
});

// Start streaming
$chunks = str_split('This is a pauseable stream!');
foreach ($chunks as $i => $chunk) {
    if ($i === 10) {
        echo "\n[PAUSING...]\n";
        $controller->pause();

        // Simulate some work
        sleep(1);

        echo "[RESUMING...]\n";
        $controller->resume();
    }

    $controller->processChunk($chunk);
    usleep(50000);
}

echo "\n\n";

// =============================================
// Example 3: Cancellation
// =============================================
echo "=== 3. Stream Cancellation ===\n\n";

$controller = new StreamController();

$controller
    ->onChunk(function ($content) {
        echo $content;
    })
    ->onComplete(function () {
        echo "\nStream completed!\n";
    });

$chunks = str_split('This stream will be cancelled early!');
foreach ($chunks as $i => $chunk) {
    if ($i === 15) {
        echo "\n[CANCELLING...]\n";
        $controller->cancel();
    }

    $controller->processChunk($chunk);

    if ($controller->isCancelled()) {
        echo "Stream cancelled after {$controller->getProgress()['chunks_processed']} chunks\n";
        break;
    }
}

echo "\n";

// =============================================
// Example 4: Stream Buffer
// =============================================
echo "=== 4. Stream Buffer ===\n\n";

$buffer = new StreamBuffer(maxSize: 1000, flushThreshold: 5);

echo "Adding chunks to buffer:\n";
for ($i = 1; $i <= 10; $i++) {
    $buffer->add("Chunk {$i}", rand(5, 15));
    echo "  Added chunk {$i} (buffer size: {$buffer->size()})\n";

    if ($buffer->size() >= 5) {
        $flushed = $buffer->flush();
        echo "  → Flushed " . count($flushed) . " chunks\n";
    }
}

echo "\n";

// =============================================
// Example 5: SSE (Server-Sent Events) Formatting
// =============================================
echo "=== 5. SSE (Server-Sent Events) Formatting ===\n\n";

echo "SSE formatted output:\n";
echo str_repeat('-', 50) . "\n";

// Data chunk
echo SSEFormatter::formatChunk("Hello from SSE");

// JSON chunk
echo SSEFormatter::formatJsonChunk([
    'text' => 'This is JSON',
    'tokens' => 10,
], eventType: 'message', id: '1');

// Progress update
echo SSEFormatter::formatProgress([
    'chunks' => 5,
    'tokens' => 50,
    'elapsed' => 1.5,
]);

// Completion
echo SSEFormatter::formatComplete(['status' => 'success', 'total_tokens' => 100]);

// Error
echo SSEFormatter::formatError('Something went wrong', ['code' => 500]);

// Keep-alive
echo SSEFormatter::sendKeepAlive();

echo str_repeat('-', 50) . "\n\n";

// =============================================
// Example 6: Token Counting Stream
// =============================================
echo "=== 6. Token Counting Stream ===\n\n";

$controller = new StreamController();
$tokenCounter = new TokenCountingStream($controller, 'gpt-4');

$controller->onChunk(function ($content) {
    echo $content;
});

$text = "This is a test sentence that will be streamed and counted.";
$chunks = str_split($text);

foreach ($chunks as $chunk) {
    $controller->processChunk($chunk);
}

$controller->complete();

echo "\n\nToken Statistics:\n";
$stats = $tokenCounter->getStats();
echo "- Estimated tokens: {$stats['estimated_tokens']}\n";
echo "- Model: {$stats['model']}\n\n";

// =============================================
// Example 7: Progress Tracking
// =============================================
echo "=== 7. Progress Tracking ===\n\n";

$controller = new StreamController();

$controller
    ->onChunk(function ($content) {
        echo $content;
    })
    ->onProgress(function ($progress) {
        // Clear line and show progress
        echo "\r\033[K";
        echo sprintf(
            "Progress: %d chunks, %d tokens, %.2fs elapsed",
            $progress['chunks_processed'],
            $progress['total_tokens'],
            $progress['elapsed_seconds']
        );
    });

$longText = str_repeat("This is a longer text for progress tracking. ", 10);
$chunks = explode(' ', $longText);

foreach ($chunks as $chunk) {
    $controller->processChunk($chunk . ' ', rand(1, 3));
    usleep(50000);
}

echo "\n\n";

// =============================================
// Example 8: Buffered Streaming
// =============================================
echo "=== 8. Buffered Streaming ===\n\n";

$controller = new StreamController();
$buffer = new StreamBuffer(flushThreshold: 10);

$controller->onChunk(function ($content) use ($buffer) {
    $buffer->add($content);

    if ($buffer->size() >= 10) {
        $flushed = $buffer->flush();
        echo "[Flushing " . count($flushed) . " chunks] ";
        foreach ($flushed as $chunk) {
            echo $chunk['content'];
        }
    }
});

$controller->onComplete(function () use ($buffer) {
    // Flush remaining
    if (! $buffer->isEmpty()) {
        $remaining = $buffer->flush();
        echo "[Final flush: " . count($remaining) . " chunks] ";
        foreach ($remaining as $chunk) {
            echo $chunk['content'];
        }
    }
});

$chunks = str_split('Buffered streaming allows for efficient processing!');
foreach ($chunks as $chunk) {
    $controller->processChunk($chunk);
}

$controller->complete();

echo "\n\n";

// =============================================
// Example 9: Error Handling in Streams
// =============================================
echo "=== 9. Error Handling in Streams ===\n\n";

$controller = new StreamController();

$controller
    ->onChunk(function ($content) {
        echo $content;
    })
    ->onError(function ($error, $partialContent, $chunks) {
        echo "\n\nError occurred after {$chunks} chunks!\n";
        echo "Error: {$error->getMessage()}\n";
        echo "Partial content: " . substr($partialContent, 0, 50) . "...\n";
    });

try {
    $chunks = str_split('This will error ');
    foreach ($chunks as $chunk) {
        $controller->processChunk($chunk);
    }

    // Simulate an error
    throw new \Exception("Simulated streaming error");

} catch (\Exception $e) {
    $controller->error($e);
}

echo "\n";

// =============================================
// Example 10: Real-World Streaming Scenario
// =============================================
echo "=== 10. Real-World Streaming Scenario ===\n\n";

function simulateStreamingResponse(): void
{
    $controller = new StreamController();
    $buffer = new StreamBuffer(flushThreshold: 5);
    $tokenCounter = new TokenCountingStream($controller);

    // Set up callbacks
    $controller
        ->onChunk(function ($content, $chunkNum) use ($buffer) {
            // Add to buffer
            $buffer->add($content);

            // Flush when threshold reached
            if ($buffer->size() >= 5) {
                $chunks = $buffer->flush();
                foreach ($chunks as $chunk) {
                    echo $chunk['content'];

                    // Could send via SSE to web client:
                    // echo SSEFormatter::formatChunk($chunk['content']);
                    // SSEFormatter::flush();
                }
            }
        })
        ->onProgress(function ($progress) {
            // Update progress (e.g., via WebSocket or SSE)
            // In CLI, we'll just track it
        })
        ->onComplete(function ($fullContent, $totalTokens) use ($buffer, $tokenCounter, $controller) {
            // Flush remaining
            if (! $buffer->isEmpty()) {
                foreach ($buffer->flush() as $chunk) {
                    echo $chunk['content'];
                }
            }

            echo "\n\n";
            echo "Stream complete:\n";
            echo "- Content length: " . strlen($fullContent) . " chars\n";
            echo "- Estimated tokens: " . $tokenCounter->getEstimatedTokens() . "\n";
            echo "- Duration: " . round($controller->getElapsedTime(), 2) . "s\n";
        })
        ->onError(function ($error) {
            echo "\nStreaming error: {$error->getMessage()}\n";
        });

    // Simulate streaming from LLM
    $response = "This is a simulated LLM response that demonstrates real-world streaming. ";
    $response .= "It includes multiple sentences and shows how buffering and token counting work together. ";
    $response .= "In a real application, these chunks would come from the LLM provider's streaming API.";

    $words = explode(' ', $response);
    foreach ($words as $word) {
        $controller->processChunk($word . ' ', rand(1, 3));
        usleep(50000); // Simulate network delay

        // Could check for pause/cancel here
        if ($controller->isCancelled()) {
            break;
        }
    }

    $controller->complete();
}

simulateStreamingResponse();

echo "\n=== All Streaming Examples Complete! ===\n\n";

echo "Key Features:\n";
echo "- StreamController: Pause/resume/cancel, callbacks\n";
echo "- StreamBuffer: Efficient chunk buffering\n";
echo "- SSEFormatter: Format for web streaming\n";
echo "- TokenCountingStream: Real-time token counting\n\n";

echo "Use Cases:\n";
echo "- Real-time chat interfaces\n";
echo "- Progress indicators for long responses\n";
echo "- Buffered processing for efficiency\n";
echo "- Server-Sent Events for web apps\n";
echo "- User-controlled streaming (pause/cancel)\n";
echo "- Token/cost tracking during streaming\n";
