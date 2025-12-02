<?php

require __DIR__ . '/../bootstrap.php';

use AnyLLM\AnyLLM;
use AnyLLM\Enums\Provider;
use AnyLLM\Support\VectorMath;

echo "=== Embeddings & Vector Operations Examples ===\n\n";

// Create OpenAI provider
$llm = AnyLLM::provider(Provider::OpenAI)
    ->apiKey(getenv('OPENAI_API_KEY'))
    ->build();

// =============================================
// Example 1: Basic Embeddings
// =============================================
echo "=== 1. Basic Embeddings ===\n\n";

$texts = [
    'The cat sat on the mat',
    'A feline rested on a rug',
    'Dogs are great pets',
    'Python is a programming language',
];

echo "Generating embeddings for " . count($texts) . " texts...\n";

$embeddings = $llm->embed('text-embedding-3-small', $texts);

echo "✓ Generated {$embeddings->count()} embeddings\n";
echo "Dimension: {$embeddings->dimension()}\n";
echo "Tokens used: {$embeddings->usage->totalTokens}\n\n";

// =============================================
// Example 2: Semantic Similarity
// =============================================
echo "=== 2. Semantic Similarity ===\n\n";

echo "Comparing texts:\n";
for ($i = 0; $i < count($texts); $i++) {
    echo ($i + 1) . ". \"{$texts[$i]}\"\n";
}
echo "\n";

// Compare similar sentences
$sim_cat_feline = $embeddings->similarity(0, 1);
echo "Similarity between 'cat' and 'feline': " . number_format($sim_cat_feline, 4) . " (high!)\n";

// Compare unrelated sentences
$sim_cat_dog = $embeddings->similarity(0, 2);
echo "Similarity between 'cat' and 'dog': " . number_format($sim_cat_dog, 4) . " (medium)\n";

$sim_cat_python = $embeddings->similarity(0, 3);
echo "Similarity between 'cat' and 'Python': " . number_format($sim_cat_python, 4) . " (low)\n\n";

// =============================================
// Example 3: Find Most Similar
// =============================================
echo "=== 3. Find Most Similar ===\n\n";

$mostSimilar = $embeddings->mostSimilar(0);
echo "Most similar to '{$texts[0]}':\n";
echo "→ '{$texts[$mostSimilar['index']]}' (similarity: " . number_format($mostSimilar['similarity'], 4) . ")\n\n";

// =============================================
// Example 4: All Pairwise Similarities
// =============================================
echo "=== 4. Pairwise Similarities Matrix ===\n\n";

$similarities = $embeddings->allSimilarities();

echo "     ";
for ($i = 0; $i < count($texts); $i++) {
    echo str_pad("T" . ($i + 1), 8);
}
echo "\n";

for ($i = 0; $i < count($texts); $i++) {
    echo "T" . ($i + 1) . "   ";
    for ($j = 0; $j < count($texts); $j++) {
        echo str_pad(number_format($similarities[$i][$j], 3), 8);
    }
    echo "\n";
}
echo "\n";

// =============================================
// Example 5: Distance Metrics
// =============================================
echo "=== 5. Distance Metrics ===\n\n";

$euclidean = $embeddings->distance(0, 1);
echo "Euclidean distance (cat vs feline): " . number_format($euclidean, 4) . "\n";

$embedding1 = $embeddings->getEmbedding(0);
$embedding2 = $embeddings->getEmbedding(1);
$manhattan = VectorMath::manhattanDistance($embedding1, $embedding2);
echo "Manhattan distance (cat vs feline): " . number_format($manhattan, 4) . "\n\n";

// =============================================
// Example 6: Semantic Search
// =============================================
echo "=== 6. Semantic Search ===\n\n";

$documents = [
    'Machine learning is a subset of artificial intelligence',
    'Deep learning uses neural networks with multiple layers',
    'Python is popular for data science and AI',
    'JavaScript is used for web development',
    'The Eiffel Tower is located in Paris',
    'Mount Everest is the highest mountain',
];

echo "Documents:\n";
foreach ($documents as $i => $doc) {
    echo ($i + 1) . ". {$doc}\n";
}
echo "\n";

// Embed documents
$docEmbeddings = $llm->embed('text-embedding-3-small', $documents);

// Search query
$query = 'neural networks and AI';
echo "Query: '{$query}'\n\n";

// Embed query
$queryEmbedding = $llm->embed('text-embedding-3-small', $query);

// Find most relevant documents
$results = VectorMath::kNearest(
    $queryEmbedding->getEmbedding(0),
    $docEmbeddings->embeddings,
    k: 3
);

echo "Top 3 most relevant documents:\n";
foreach ($results as $i => $result) {
    $docIndex = $result['index'];
    echo ($i + 1) . ". \"{$documents[$docIndex]}\" (similarity: " . number_format($result['similarity'], 4) . ")\n";
}
echo "\n";

// =============================================
// Example 7: Clustering Similar Items
// =============================================
echo "=== 7. Clustering Similar Items ===\n\n";

