<?php

declare(strict_types=1);

namespace AnyLLM\Responses;

use AnyLLM\Responses\Parts\Usage;

final class EmbeddingResponse extends Response
{
    /**
     * @param array<array<float>> $embeddings Array of embedding vectors
     * @param array<string, mixed>|null $metadata
     * @param array<string, mixed>|null $raw
     */
    public function __construct(
        public readonly array $embeddings,
        ?string $model = null,
        ?Usage $usage = null,
        public readonly ?array $metadata = null,
        ?string $id = null,
        ?array $raw = null,
    ) {
        parent::__construct($id, $model, $usage, $raw);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): static
    {
        $dataArray = is_array($data['data'] ?? null) ? $data['data'] : [];
        $embeddings = array_map(
            fn($item) => (is_array($item) && isset($item['embedding']) && is_array($item['embedding'])) ? $item['embedding'] : [],
            $dataArray
        );

        return new self(
            embeddings: $embeddings,
            model: $data['model'] ?? null,
            usage: isset($data['usage']) ? Usage::fromArray($data['usage']) : null,
            metadata: $data['metadata'] ?? null,
            id: $data['id'] ?? null,
            raw: $data,
        );
    }

    /**
     * Get a single embedding by index.
     *
     * @return array<float>
     */
    public function getEmbedding(int $index = 0): array
    {
        return $this->embeddings[$index] ?? [];
    }

    /**
     * Get the number of embeddings.
     */
    public function count(): int
    {
        return count($this->embeddings);
    }

    /**
     * Calculate cosine similarity between two embeddings.
     */
    public function similarity(int $index1, int $index2): float
    {
        $embedding1 = $this->getEmbedding($index1);
        $embedding2 = $this->getEmbedding($index2);

        if (empty($embedding1) || empty($embedding2)) {
            return 0.0;
        }

        return $this->cosineSimilarity($embedding1, $embedding2);
    }

    /**
     * Find the most similar embedding to the given index.
     *
     * @return array{index: int, similarity: float}
     */
    public function mostSimilar(int $index): array
    {
        $target = $this->getEmbedding($index);
        $maxSimilarity = -1.0;
        $maxIndex = -1;

        foreach ($this->embeddings as $i => $embedding) {
            if ($i === $index) {
                continue;
            }

            $similarity = $this->cosineSimilarity($target, $embedding);
            if ($similarity > $maxSimilarity) {
                $maxSimilarity = $similarity;
                $maxIndex = $i;
            }
        }

        return ['index' => $maxIndex, 'similarity' => $maxSimilarity];
    }

    /**
     * Get all pairwise similarities.
     *
     * @return array<array<float>>
     */
    public function allSimilarities(): array
    {
        $count = $this->count();
        $similarities = [];

        for ($i = 0; $i < $count; $i++) {
            $similarities[$i] = [];
            for ($j = 0; $j < $count; $j++) {
                if ($i === $j) {
                    $similarities[$i][$j] = 1.0;
                } else {
                    $similarities[$i][$j] = $this->similarity($i, $j);
                }
            }
        }

        return $similarities;
    }

    /**
     * Calculate cosine similarity between two vectors.
     *
     * @param array<float> $a
     * @param array<float> $b
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $magnitudeA += $a[$i] * $a[$i];
            $magnitudeB += $b[$i] * $b[$i];
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA == 0.0 || $magnitudeB == 0.0) {
            return 0.0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }

    /**
     * Calculate Euclidean distance between two embeddings.
     */
    public function distance(int $index1, int $index2): float
    {
        $embedding1 = $this->getEmbedding($index1);
        $embedding2 = $this->getEmbedding($index2);

        if (empty($embedding1) || empty($embedding2) || count($embedding1) !== count($embedding2)) {
            return PHP_FLOAT_MAX;
        }

        $sum = 0.0;
        for ($i = 0; $i < count($embedding1); $i++) {
            $diff = $embedding1[$i] - $embedding2[$i];
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }

    /**
     * Get the dimension of the embeddings.
     */
    public function dimension(): int
    {
        return isset($this->embeddings[0]) ? count($this->embeddings[0]) : 0;
    }
}
