<?php

declare(strict_types=1);

namespace AnyLLM\StructuredOutput;

use AnyLLM\StructuredOutput\Attributes\ArrayOf;
use AnyLLM\StructuredOutput\Attributes\Description;
use AnyLLM\StructuredOutput\Attributes\Enum;
use ReflectionClass;
use ReflectionProperty;
use ReflectionNamedType;

/**
 * @template T
 */
final class Schema
{
    /**
     * @param class-string<T>|null $targetClass
     */
    private function __construct(
        public readonly array $jsonSchema,
        public readonly ?string $targetClass = null,
    ) {}

    /**
     * @template TClass
     * @param class-string<TClass> $class
     * @return self<TClass>
     */
    public static function fromClass(string $class): self
    {
        $reflection = new ReflectionClass($class);
        $properties = [];
        $required = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $propSchema = self::propertyToSchema($property);
            $properties[$property->getName()] = $propSchema;

            if (! $property->getType()?->allowsNull() && ! $property->hasDefaultValue()) {
                $required[] = $property->getName();
            }
        }

        return new self(
            jsonSchema: [
                'type' => 'object',
                'properties' => $properties,
                'required' => $required,
                'additionalProperties' => false,
            ],
            targetClass: $class,
        );
    }

    public static function fromJsonSchema(array $schema): self
    {
        return new self(jsonSchema: $schema);
    }

    public static function object(): SchemaBuilder
    {
        return new SchemaBuilder('object');
    }

    /**
     * @return T|array
     */
    public function parse(mixed $data): mixed
    {
        if ($this->targetClass === null) {
            return $data;
        }

        return self::hydrate($this->targetClass, $data);
    }

    public function toJsonSchema(): array
    {
        return $this->jsonSchema;
    }

    private static function propertyToSchema(ReflectionProperty $property): array
    {
        $type = $property->getType();
        $schema = [];

        // Check for Description attribute
        $descAttr = $property->getAttributes(Description::class)[0] ?? null;
        if ($descAttr) {
            $schema['description'] = $descAttr->newInstance()->value;
        }

        // Check for Enum attribute
        $enumAttr = $property->getAttributes(Enum::class)[0] ?? null;
        if ($enumAttr) {
            $schema['enum'] = $enumAttr->newInstance()->values;
        }

        // Check for ArrayOf attribute
        $arrayOfAttr = $property->getAttributes(ArrayOf::class)[0] ?? null;
        if ($arrayOfAttr) {
            $itemClass = $arrayOfAttr->newInstance()->class;
            $itemSchema = self::fromClass($itemClass);
            return [
                ...($schema['description'] ?? [] ? ['description' => $schema['description']] : []),
                'type' => 'array',
                'items' => $itemSchema->toJsonSchema(),
            ];
        }

        // Map PHP type to JSON Schema type
        $schema = array_merge($schema, self::typeToSchema($type));

        return $schema;
    }

    private static function typeToSchema(?\ReflectionType $type): array
    {
        if ($type === null) {
            return ['type' => 'string'];
        }

        if ($type instanceof ReflectionNamedType) {
            return match ($type->getName()) {
                'int', 'integer' => ['type' => 'integer'],
                'float', 'double' => ['type' => 'number'],
                'bool', 'boolean' => ['type' => 'boolean'],
                'string' => ['type' => 'string'],
                'array' => ['type' => 'array'],
                default => self::objectTypeToSchema($type->getName()),
            };
        }

        return ['type' => 'string'];
    }

    private static function objectTypeToSchema(string $className): array
    {
        if (class_exists($className)) {
            $schema = self::fromClass($className);
            return $schema->toJsonSchema();
        }

        return ['type' => 'string'];
    }

    /**
     * @template TClass
     * @param class-string<TClass> $class
     * @return TClass
     */
    private static function hydrate(string $class, array $data): object
    {
        $reflection = new ReflectionClass($class);
        $instance = $reflection->newInstanceWithoutConstructor();

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();

            if (array_key_exists($name, $data)) {
                $value = $data[$name];

                // Handle nested objects
                $type = $property->getType();
                if ($type instanceof ReflectionNamedType && ! $type->isBuiltin() && is_array($value)) {
                    $value = self::hydrate($type->getName(), $value);
                }

                // Handle arrays of objects
                $arrayOfAttr = $property->getAttributes(ArrayOf::class)[0] ?? null;
                if ($arrayOfAttr && is_array($value)) {
                    $itemClass = $arrayOfAttr->newInstance()->class;
                    $value = array_map(
                        fn ($item) => is_array($item) ? self::hydrate($itemClass, $item) : $item,
                        $value
                    );
                }

                $property->setValue($instance, $value);
            }
        }

        return $instance;
    }
}

