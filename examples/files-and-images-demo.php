<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AnyLLM\AnyLLM;
use AnyLLM\Messages\{SystemMessage, UserMessage};
use AnyLLM\Messages\Content\{FileContent, ImageContent, TextContent};

echo "=== Files and Images Demo (OpenAI & OpenRouter) ===\n\n";

/**
 * This example demonstrates how to use local and remote files (PDFs, images)
 * with OpenAI and OpenRouter providers.
 *
 * FileContent supports:
 * - Local files: /path/to/file.pdf
 * - Remote URLs: https://example.com/document.pdf
 *
 * ImageContent supports:
 * - Local images: /path/to/image.jpg
 * - Remote URLs: https://example.com/image.jpg
 */

// ============================================================================
// Setup Providers
// ============================================================================

$openai = AnyLLM::openai(apiKey: $_ENV['OPENAI_API_KEY'] ?? 'your-key');
$openrouter = AnyLLM::openrouter(apiKey: $_ENV['OPENROUTER_API_KEY'] ?? 'your-key');

// ============================================================================
// Example 1: Local Simple PDF with OpenAI
// ============================================================================
echo "1. Local Simple PDF with OpenAI\n";
echo str_repeat('-', 50) . "\n";

try {
    $localPdfPath = __DIR__ . '/document-structured.pdf';

    if (file_exists($localPdfPath)) {
        $response = $openai->chat(
            model: 'gpt-4o',
            messages: [
                UserMessage::withFiles(
                    text: 'Summarize the key points from this PDF document.',
                    files: [$localPdfPath], // FileContent::fromPath() is called automatically
                ),
            ],
        );

        echo "Response: {$response->content}\n";
        echo "Model: {$response->model}\n";
        echo "Tokens: {$response->usage?->totalTokens}\n";
    } else {
        echo "Note: File not found: {$localPdfPath}\n";
    }
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Example 2: Local Hard PDF with OpenAI
// ============================================================================
echo "2. Local Hard PDF with OpenAI\n";
echo str_repeat('-', 50) . "\n";

try {
    $hardPdfPath = __DIR__ . '/document-unordered.pdf';

    if (file_exists($hardPdfPath)) {
        $response = $openai->chat(
            model: 'gpt-4o',
            messages: [
                UserMessage::withFiles(
                    text: 'Analyze this complex PDF document. What are the main challenges in processing it?',
                    files: [$hardPdfPath],
                ),
            ],
        );

        echo "Response: {$response->content}\n";
        echo "Model: {$response->model}\n";
        echo "Tokens: {$response->usage?->totalTokens}\n";
    } else {
        echo "Note: File not found: {$hardPdfPath}\n";
    }
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Example 3: Explicit FileContent Usage with Simple PDF
// ============================================================================
echo "3. Explicit FileContent Usage\n";
echo str_repeat('-', 50) . "\n";

try {
    $localPdfPath = __DIR__ . '/document-structured.pdf';

    if (file_exists($localPdfPath)) {
        // Create FileContent explicitly for more control
        $fileContent = FileContent::fromPath($localPdfPath);

        echo "File loaded: {$fileContent->filename}\n";
        echo "Media type: {$fileContent->mediaType}\n";
        echo "Size: " . round(strlen(base64_decode($fileContent->data)) / 1024, 2) . " KB\n";
        echo "Format: " . json_encode($fileContent->toOpenAIFormat(), JSON_PRETTY_PRINT) . "\n\n";

        $response = $openai->chat(
            model: 'gpt-4o',
            messages: [
                UserMessage::withContent([
                    TextContent::create('Analyze this document and extract the main topic.'),
                    $fileContent,
                ]),
            ],
        );

        echo "Analysis: {$response->content}\n";
    } else {
        echo "Note: File not found: {$localPdfPath}\n";
    }
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Example 4: Local Structured Image with OpenAI
// ============================================================================
echo "4. Local Structured Image with OpenAI\n";
echo str_repeat('-', 50) . "\n";

try {
    $localImagePath = __DIR__ . '/image-structured.png';

    if (file_exists($localImagePath)) {
        $response = $openai->chat(
            model: 'gpt-4o',
            messages: [
                UserMessage::withImage(
                    text: 'Describe what you see in this structured image in detail. What patterns or organization do you notice?',
                    image: ImageContent::fromPath($localImagePath),
                ),
            ],
        );

        echo "Description: {$response->content}\n";
        echo "Model: {$response->model}\n";
    } else {
        echo "Note: File not found: {$localImagePath}\n";
    }
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Example 5: Local Chaotic Image with OpenAI
// ============================================================================
echo "5. Local Chaotic Image with OpenAI\n";
echo str_repeat('-', 50) . "\n";

try {
    $chaoticImagePath = __DIR__ . '/image-chaotic.png';

    if (file_exists($chaoticImagePath)) {
        $response = $openai->chat(
            model: 'gpt-4o',
            messages: [
                UserMessage::withImage(
                    text: 'Analyze this chaotic image. What elements can you identify despite the complexity?',
                    image: ImageContent::fromPath($chaoticImagePath),
                ),
            ],
        );

        echo "Analysis: {$response->content}\n";
        echo "Model: {$response->model}\n";
    } else {
        echo "Note: File not found: {$chaoticImagePath}\n";
    }
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Example 6: Multiple Images (Structured + Chaotic) with OpenAI
// ============================================================================
echo "6. Multiple Images (Structured + Chaotic) with OpenAI\n";
echo str_repeat('-', 50) . "\n";

try {
    $structuredImagePath = __DIR__ . '/image-structured.png';
    $chaoticImagePath = __DIR__ . '/image-chaotic.png';

    if (file_exists($structuredImagePath) && file_exists($chaoticImagePath)) {
        $response = $openai->chat(
            model: 'gpt-4o',
            messages: [
                UserMessage::withContent([
                    TextContent::create('Compare these two images - one structured and one chaotic. What are the key differences in their organization and visual patterns?'),
                    ImageContent::fromPath($structuredImagePath),
                    ImageContent::fromPath($chaoticImagePath),
                ]),
            ],
        );

        echo "Comparison: {$response->content}\n";
        echo "Model: {$response->model}\n";
    } else {
        echo "Note: Missing image files.\n";
        if (!file_exists($structuredImagePath)) {
            echo "  - Not found: {$structuredImagePath}\n";
        }
        if (!file_exists($chaoticImagePath)) {
            echo "  - Not found: {$chaoticImagePath}\n";
        }
    }
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Example 7: Local Simple PDF with OpenRouter
// ============================================================================
echo "7. Local Simple PDF with OpenRouter\n";
echo str_repeat('-', 50) . "\n";

try {
    $localPdfPath = __DIR__ . '/document-structured.pdf';

    if (file_exists($localPdfPath)) {
        $response = $openrouter->chat(
            model: 'openai/gpt-4o',
            messages: [
                UserMessage::withFiles(
                    text: 'Extract the main topics from this PDF document.',
                    files: [$localPdfPath],
                ),
            ],
        );

        echo "Response: {$response->content}\n";
        echo "Model used: {$response->model}\n";
        echo "Tokens: {$response->usage?->totalTokens}\n";
    } else {
        echo "Note: File not found: {$localPdfPath}\n";
    }
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Example 8: Local Hard PDF with OpenRouter
// ============================================================================
echo "8. Local Hard PDF with OpenRouter\n";
echo str_repeat('-', 50) . "\n";

try {
    $hardPdfPath = __DIR__ . '/document-unordered.pdf';

    if (file_exists($hardPdfPath)) {
        $response = $openrouter->chat(
            model: 'openai/gpt-4o',
            messages: [
                UserMessage::withFiles(
                    text: 'Analyze this complex PDF document. Extract key information and identify any processing challenges.',
                    files: [$hardPdfPath],
                ),
            ],
        );

        echo "Response: {$response->content}\n";
        echo "Model used: {$response->model}\n";
        echo "Tokens: {$response->usage?->totalTokens}\n";
    } else {
        echo "Note: File not found: {$hardPdfPath}\n";
    }
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Example 9: Local Structured Image with OpenRouter
// ============================================================================
echo "9. Local Structured Image with OpenRouter\n";
echo str_repeat('-', 50) . "\n";

try {
    $structuredImagePath = __DIR__ . '/image-structured.png';

    if (file_exists($structuredImagePath)) {
        $response = $openrouter->chat(
            model: 'openai/gpt-4o',
            messages: [
                UserMessage::withImage(
                    text: 'Analyze this structured image and describe its visual elements and organization in detail.',
                    image: ImageContent::fromPath($structuredImagePath),
                ),
            ],
        );

        echo "Analysis: {$response->content}\n";
        echo "Model used: {$response->model}\n";
    } else {
        echo "Note: File not found: {$structuredImagePath}\n";
    }
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Example 10: Mixed Content (PDF + Image) with OpenRouter
// ============================================================================
echo "10. Mixed Content (PDF + Image) with OpenRouter\n";
echo str_repeat('-', 50) . "\n";

try {
    $simplePdfPath = __DIR__ . '/document-structured.pdf';
    $structuredImagePath = __DIR__ . '/image-structured.png';

    if (file_exists($simplePdfPath) && file_exists($structuredImagePath)) {
        $response = $openrouter->chat(
            model: 'openai/gpt-4o',
            messages: [
                UserMessage::withContent([
                    TextContent::create('I have a PDF document and an image. Please analyze both and tell me if they are related in any way, or describe what each contains.'),
                    FileContent::fromPath($simplePdfPath),
                    ImageContent::fromPath($structuredImagePath),
                ]),
            ],
        );

        echo "Analysis: {$response->content}\n";
        echo "Model used: {$response->model}\n";
    } else {
        echo "Note: Missing files.\n";
        if (!file_exists($simplePdfPath)) {
            echo "  - Not found: {$simplePdfPath}\n";
        }
        if (!file_exists($structuredImagePath)) {
            echo "  - Not found: {$structuredImagePath}\n";
        }
    }
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Example 11: Multiple PDFs (Simple + Hard) with OpenAI
// ============================================================================
echo "11. Multiple PDFs (Simple + Hard) with OpenAI\n";
echo str_repeat('-', 50) . "\n";

try {
    $simplePdfPath = __DIR__ . '/document-structured.pdf';
    $hardPdfPath = __DIR__ . '/document-unordered.pdf';

    // Mix local PDFs
    $files = [];
    if (file_exists($simplePdfPath)) {
        $files[] = $simplePdfPath;
    }
    if (file_exists($hardPdfPath)) {
        $files[] = $hardPdfPath;
    }

    if (!empty($files)) {
        $response = $openai->chat(
            model: 'gpt-4o',
            messages: [
                UserMessage::withFiles(
                    text: 'Compare these two PDF documents. What are the key differences in complexity and content?',
                    files: $files,
                ),
            ],
        );

        echo "Comparison: {$response->content}\n";
        echo "Model: {$response->model}\n";
        echo "Tokens: {$response->usage?->totalTokens}\n";
    } else {
        echo "Note: Missing PDF files.\n";
        if (!file_exists($simplePdfPath)) {
            echo "  - Not found: {$simplePdfPath}\n";
        }
        if (!file_exists($hardPdfPath)) {
            echo "  - Not found: {$hardPdfPath}\n";
        }
    }
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Summary
// ============================================================================
echo str_repeat('=', 50) . "\n";
echo "✓ Files and Images Demo Complete!\n\n";

echo "Key Features Demonstrated:\n";
echo "• Local file support (PDFs, images)\n";
echo "• Remote URL support (HTTP/HTTPS)\n";
echo "• Automatic URL detection in fromPath()\n";
echo "• Explicit FileContent and ImageContent creation\n";
echo "• Multiple files in a single message\n";
echo "• Mixed content (text + files + images)\n";
echo "• Works with both OpenAI and OpenRouter\n\n";

echo "Usage Patterns:\n";
echo "1. Simple: UserMessage::withFiles('text', ['/path/to/file.pdf'])\n";
echo "2. Remote: UserMessage::withFiles('text', ['https://example.com/file.pdf'])\n";
echo "3. Images: UserMessage::withImage('text', 'https://example.com/image.jpg')\n";
echo "4. Explicit: FileContent::fromUrl('https://example.com/file.pdf')\n";
echo "5. Mixed: UserMessage::withContent([TextContent::create('text'), FileContent, ImageContent])\n\n";

echo "Supported File Types:\n";
echo "• PDFs: application/pdf\n";
echo "• Images: image/jpeg, image/png, image/gif, image/webp\n";
echo "• Documents: .docx, .xlsx, .csv, .txt, .md, etc.\n\n";

echo "Tips:\n";
echo "• FileContent automatically detects URLs vs local paths\n";
echo "• Remote files are fetched and base64-encoded\n";
echo "• Local files must exist and be readable\n";
echo "• Use cURL for better remote file handling (if available)\n";
echo "• Media types are auto-detected from content or file extension\n";
echo "• Both providers support vision models (gpt-4o, gpt-4-vision-preview)\n";
echo "• Images work reliably with both OpenAI and OpenRouter\n";
echo "• PDF support may be limited - OpenAI validates MIME types strictly\n";
echo "• For PDF processing, consider using Anthropic's Claude models\n";
echo "• ImageContent::fromUrl() passes URLs directly (OpenAI fetches them)\n";
echo "• FileContent::fromUrl() fetches and base64-encodes files locally\n";
