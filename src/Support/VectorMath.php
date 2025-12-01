<?php

declare(strict_types=1);

namespace AnyLLM\Support;

final class VectorMath
{
    /**
     * Calculate cosine similarity between two vectors.
     *
     * @param array<float> $a
     * @param array<float> $b
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            throw new \InvalidArgumentException('Vectors must have the same dimension');
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
     * Calculate Euclidean distance between two vectors.
     *
     * @param array<float> $a
     * @param array<float> $b
     */
    public static function euclideanDistance(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            throw new \InvalidArgumentException('Vectors must have the same dimension');
        }

        $sum = 0.0;
        for ($i = 0; $i < count($a); $i++) {
            $diff = $a[$i] - $b[$i];
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }

    /**
     * Calculate dot product of two vectors.
     *
     * @param array<float> $a
     * @param array<float> $b
     */
    public static function dotProduct(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            throw new \InvalidArgumentException('Vectors must have the same dimension');
        }

        $result = 0.0;
        for ($i = 0; $i < count($a); $i++) {
            $result += $a[$i] * $b[$i];
        }

        return $result;
    }

    /**
     * Calculate magnitude (length) of a vector.
     *
     * @param array<float> $vector
     */
    public static function magnitude(array $vector): float
    {
        $sum = 0.0;
        foreach ($vector as $value) {
            $sum += $value * $value;
        }

        return sqrt($sum);
    }

    /**
     * Normalize a vector to unit length.
     *
     * @param array<float> $vector
     * @return array<float>
     */
    public static function normalize(array $vector): array
    {
        $magnitude = self::magnitude($vector);

        if ($magnitude == 0.0) {
            return $vector;
        }

        return array_map(fn($v) => $v / $magnitude, $vector);
    }

    /**
     * Find the k nearest vectors to a query vector.
     *
     * @param array<float> $query
     * @param array<array<float>> $vectors
     * @return array<array{index: int, similarity: float, distance: float}>
     */
    public static function kNearest(array $query, array $vectors, int $k = 5): array
    {
        $similarities = [];

        foreach ($vectors as $index => $vector) {
            $similarities[] = [
                'index' => $index,
                'similarity' => self::cosineSimilarity($query, $vector),
                'distance' => self::euclideanDistance($query, $vector),
            ];
        }

        // Sort by similarity (descending)
        usort($similarities, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($similarities, 0, $k);
    }

    /**
     * Calculate Manhattan distance between two vectors.
     *
     * @param array<float> $a
     * @param array<float> $b
     */
    public static function manhattanDistance(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            throw new \InvalidArgumentException('Vectors must have the same dimension');
        }

        $sum = 0.0;
        for ($i = 0; $i < count($a); $i++) {
            $sum += abs($a[$i] - $b[$i]);
        }

        return $sum;
    }
}

