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
     * Generate a human-readable field list from the schema for prompt enhancement
     * @return string
     */
    public function toFieldList(): string
    {
        if ($this->targetClass === null) {
            return '';
        }

        return self::generateFieldList($this->targetClass);
    }

    /**
     * Generate a human-readable field list from a PHP class
     * @template TClass of object
     * @param class-string<TClass> $class
     * @param string $prefix
     * @param int $depth
     * @return string
     */
    private static function generateFieldList(string $class, string $prefix = '', int $depth = 0): string
    {
        if ($depth > 5) { // Prevent infinite recursion
            return '';
        }

        if (!class_exists($class)) {
            return '';
        }

        $reflection = new ReflectionClass($class);
        $lines = [];
        $indent = str_repeat('  ', $depth);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            $fullName = $prefix ? "{$prefix}.{$name}" : $name;

            // Get description from attribute
            $descAttr = $property->getAttributes(Description::class)[0] ?? null;
            $description = $descAttr ? $descAttr->newInstance()->value : '';

            // Get type information
            $type = $property->getType();
            $typeName = 'mixed';
            $isArray = false;

            if ($type instanceof ReflectionNamedType) {
                $typeName = $type->getName();
                $isArray = $typeName === 'array';
            }

            // Check for ArrayOf attribute
            $arrayOfAttr = $property->getAttributes(ArrayOf::class)[0] ?? null;
            if ($arrayOfAttr) {
                $itemClass = $arrayOfAttr->newInstance()->class;
                $isArray = true;
                $typeName = $itemClass;
            }

            // Format type for display
            $typeDisplay = $isArray ? "array of {$typeName}" : $typeName;
            if ($type?->allowsNull()) {
                $typeDisplay .= ' (nullable)';
            }

            // Build field description
            $fieldDesc = "- {$fullName} ({$typeDisplay}";
            if ($description) {
                $fieldDesc .= ", {$description}";
            }
            $fieldDesc .= ')';

            $lines[] = $indent . $fieldDesc;

            // If it's a nested object, recurse
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin() && class_exists($type->getName())) {
                $nested = self::generateFieldList($type->getName(), $fullName, $depth + 1);
                if ($nested) {
                    $lines[] = $nested;
                }
            }
        }

        return implode("\n", $lines);
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
     * Transform data to handle common field name mismatches before hydration
     * @param array<string, mixed> $data
     * @param string $className
     * @return array<string, mixed>
     */
    private static function transformData(array $data, string $className): array
    {
        if (!class_exists($className)) {
            return $data;
        }

        $reflection = new ReflectionClass($className);
        $transformed = $data;

        // First, try to flatten nested structures generically
        $transformed = self::flattenNestedStructures($transformed, $reflection);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $expectedName = $property->getName();
            
            // If already present, skip
            if (array_key_exists($expectedName, $transformed)) {
                // Still need to recursively transform nested objects
                if (is_array($transformed[$expectedName])) {
                    $type = $property->getType();
                    if ($type instanceof ReflectionNamedType && !$type->isBuiltin() && class_exists($type->getName())) {
                        $transformed[$expectedName] = self::transformData($transformed[$expectedName], $type->getName());
                    }
                }
                continue;
            }

            // Generate alternative field names using generic patterns
            $alternatives = self::getFieldNameAlternatives($expectedName);
            
            foreach ($alternatives as $alt) {
                // Handle nested paths (e.g., "early_payment.amount")
                if (strpos($alt, '.') !== false) {
                    $parts = explode('.', $alt);
                    $value = $transformed;
                    foreach ($parts as $part) {
                        if (is_array($value) && array_key_exists($part, $value)) {
                            $value = $value[$part];
                        } else {
                            $value = null;
                            break;
                        }
                    }
                    if ($value !== null) {
                        $transformed[$expectedName] = $value;
                        break;
                    }
                } elseif (array_key_exists($alt, $transformed)) {
                    $transformed[$expectedName] = $transformed[$alt];
                    break;
                }
            }

            // Search for nested structures that might contain this field
            if (!array_key_exists($expectedName, $transformed)) {
                $found = self::findInNestedStructures($transformed, $expectedName);
                if ($found !== null) {
                    $transformed[$expectedName] = $found;
                }
            }

            // Handle nested objects - recursively transform
            if (isset($transformed[$expectedName]) && is_array($transformed[$expectedName])) {
                $type = $property->getType();
                if ($type instanceof ReflectionNamedType && !$type->isBuiltin() && class_exists($type->getName())) {
                    $transformed[$expectedName] = self::transformData($transformed[$expectedName], $type->getName());
                }
            }
        }

        return $transformed;
    }

    /**
     * Flatten nested structures generically by analyzing the schema
     * Looks for patterns like {prefix_payment: {amount: X}} -> {prefix_amount: X}
     * @param array<string, mixed> $data
     * @param ReflectionClass $reflection
     * @return array<string, mixed>
     */
    private static function flattenNestedStructures(array $data, ReflectionClass $reflection): array
    {
        $transformed = $data;
        $expectedFields = [];

        // Get all expected field names from the class
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $expectedFields[] = $property->getName();
        }

        // Look for nested objects in the data
        foreach ($data as $key => $value) {
            if (is_array($value) && !empty($value)) {
                // Check if this looks like a nested structure (object with sub-objects)
                $hasNestedObjects = false;
                foreach ($value as $subKey => $subValue) {
                    if (is_array($subValue) && !empty($subValue) && !self::isSimpleArray($subValue)) {
                        $hasNestedObjects = true;
                        break;
                    }
                }

                if ($hasNestedObjects) {
                    // Try to flatten: look for patterns like prefix_suffix.field -> prefix_field
                    foreach ($value as $nestedKey => $nestedValue) {
                        if (is_array($nestedValue) && !empty($nestedValue)) {
                            // Extract prefix from nested key (e.g., "early_payment" -> "early")
                            $prefix = self::extractPrefix($nestedKey);
                            
                            // For each field in the nested structure, check if we expect a flattened version
                            foreach ($nestedValue as $nestedField => $nestedFieldValue) {
                                if (!is_array($nestedFieldValue)) {
                                    // Try to construct expected field name: prefix + field
                                    $candidateField = $prefix . '_' . $nestedField;
                                    
                                    // Check if this candidate matches an expected field
                                    if (in_array($candidateField, $expectedFields, true)) {
                                        // Only flatten if the field doesn't already exist
                                        if (!isset($transformed[$key][$candidateField])) {
                                            $transformed[$key][$candidateField] = $nestedFieldValue;
                                        }
                                    }
                                    
                                    // Also try direct match (e.g., if nested field name matches expected field)
                                    if (in_array($nestedField, $expectedFields, true)) {
                                        if (!isset($transformed[$key][$nestedField])) {
                                            $transformed[$key][$nestedField] = $nestedFieldValue;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $transformed;
    }

    /**
     * Extract prefix from a key (e.g., "early_payment" -> "early", "bank_transfer" -> "bank")
     * @param string $key
     * @return string
     */
    private static function extractPrefix(string $key): string
    {
        // Try to extract prefix before common suffixes
        $suffixes = ['_payment', '_transfer', '_card', '_device', '_info', '_data'];
        foreach ($suffixes as $suffix) {
            if (str_ends_with($key, $suffix)) {
                return substr($key, 0, -strlen($suffix));
            }
        }
        
        // If no suffix found, try to split on underscore and take first part
        $parts = explode('_', $key);
        return $parts[0] ?? $key;
    }

    /**
     * Check if array is a simple list (not an object-like structure)
     * @param array<mixed> $arr
     * @return bool
     */
    private static function isSimpleArray(array $arr): bool
    {
        return array_keys($arr) === range(0, count($arr) - 1);
    }

    /**
     * Search for a field in nested structures generically
     * @param array<string, mixed> $data
     * @param string $fieldName
     * @return mixed
     */
    private static function findInNestedStructures(array $data, string $fieldName): mixed
    {
        foreach ($data as $value) {
            if (is_array($value)) {
                // Check direct match
                if (array_key_exists($fieldName, $value)) {
                    return $value[$fieldName];
                }
                
                // Recursively search nested structures
                $found = self::findInNestedStructures($value, $fieldName);
                if ($found !== null) {
                    return $found;
                }
            }
        }
        
        return null;
    }

    /**
     * Get alternative field names using generic patterns (no hardcoded mappings)
     * @param string $fieldName
     * @return array<string>
     */
    private static function getFieldNameAlternatives(string $fieldName): array
    {
        $alternatives = [];
        
        // Add snake_case version
        $snakeName = self::camelToSnake($fieldName);
        if ($snakeName !== $fieldName) {
            $alternatives[] = $snakeName;
        }

        // Add camelCase version
        $camelName = self::snakeToCamel($fieldName);
        if ($camelName !== $fieldName) {
            $alternatives[] = $camelName;
        }

        // Try removing common prefixes
        $prefixes = ['document_', 'primary_', 'secondary_', 'registration_', 'vehicle_', 'appeal_', 'early_', 'standard_', 'late_', 'online_', 'device_', 'measurement_'];
        foreach ($prefixes as $prefix) {
            if (str_starts_with($fieldName, $prefix)) {
                $withoutPrefix = substr($fieldName, strlen($prefix));
                if ($withoutPrefix) {
                    $alternatives[] = $withoutPrefix;
                }
            }
        }

        // Try removing common suffixes
        $suffixes = ['_kmh', '_code', '_id', '_type', '_name', '_date', '_time', '_amount', '_deadline'];
        foreach ($suffixes as $suffix) {
            if (str_ends_with($fieldName, $suffix)) {
                $withoutSuffix = substr($fieldName, 0, -strlen($suffix));
                if ($withoutSuffix) {
                    $alternatives[] = $withoutSuffix;
                }
            }
        }

        // Try singular/plural conversions
        if (str_ends_with($fieldName, 's') && strlen($fieldName) > 1) {
            $singular = substr($fieldName, 0, -1);
            $alternatives[] = $singular;
        } elseif (!str_ends_with($fieldName, 's')) {
            $plural = $fieldName . 's';
            $alternatives[] = $plural;
        }

        // Try nested path patterns (e.g., early_amount -> early_payment.amount)
        // This is a heuristic: if field has a prefix, try prefix_payment.field or prefix_transfer.field
        $parts = explode('_', $fieldName);
        if (count($parts) >= 2) {
            $prefix = $parts[0];
            $suffix = implode('_', array_slice($parts, 1));
            
            // Common nested patterns
            $nestedPrefixes = ['payment', 'transfer', 'card', 'device', 'info'];
            foreach ($nestedPrefixes as $nestedPrefix) {
                $alternatives[] = "{$prefix}_{$nestedPrefix}.{$suffix}";
            }
        }

        return array_unique($alternatives);
    }

    /**
     * Convert snake_case to camelCase
     */
    private static function snakeToCamel(string $str): string
    {
        return lcfirst(str_replace('_', '', ucwords($str, '_')));
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
        // Transform data to handle common mismatches
        $data = self::transformData($data, $class);
        
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
