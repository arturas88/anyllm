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
     * @param T|array $object
     * @param Schema<T>|null $schema
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
     * @param Schema<T>|class-string<T>|null $schema
     * @return self<T>
     */
    public static function fromArray(array $data, Schema|string|null $schema = null): static
    {
        $schemaInstance = match (true) {
            $schema instanceof Schema => $schema,
            is_string($schema) => Schema::fromClass($schema),
            default => null,
        };

        $content = $data['content'] ?? $data['object'] ?? $data;
        
        if (is_string($content)) {
            $content = json_decode($content, true);
        }

        $parsed = $schemaInstance?->parse($content) ?? $content;

        return new self(
            object: $parsed,
            schema: $schemaInstance,
            id: $data['id'] ?? null,
            model: $data['model'] ?? null,
            usage: isset($data['usage']) ? Usage::fromArray($data['usage']) : null,
            raw: $data,
        );
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'object' => is_array($this->object) ? $this->object : (array) $this->object,
        ];
    }
}

