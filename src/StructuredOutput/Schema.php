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
     * @param array<string, mixed> $jsonSchema
     * @param class-string<T>|null $targetClass
     */
    private function __construct(
        public readonly array $jsonSchema,
        public readonly ?string $targetClass = null,
    ) {}

    /**
     * @template TClass of object
     * @param class-string<TClass> $class
     * @return self<TClass>
     */
    public static function fromClass(string $class): self
    {
        /** @var class-string<TClass> $class */
        $reflection = new ReflectionClass($class);
        $properties = [];
        $required = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $propSchema = self::propertyToSchema($property);
            $properties[$property->getName()] = $propSchema;

            $type = $property->getType();
            $isNullable = $type?->allowsNull() ?? false;
            $hasDefault = $property->hasDefaultValue();
            $defaultValue = $hasDefault ? $property->getDefaultValue() : null;

            // OpenAI's strict mode requires all properties to be in 'required' array
            // Nullable properties must be included (they can be null, but must be present)
            // Only exclude properties with non-null default values (truly optional)
            if ($isNullable || ! $hasDefault || $defaultValue === null) {
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

    /**
     * @param array<string, mixed> $schema
     * @return self<mixed>
     */
    public static function fromJsonSchema(array $schema): self
    {
        return new self(jsonSchema: $schema);
    }

    public static function object(): SchemaBuilder
    {
        return new SchemaBuilder('object');
    }

    /**
     * @param array<string, mixed>|mixed $data
     * @return T|array<string, mixed>
     */
    public function parse(mixed $data): mixed
    {
        if ($this->targetClass === null) {
            return is_array($data) ? $data : [];
        }

        if (!is_array($data)) {
            return $data;
        }

        // $this->targetClass is ?string (property type), already checked above
        $targetClass = $this->targetClass;
        // $targetClass is string when not null (from property type ?string)
        if (!class_exists($targetClass)) {
            return $data;
        }

        /** @var class-string<T> $targetClass */
        /** @var T */
        $result = self::hydrate($targetClass, $data); // @phpstan-ignore-line
        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJsonSchema(): array
    {
        return $this->jsonSchema;
    }

    /**
     * @return array<string, mixed>
     */
    private static function propertyToSchema(ReflectionProperty $property): array
    {
        $type = $property->getType();
        $allowsNull = $type?->allowsNull() ?? false;
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

            // Handle primitive types (string, int, float, bool)
            $primitiveTypes = ['string', 'int', 'integer', 'float', 'double', 'bool', 'boolean'];
            if (in_array(strtolower($itemClass), $primitiveTypes)) {
                $itemType = match (strtolower($itemClass)) {
                    'int', 'integer' => 'integer',
                    'float', 'double' => 'number',
                    'bool', 'boolean' => 'boolean',
                    default => 'string',
                };

                return [
                    ...($schema['description'] ?? [] ? ['description' => $schema['description']] : []),
                    'type' => 'array',
                    'items' => ['type' => $itemType],
                ];
            }

            // Handle class types
            $itemSchema = self::fromClass($itemClass);
            return [
                ...($schema['description'] ?? [] ? ['description' => $schema['description']] : []),
                'type' => 'array',
                'items' => $itemSchema->toJsonSchema(),
            ];
        }

        // Map PHP type to JSON Schema type
        $typeSchema = self::typeToSchema($type, $allowsNull);

        // OpenAI requires array types to have 'items' definition
        // If it's an array type without ArrayOf attribute, default to string array
        if (isset($typeSchema['type']) && $typeSchema['type'] === 'array') {
            // Try to parse PHPDoc for array type hint (e.g., @var array<string>)
            $docComment = $property->getDocComment();
            $itemType = 'string'; // default to string

            if ($docComment && preg_match('/@var\s+array<(\w+)>/', $docComment, $matches)) {
                $itemType = match ($matches[1]) {
                    'int', 'integer' => 'integer',
                    'float', 'double', 'number' => 'number',
                    'bool', 'boolean' => 'boolean',
                    'string' => 'string',
                    default => 'string',
                };
            }

            $typeSchema = [
                'type' => 'array',
                'items' => ['type' => $itemType],
            ];
        }

        $schema = array_merge($schema, $typeSchema);

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private static function typeToSchema(?\ReflectionType $type, bool $allowsNull = false): array
    {
        if ($type === null) {
            return $allowsNull ? ['type' => ['string', 'null']] : ['type' => 'string'];
        }

        if ($type instanceof ReflectionNamedType) {
            $baseType = match ($type->getName()) {
                'int', 'integer' => 'integer',
                'float', 'double' => 'number',
                'bool', 'boolean' => 'boolean',
                'string' => 'string',
                'array' => 'array',
                default => null,
            };

            if ($baseType !== null) {
                $typeSchema = ['type' => $baseType];
            } else {
                $typeSchema = self::objectTypeToSchema($type->getName());
            }

            // Handle nullable types - OpenAI requires explicit null in type array
            if ($allowsNull || $type->allowsNull()) {
                if (isset($typeSchema['type']) && is_string($typeSchema['type'])) {
                    $typeSchema['type'] = [$typeSchema['type'], 'null'];
                }
            }

            return $typeSchema;
        }

        return $allowsNull ? ['type' => ['string', 'null']] : ['type' => 'string'];
    }

    /**
     * @return array<string, mixed>
     */
    private static function objectTypeToSchema(string $className): array
    {
        if (class_exists($className)) {
            $schema = self::fromClass($className);
            return $schema->toJsonSchema();
        }

        return ['type' => 'string'];
    }

    /**
     * @template TClass of object
     * @param class-string<TClass> $class
     * @param array<string, mixed> $data
     * @return TClass
     */
    private static function hydrate(string $class, array $data): object
    {
        /** @var class-string<TClass> $class */
        $reflection = new ReflectionClass($class);
        $instance = $reflection->newInstanceWithoutConstructor();

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            $value = null;
            $found = false;

            // Try exact match first
            if (array_key_exists($name, $data)) {
                $value = $data[$name];
                $found = true;
            } else {
                // Try snake_case version (camelCase -> snake_case)
                $snakeName = self::camelToSnake($name);
                if (array_key_exists($snakeName, $data)) {
                    $value = $data[$snakeName];
                    $found = true;
                }
            }

            if ($found && $value !== null) {
                // Handle nested objects
                $type = $property->getType();
                if ($type instanceof ReflectionNamedType && ! $type->isBuiltin() && is_array($value)) {
                    /** @var class-string $className */
                    $className = $type->getName();
                    $value = self::hydrate($className, $value);
                }

                // Handle arrays of objects
                $arrayOfAttr = $property->getAttributes(ArrayOf::class)[0] ?? null;
                if ($arrayOfAttr && is_array($value)) {
                    $itemClass = $arrayOfAttr->newInstance()->class;
                    $value = array_map(
                        fn($item) => is_array($item) ? self::hydrate($itemClass, $item) : $item,
                        $value
                    );
                }

                $property->setValue($instance, $value);
            } elseif ($property->hasDefaultValue()) {
                // Set default value if property has one and wasn't found in data
                $property->setValue($instance, $property->getDefaultValue());
            }
        }

        return $instance;
    }

    private static function camelToSnake(string $str): string
    {
        $replaced = preg_replace('/([a-z])([A-Z])/', '$1_$2', $str);
        return strtolower($replaced ?? $str);
    }
}
