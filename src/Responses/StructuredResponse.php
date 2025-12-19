<?php

declare(strict_types=1);

namespace AnyLLM\Responses;

use AnyLLM\StructuredOutput\Schema;
use AnyLLM\Responses\Parts\Usage;

/**
 * @template T
 */
final class StructuredResponse extends Response
{
    /**
     * @param T|array<string, mixed> $object
     * @param Schema<T>|null $schema
     * @param array<string, mixed>|null $raw
     */
    public function __construct(
        public readonly mixed $object,
        public readonly ?Schema $schema = null,
        ?string $id = null,
        ?string $model = null,
        ?Usage $usage = null,
        ?array $raw = null,
    ) {
        parent::__construct($id, $model, $usage, $raw);
    }

    /**
     * @param array<string, mixed> $data
     * @param Schema<T>|class-string<T>|null $schema
     * @return self<T>
     */
    public static function fromArray(array $data, Schema|string|null $schema = null): static
    {
        $schemaInstance = match (true) {
            $schema instanceof Schema => $schema,
            is_string($schema) && class_exists($schema) => Schema::fromClass($schema), // @phpstan-ignore-line
            default => null,
        };

        $content = $data['content'] ?? $data['object'] ?? $data;

        if (is_string($content)) {
            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $content = $decoded;
            } else {
                // Try to extract JSON from markdown code blocks (```json ... ``` or ``` ... ```)
                $extracted = self::extractJsonFromMarkdown($content);
                if ($extracted !== null) {
                    $content = $extracted;
                } else {
                    // Fallback: try to extract JSON using regex (for non-markdown wrapped JSON)
                    if (preg_match('/\{.*\}/s', $content, $matches)) {
                        $decoded = json_decode($matches[0], true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $content = $decoded;
                        }
                    }
                }
            }
        }

        // Ensure content is an array for parsing
        if (! is_array($content)) {
            $content = [];
        }

        // If we have a schema but content is empty, this is likely an error
        if ($schemaInstance !== null && empty($content)) {
            throw new \RuntimeException(
                'Received empty response from provider. This may indicate a schema validation error or the model was unable to generate structured output.'
            );
        }

        $parsed = $schemaInstance?->parse($content) ?? $content;

        /** @var Schema<T>|null $schemaInstance */
        return new self(
            object: $parsed,
            schema: $schemaInstance,
            id: $data['id'] ?? null,
            model: $data['model'] ?? null,
            usage: isset($data['usage']) ? Usage::fromArray($data['usage']) : null,
            raw: $data,
        );
    }

    /**
     * Extract JSON from markdown code blocks.
     *
     * Handles formats like:
     * - ```json\n{...}\n```
     * - ```\n{...}\n```
     * - ```json\n{...}``` (without trailing newline)
     *
     * @param string $content The content that may contain markdown-wrapped JSON
     * @return array<string, mixed>|null The decoded JSON array, or null if extraction failed
     */
    private static function extractJsonFromMarkdown(string $content): ?array
    {
        // Try to match markdown code blocks with json language identifier
        // Pattern: ```json\n...\n``` or ```json\n...``` (with optional trailing newline)
        // Using non-greedy match to stop at the first closing ```
        if (preg_match('/```\s*json\s*\n(.*?)\n?```/s', $content, $matches)) {
            $jsonContent = trim($matches[1]);
            $decoded = json_decode($jsonContent, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        // Try to match generic markdown code blocks (without language identifier)
        // Extract content between ``` markers and check if it's valid JSON
        if (preg_match('/```\s*\n(.*?)\n?```/s', $content, $matches)) {
            $jsonContent = trim($matches[1]);
            // Only try to decode if it looks like JSON (starts with { or [)
            if (preg_match('/^\s*[\[\{]/', $jsonContent)) {
                $decoded = json_decode($jsonContent, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            }
        }

        // Try to match code blocks with any language identifier that might contain JSON
        // Pattern: ```lang\n{...}\n``` or ```lang\n{...}``` where the content looks like JSON
        if (preg_match('/```\w+\s*\n(.*?)\n?```/s', $content, $matches)) {
            $jsonContent = trim($matches[1]);
            // Only try to decode if it looks like JSON (starts with { or [)
            if (preg_match('/^\s*[\[\{]/', $jsonContent)) {
                $decoded = json_decode($jsonContent, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'object' => is_array($this->object) ? $this->object : (array) $this->object,
        ];
    }
}