$items = [
    'apple', 'banana', 'orange',  // Fruits
    'car', 'bike', 'bus',         // Vehicles
    'dog', 'cat', 'rabbit',       // Animals
];

echo "Items to cluster:\n";
foreach ($items as $i => $item) {
    echo ($i + 1) . ". {$item}\n";
}
echo "\n";

$itemEmbeddings = $llm->embed('text-embedding-3-small', $items);

// Simple clustering: find which items are similar to each other
echo "Similarity clusters (threshold: 0.75):\n";

$clustered = [];
for ($i = 0; $i < count($items); $i++) {
    if (isset($clustered[$i])) {
        continue;
    }

    $cluster = [$items[$i]];
    $clustered[$i] = true;

    for ($j = $i + 1; $j < count($items); $j++) {
        if (isset($clustered[$j])) {
            continue;
        }

        $sim = $itemEmbeddings->similarity($i, $j);
        if ($sim > 0.75) {
            $cluster[] = $items[$j];
            $clustered[$j] = true;
        }
    }

    if (count($cluster) > 1) {
        echo "- " . implode(', ', $cluster) . "\n";
    }
}
echo "\n";

// =============================================
// Example 8: RAG (Retrieval-Augmented Generation)
// =============================================
echo "=== 8. RAG (Retrieval-Augmented Generation) ===\n\n";

$knowledge = [
    'Paris is the capital of France and known for the Eiffel Tower',
    'London is the capital of the UK and home to Big Ben',
    'Tokyo is the capital of Japan and known for its technology',
    'New York is a major city in the USA, famous for the Statue of Liberty',
];

echo "Knowledge base:\n";
foreach ($knowledge as $i => $fact) {
    echo ($i + 1) . ". {$fact}\n";
}
echo "\n";

// Embed knowledge base
$knowledgeEmbeddings = $llm->embed('text-embedding-3-small', $knowledge);

// User question
$question = 'Tell me about landmarks in Paris';
echo "Question: '{$question}'\n\n";

// Find relevant context
$questionEmbedding = $llm->embed('text-embedding-3-small', $question);
$relevant = VectorMath::kNearest(
    $questionEmbedding->getEmbedding(0),
    $knowledgeEmbeddings->embeddings,
    k: 2
);

echo "Most relevant knowledge:\n";
$context = [];
foreach ($relevant as $i => $result) {
    $fact = $knowledge[$result['index']];
    echo ($i + 1) . ". {$fact} (relevance: " . number_format($result['similarity'], 4) . ")\n";
    $context[] = $fact;
}
echo "\n";

// Now you would send the context + question to the LLM for a grounded answer
echo "RAG prompt would be:\n";
echo "Context: " . implode(' ', $context) . "\n";
echo "Question: {$question}\n";
echo "Answer: [LLM would generate answer based on context]\n\n";

// =============================================
// Example 9: Vector Math Operations
// =============================================
echo "=== 9. Vector Math Operations ===\n\n";

$vec1 = $embeddings->getEmbedding(0);
$vec2 = $embeddings->getEmbedding(1);

echo "Vector operations:\n";
echo "- Dot product: " . number_format(VectorMath::dotProduct($vec1, $vec2), 4) . "\n";
echo "- Magnitude v1: " . number_format(VectorMath::magnitude($vec1), 4) . "\n";
echo "- Magnitude v2: " . number_format(VectorMath::magnitude($vec2), 4) . "\n";

$normalized = VectorMath::normalize($vec1);
echo "- Normalized magnitude: " . number_format(VectorMath::magnitude($normalized), 4) . " (should be 1.0)\n\n";

// =============================================
// Example 10: Caching Embeddings for Performance
// =============================================
echo "=== 10. Caching Embeddings ===\n\n";

use AnyLLM\Support\FileCache;

$cache = new FileCache();

function getEmbeddingsCached(array $texts, $llm, $cache): array
{
    $cacheKey = 'embeddings:' . md5(json_encode($texts));

    return $cache->remember($cacheKey, function () use ($texts, $llm) {
        echo "Computing embeddings (not cached)...\n";
        $response = $llm->embed('text-embedding-3-small', $texts);
        return $response->embeddings;
    }, 3600);
}

// First call - computes
$cached1 = getEmbeddingsCached(['test text'], $llm, $cache);

// Second call - uses cache
$cached2 = getEmbeddingsCached(['test text'], $llm, $cache);

echo "✓ Embeddings cached for reuse\n\n";

echo "=== All Embeddings Examples Complete! ===\n\n";

echo "Use Cases:\n";
echo "- Semantic search\n";
echo "- Document similarity\n";
echo "- Clustering\n";
echo "- Classification\n";
echo "- RAG (Retrieval-Augmented Generation)\n";
echo "- Recommendation systems\n";
echo "- Duplicate detection\n";
echo "- Question answering\n\n";

echo "Cost Savings:\n";
echo "- Cache embeddings for frequently used texts\n";
echo "- Batch embed multiple texts at once\n";
echo "- Use smaller models (text-embedding-3-small) when possible\n";
