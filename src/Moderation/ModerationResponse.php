<?php

declare(strict_types=1);

namespace AnyLLM\Moderation;

/**
 * Response from content moderation API.
 */
final class ModerationResponse
{
    /**
     * @param array<string, bool> $categories Content categories flagged
     * @param array<string, float> $categoryScores Confidence scores for each category
     * @param bool $flagged Whether the content was flagged
     * @param string|null $id Optional moderation ID
     * @param string|null $model Optional model used for moderation
     */
    public function __construct(
        public readonly array $categories,
        public readonly array $categoryScores,
        public readonly bool $flagged,
        public readonly ?string $id = null,
        public readonly ?string $model = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $results = $data;
        if (isset($data['results']) && is_array($data['results']) && isset($data['results'][0]) && is_array($data['results'][0])) {
            $results = $data['results'][0];
        }

        return new self(
            categories: is_array($results['categories'] ?? null) ? $results['categories'] : [],
            categoryScores: $results['category_scores'] ?? [],
            flagged: $results['flagged'] ?? false,
            id: $data['id'] ?? null,
            model: $data['model'] ?? null,
        );
    }

    /**
     * Check if a specific category was flagged.
     */
    public function isFlagged(string $category): bool
    {
        return $this->categories[$category] ?? false;
    }

    /**
     * Get the score for a specific category.
     */
    public function getScore(string $category): float
    {
        return $this->categoryScores[$category] ?? 0.0;
    }

    /**
     * Get all flagged categories.
     *
     * @return array<string> List of category names that were flagged
     */
    public function getFlaggedCategories(): array
    {
        return array_keys(array_filter($this->categories, fn($flagged) => $flagged === true));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'model' => $this->model,
            'flagged' => $this->flagged,
            'categories' => $this->categories,
            'category_scores' => $this->categoryScores,
            'flagged_categories' => $this->getFlaggedCategories(),
        ];
    }
}
