<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use AnyLLM\AnyLLM;

echo "=== Mistral AI OCR Demo ===\n\n";

/**
 * Mistral AI provides vision models with OCR capabilities
 * Perfect for extracting text from images, documents, receipts, etc.
 */

$mistral = AnyLLM::mistral(apiKey: $_ENV['MISTRAL_API_KEY'] ?? 'your-key');

// ============================================================================
// Example 1: Basic OCR - Extract Text from Image
// ============================================================================
echo "1. Basic OCR - Extract All Text\n";
echo str_repeat('-', 50) . "\n";

try {
    // Replace with your image URL or use base64
    $imageUrl = 'https://example.com/document.jpg';
    
    $result = $mistral->extractText(
        model: 'pixtral-12b-2409', // Mistral's vision model
        image: $imageUrl,
        prompt: 'Extract all text from this image. Be precise and maintain formatting.',
    );
    
    echo "Extracted Text:\n";
    echo $result['text'] . "\n";
    echo "\nTokens used: " . ($result['usage']['total_tokens'] ?? 'N/A') . "\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Example 2: Structured Data Extraction from Receipt
// ============================================================================
echo "2. Receipt OCR - Extract Structured Data\n";
echo str_repeat('-', 50) . "\n";

try {
    $receiptImageUrl = 'https://example.com/receipt.jpg';
    
    $result = $mistral->extractText(
        model: 'pixtral-12b-2409',
        image: $receiptImageUrl,
        prompt: 'Extract the following information from this receipt in JSON format: 
                merchant name, date, total amount, tax, items list (name and price), 
                and payment method.',
    );
    
    echo "Receipt Data:\n";
    echo $result['text'] . "\n";
    
    // Parse if returned as JSON
    try {
        $data = json_decode($result['text'], true);
        if ($data) {
            echo "\nParsed Receipt:\n";
            echo "Merchant: " . ($data['merchant'] ?? 'N/A') . "\n";
            echo "Date: " . ($data['date'] ?? 'N/A') . "\n";
            echo "Total: $" . ($data['total'] ?? 'N/A') . "\n";
        }
    } catch (\Exception $e) {
        // Not JSON, that's ok
    }
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Example 3: Business Card Extraction
// ============================================================================
echo "3. Business Card OCR\n";
echo str_repeat('-', 50) . "\n";

try {
    $businessCardUrl = 'https://example.com/business-card.jpg';
    
    $result = $mistral->extractText(
        model: 'pixtral-12b-2409',
        image: $businessCardUrl,
        prompt: 'Extract contact information from this business card: name, title, 
                company, email, phone, address. Format as JSON.',
    );
    
    echo "Business Card Info:\n";
    echo $result['text'] . "\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Example 4: Handwritten Text Recognition
// ============================================================================
echo "4. Handwritten Text OCR\n";
echo str_repeat('-', 50) . "\n";

try {
    $handwrittenUrl = 'https://example.com/handwritten-note.jpg';
    
    $result = $mistral->extractText(
        model: 'pixtral-12b-2409',
        image: $handwrittenUrl,
        prompt: 'Read and transcribe the handwritten text in this image. 
                Be careful with difficult-to-read characters.',
    );
    
    echo "Transcribed Text:\n";
    echo $result['text'] . "\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Example 5: Multi-Language Document
// ============================================================================
echo "5. Multi-Language OCR\n";
echo str_repeat('-', 50) . "\n";

try {
    $multiLangUrl = 'https://example.com/multilingual-doc.jpg';
    
    $result = $mistral->extractText(
        model: 'pixtral-12b-2409',
        image: $multiLangUrl,
        prompt: 'Extract all text from this document. Identify and label the language 
                of each section. Translate non-English sections to English.',
    );
    
    echo "Multi-Language Content:\n";
    echo $result['text'] . "\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Example 6: Form Field Extraction
// ============================================================================
echo "6. Form Field Extraction\n";
echo str_repeat('-', 50) . "\n";

try {
    $formUrl = 'https://example.com/filled-form.jpg';
    
    $result = $mistral->extractText(
        model: 'pixtral-12b-2409',
        image: $formUrl,
        prompt: 'This is a filled form. Extract all field labels and their corresponding 
                filled values. Return as JSON with field_label: value pairs.',
    );
    
    echo "Form Data:\n";
    echo $result['text'] . "\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Example 7: Invoice Processing
// ============================================================================
echo "7. Invoice OCR & Processing\n";
echo str_repeat('-', 50) . "\n";

try {
    $invoiceUrl = 'https://example.com/invoice.pdf';
    
    $result = $mistral->extractText(
        model: 'pixtral-12b-2409',
        image: $invoiceUrl,
        prompt: 'Extract invoice information: invoice number, date, due date, 
                vendor details, line items (description, quantity, price), 
                subtotal, tax, and total. Return as structured JSON.',
    );
    
    echo "Invoice Data:\n";
    echo $result['text'] . "\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================================
// Summary
// ============================================================================
echo str_repeat('=', 50) . "\n";
echo "✓ Mistral OCR Demo Complete!\n\n";

echo "Use Cases:\n";
echo "• Document digitization\n";
echo "• Receipt/invoice processing\n";
echo "• Business card scanning\n";
echo "• Form data extraction\n";
echo "• Handwritten text recognition\n";
echo "• Multi-language document processing\n";
echo "• ID/passport verification\n";
echo "• License plate recognition\n";
echo "• Medical prescription reading\n\n";

echo "Tips:\n";
echo "• Use pixtral-12b-2409 for vision tasks\n";
echo "• Be specific in your prompts\n";
echo "• Request JSON for structured data\n";
echo "• Test with high-quality images\n";
echo "• Combine with chat for analysis\n";

