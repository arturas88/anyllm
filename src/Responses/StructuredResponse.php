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
                // If JSON decode failed, try to extract JSON from the string
                if (preg_match('/\{.*\}/s', $content, $matches)) {
                    $decoded = json_decode($matches[0], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $content = $decoded;
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
