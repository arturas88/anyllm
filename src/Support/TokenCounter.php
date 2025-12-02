<?php

declare(strict_types=1);

namespace AnyLLM\Support;

use AnyLLM\Messages\Message;

final class TokenCounter
{
    /**
     * Approximate token counts per model family
     */
    private const CHARS_PER_TOKEN = [
        'gpt-4' => 4,
        'gpt-3.5' => 4,
        'claude' => 4,
        'gemini' => 4,
        'default' => 4,
    ];

    /**
     * Estimate token count for text using character-based approximation.
     * This is a rough estimate. For accurate counts, use the provider's API.
     */
    public static function estimate(string $text, string $model = 'default'): int
    {
        $charsPerToken = self::getCharsPerToken($model);

        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Count characters
        $chars = mb_strlen($text);

        // Estimate tokens
        return (int) ceil($chars / $charsPerToken);
    }

    /**
     * Estimate token count for multiple messages.
     *
     * @param Message[] $messages
     */
    public static function estimateMessages(array $messages, string $model = 'default'): int
    {
        $total = 0;

        foreach ($messages as $message) {
            // Add overhead for message structure (role, etc.)
            $total += 4;

            // Count content
            $content = $message->getContent();
            if (is_string($content)) {
                $total += self::estimate($content, $model);
            } elseif (is_array($content)) {
                foreach ($content as $part) {
                    if (is_string($part)) {
                        $total += self::estimate($part, $model);
                    } elseif (is_object($part) && method_exists($part, 'toArray')) {
                        $partArray = $part->toArray();
                        if (isset($partArray['text'])) {
                            $total += self::estimate($partArray['text'], $model);
                        }
                        if (isset($partArray['type']) && $partArray['type'] === 'image') {
                            $total += 85; // Approximate tokens for image
                        }
                    }
                }
            }
        }

        return $total;
    }

    /**
     * Check if messages would exceed token limit.
     *
     * @param Message[] $messages
     */
    public static function wouldExceedLimit(
        array $messages,
        int $maxTokens,
        string $model = 'default',
    ): bool {
        $estimated = self::estimateMessages($messages, $model);
        return $estimated > $maxTokens;
    }

    /**
     * Truncate text to fit within token limit.
     */
    public static function truncate(
        string $text,
        int $maxTokens,
        string $model = 'default',
        string $suffix = '...',
    ): string {
        $estimated = self::estimate($text, $model);

        if ($estimated <= $maxTokens) {
            return $text;
        }

        $charsPerToken = self::getCharsPerToken($model);
        $targetChars = ($maxTokens - self::estimate($suffix, $model)) * $charsPerToken;

        return mb_substr($text, 0, $targetChars) . $suffix;
    }

    /**
     * Get the characters per token ratio for a model.
     */
    private static function getCharsPerToken(string $model): int
    {
        foreach (self::CHARS_PER_TOKEN as $family => $ratio) {
            if (str_contains(strtolower($model), $family)) {
                return $ratio;
            }
        }

        return self::CHARS_PER_TOKEN['default'];
    }

    /**
     * Calculate the percentage of tokens used.
     */
    public static function percentage(int $used, int $limit): float
    {
        if ($limit === 0) {
            return 0.0;
        }

        return round(($used / $limit) * 100, 2);
    }

    /**
     * Format token count for display.
     */
    public static function format(int $tokens, ?int $limit = null): string
    {
        $formatted = number_format($tokens);

        if ($limit !== null) {
            $percentage = self::percentage($tokens, $limit);
            return "{$formatted} / " . number_format($limit) . " ({$percentage}%)";
        }

        return $formatted;
    }
}
