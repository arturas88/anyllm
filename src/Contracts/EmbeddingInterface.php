<?php

declare(strict_types=1);

namespace AnyLLM\Contracts;

use AnyLLM\Responses\EmbeddingResponse;

interface EmbeddingInterface
{
    /**
     * Generate embeddings for the given input.
     *
     * @param string|array<string> $input Single text or array of texts to embed
     */
    public function embed(string $model, string|array $input): EmbeddingResponse;

    /**
     * Calculate cosine similarity between two embeddings.
     *
     * @param array<float> $embedding1
     * @param array<float> $embedding2
     */
    public function similarity(array $embedding1, array $embedding2): float;
}

