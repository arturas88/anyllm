<?php

declare(strict_types=1);

namespace AnyLLM\StructuredOutput;

final class SchemaBuilder
{
    private array $properties = [];
    private array $required = [];
    private ?string $description = null;

    public function __construct(
        private readonly string $type,
    ) {}

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function property(
        string $name,
        string $type,
        ?string $description = null,
        bool $required = true,
        ?array $enum = null,
    ): self {
        $prop = ['type' => $type];

        if ($description !== null) {
            $prop['description'] = $description;
        }

        if ($enum !== null) {
            $prop['enum'] = $enum;
        }

        $this->properties[$name] = $prop;

        if ($required) {
            $this->required[] = $name;
        }

        return $this;
    }

    public function string(string $name, ?string $description = null, bool $required = true): self
    {
        return $this->property($name, 'string', $description, $required);
    }

    public function integer(string $name, ?string $description = null, bool $required = true): self
    {
        return $this->property($name, 'integer', $description, $required);
    }

    public function number(string $name, ?string $description = null, bool $required = true): self
    {
        return $this->property($name, 'number', $description, $required);
    }

    public function boolean(string $name, ?string $description = null, bool $required = true): self
    {
        return $this->property($name, 'boolean', $description, $required);
    }

    public function enum(string $name, array $values, ?string $description = null, bool $required = true): self
    {
        return $this->property($name, 'string', $description, $required, $values);
    }

    public function array(string $name, string|SchemaBuilder $items, ?string $description = null, bool $required = true): self
    {
        $itemSchema = is_string($items)
            ? ['type' => $items]
            : $items->build();

        $this->properties[$name] = [
            'type' => 'array',
            'items' => $itemSchema,
            ...($description ? ['description' => $description] : []),
        ];

        if ($required) {
            $this->required[] = $name;
        }

        return $this;
    }

    public function object(string $name, SchemaBuilder $schema, ?string $description = null, bool $required = true): self
    {
        $this->properties[$name] = [
            ...$schema->build(),
            ...($description ? ['description' => $description] : []),
        ];

        if ($required) {
            $this->required[] = $name;
        }

        return $this;
    }

    public function build(): array
    {
        return array_filter([
            'type' => $this->type,
            'description' => $this->description,
            'properties' => $this->properties ?: null,
            'required' => $this->required ?: null,
            'additionalProperties' => $this->type === 'object' ? false : null,
        ], fn ($v) => $v !== null);
    }

    public function toSchema(): Schema
    {
        return Schema::fromJsonSchema($this->build());
    }
}

