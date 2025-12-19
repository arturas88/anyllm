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

        // First, normalize keys in nested objects (strip prefixes like "document.primary_reference" -> "primary_reference")
        $transformed = self::normalizeNestedObjectKeys($transformed);

        // Then, try to flatten nested structures generically
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
     * @param ReflectionClass<object> $reflection
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
                        // Only process string keys (skip numeric array indices)
                        if (!is_string($nestedKey)) {
                            continue;
                        }

                        if (is_array($nestedValue) && !empty($nestedValue)) {
                            // Extract prefix from nested key (e.g., "early_payment" -> "early")
                            $prefix = self::extractPrefix($nestedKey);

                            // For each field in the nested structure, check if we expect a flattened version
                            foreach ($nestedValue as $nestedField => $nestedFieldValue) {
                                // Only process string field names (skip numeric array indices)
                                if (!is_string($nestedField)) {
                                    continue;
                                }

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
     * Normalize keys in nested objects by stripping prefixes.
     * Handles cases like {"document": {"document.primary_reference": "value"}}
     * -> {"document": {"primary_reference": "value"}}
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function normalizeNestedObjectKeys(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            if (is_array($value) && !self::isSimpleArray($value)) {
                // Check if this is a nested object that might have prefixed keys
                $normalizedValue = [];
                $prefix = $key . '.';

                foreach ($value as $nestedKey => $nestedValue) {
                    // Only process string keys (skip numeric array indices)
                    if (!is_string($nestedKey)) {
                        // Keep numeric keys as-is, but recursively normalize if it's an array
                        if (is_array($nestedValue) && !self::isSimpleArray($nestedValue)) {
                            $normalizedValue[$nestedKey] = self::normalizeNestedObjectKeys($nestedValue);
                        } else {
                            $normalizedValue[$nestedKey] = $nestedValue;
                        }
                        continue;
                    }

                    // If the nested key starts with the parent key prefix, strip it
                    if (str_starts_with($nestedKey, $prefix)) {
                        $normalizedKey = substr($nestedKey, strlen($prefix));
                        // Recursively normalize nested arrays
                        if (is_array($nestedValue) && !self::isSimpleArray($nestedValue)) {
                            $normalizedValue[$normalizedKey] = self::normalizeNestedObjectKeys($nestedValue);
                        } else {
                            $normalizedValue[$normalizedKey] = $nestedValue;
                        }
                    } else {
                        // Keep the key as-is, but recursively normalize if it's an array
                        if (is_array($nestedValue) && !self::isSimpleArray($nestedValue)) {
                            $normalizedValue[$nestedKey] = self::normalizeNestedObjectKeys($nestedValue);
                        } else {
                            $normalizedValue[$nestedKey] = $nestedValue;
                        }
                    }
                }

                $normalized[$key] = $normalizedValue;
            } else {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
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

            // Try word order swaps for two-word snake_case fields (e.g., bic_swift -> swift_bic)
            if (count($parts) === 2) {
                $swapped = $parts[1] . '_' . $parts[0];
                $alternatives[] = $swapped;
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
     * Try to convert a non-array value to a class instance
     * This handles cases where the LLM returns a simple value (string/number)
     * instead of an object for a class-typed property.
     *
     * Uses reflection to automatically discover required properties and
     * attempts to construct the object from available data in the parent context.
     *
     * @param class-string $className
     * @param mixed $value The value that was provided (might match one property)
     * @param array<string, mixed> $parentData Context data from parent object
     * @param string $propertyName Name of the property being set (for context)
     * @param bool $allowsNull Whether the property allows null values
     * @return object|null The converted object or null if conversion not possible
     */
    private static function tryConvertToClass(string $className, mixed $value, array $parentData, string $propertyName, bool $allowsNull): ?object
    {
        if (!class_exists($className)) {
            return null;
        }

        try {
            $reflection = new \ReflectionClass($className);
            $instance = $reflection->newInstanceWithoutConstructor();

            // Get all public properties of the target class
            $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);

            if (empty($properties)) {
                return null;
            }

            // Try to match the provided value to one of the properties
            // We want to find the "best" match, not just the first match
            $matchedProperty = null;
            $matchedValue = $value;
            $bestScore = -1;

            foreach ($properties as $prop) {
                $propType = $prop->getType();
                $propName = $prop->getName();
                $score = 0;

                // Check if the value type matches this property's type
                if (!self::valueMatchesType($value, $propType)) {
                    continue;
                }

                // Score the match based on various heuristics

                // 1. Name similarity: if context property name matches target property name
                if (strtolower($propertyName) === strtolower($propName)) {
                    $score += 100; // Strong preference
                }

                // 2. Value characteristics: numeric strings prefer "amount", "price", "value", etc.
                if (is_numeric($value) || (is_string($value) && is_numeric($value))) {
                    $numericPropertyNames = ['amount', 'price', 'value', 'cost', 'fee', 'total', 'sum'];
                    if (in_array(strtolower($propName), $numericPropertyNames)) {
                        $score += 50;
                    }
                }

                // 3. Currency codes: 3-letter uppercase strings prefer "currency", "currency_code", etc.
                if (is_string($value) && strlen($value) === 3 && ctype_upper($value)) {
                    $currencyPropertyNames = ['currency', 'currency_code', 'currencyCode', 'code'];
                    if (in_array(strtolower($propName), $currencyPropertyNames)) {
                        $score += 50;
                    }
                }

                // 4. Default: any type match gets a base score
                if ($score === 0) {
                    $score = 10;
                }

                // Keep the best match
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $matchedProperty = $prop;
                    $matchedValue = $value;
                }
            }

            // Collect data for all properties
            $propertyData = [];

            foreach ($properties as $prop) {
                $propName = $prop->getName();
                $propValue = null;

                // If this is the matched property, use the provided value
                if ($matchedProperty === $prop) {
                    $propValue = $matchedValue;
                } else {
                    // Try to find the property value in parent data using various strategies
                    // Exclude the matched value to avoid assigning it to multiple properties
                    $propValue = self::findPropertyValue($prop, $parentData, $propertyName, $matchedValue);
                }

                if ($propValue !== null) {
                    $propertyData[$propName] = $propValue;
                }
            }

            // Check if we have enough data to construct the object
            // At minimum, we need the matched property value, or all non-nullable properties
            $hasRequiredData = false;

            if ($matchedProperty !== null) {
                // We have at least one matching property
                $hasRequiredData = true;
            } else {
                // Check if we have all non-nullable properties
                $allRequiredFound = true;
                foreach ($properties as $prop) {
                    $propType = $prop->getType();
                    if ($propType instanceof \ReflectionNamedType && !$propType->allowsNull() && !$propType->isBuiltin()) {
                        if (!isset($propertyData[$prop->getName()])) {
                            $allRequiredFound = false;
                            break;
                        }
                    }
                }
                $hasRequiredData = $allRequiredFound && !empty($propertyData);
            }

            if (!$hasRequiredData || empty($propertyData)) {
                return null;
            }

            // Set all found properties on the instance
            foreach ($propertyData as $name => $val) {
                $prop = $reflection->getProperty($name);
                $prop->setAccessible(true);

                // Handle nested objects recursively
                $propType = $prop->getType();
                if ($propType instanceof \ReflectionNamedType && !$propType->isBuiltin() && is_array($val)) {
                    $val = self::hydrate($propType->getName(), $val); // @phpstan-ignore-line
                }

                $prop->setValue($instance, $val);
            }

            return $instance;
        } catch (\ReflectionException $e) {
            return null;
        }
    }

    /**
     * Check if a value matches a property type
     */
    private static function valueMatchesType(mixed $value, ?\ReflectionType $type): bool
    {
        if ($type === null) {
            return false;
        }

        if ($type instanceof \ReflectionNamedType) {
            $typeName = $type->getName();

            // Built-in type matching
            return match ($typeName) {
                'string' => is_string($value),
                'int', 'integer' => is_int($value) || (is_string($value) && ctype_digit($value)),
                'float', 'double' => is_float($value) || is_numeric($value),
                'bool', 'boolean' => is_bool($value),
                default => false, // For class types, we'll handle separately
            };
        }

        return false;
    }

    /**
     * Try to find a property value in the parent data using various search strategies
     *
     * @param \ReflectionProperty $property The property to find data for
     * @param array<string, mixed> $parentData The parent data context
     * @param string $contextPropertyName The name of the property that triggered this conversion
     * @param mixed $excludeValue Optional value to exclude (e.g., the value already matched to another property)
     * @return mixed The found value or null
     */
    private static function findPropertyValue(\ReflectionProperty $property, array $parentData, string $contextPropertyName, mixed $excludeValue = null): mixed
    {
        $propName = $property->getName();
        $propType = $property->getType();

        // Strategy 1: Exact match in parent data
        if (isset($parentData[$propName])) {
            $value = $parentData[$propName];
            // Don't return the excluded value (the one already matched to another property)
            if ($value !== $excludeValue && self::valueMatchesType($value, $propType)) {
                return $value;
            }
        }

        // Strategy 2: Snake case version
        $snakeName = self::camelToSnake($propName);
        if ($snakeName !== $propName && isset($parentData[$snakeName])) {
            $value = $parentData[$snakeName];
            if ($value !== $excludeValue && self::valueMatchesType($value, $propType)) {
                return $value;
            }
        }

        // Strategy 3: Prefixed with context property name (e.g., "amount_currency" when converting "amount")
        $prefixedName = $contextPropertyName . '_' . $propName;
        if (isset($parentData[$prefixedName])) {
            $value = $parentData[$prefixedName];
            if ($value !== $excludeValue && self::valueMatchesType($value, $propType)) {
                return $value;
            }
        }

        // Strategy 4: Snake case prefixed version
        $snakePrefixedName = self::camelToSnake($contextPropertyName) . '_' . $snakeName;
        if ($snakePrefixedName !== $prefixedName && isset($parentData[$snakePrefixedName])) {
            $value = $parentData[$snakePrefixedName];
            if ($value !== $excludeValue && self::valueMatchesType($value, $propType)) {
                return $value;
            }
        }

        // Strategy 5: Search in nested structures
        $found = self::findInNestedStructures($parentData, $propName);
        if ($found !== null && $found !== $excludeValue && self::valueMatchesType($found, $propType)) {
            return $found;
        }

        // Strategy 6: Try snake case in nested structures
        if ($snakeName !== $propName) {
            $found = self::findInNestedStructures($parentData, $snakeName);
            if ($found !== null && $found !== $excludeValue && self::valueMatchesType($found, $propType)) {
                return $found;
            }
        }

        return null;
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
                } else {
                    // Try word order swap for two-word snake_case fields (e.g., bic_swift -> swift_bic)
                    $parts = explode('_', $snakeName);
                    if (count($parts) === 2) {
                        $swapped = $parts[1] . '_' . $parts[0];
                        if (array_key_exists($swapped, $data)) {
                            $value = $data[$swapped];
                            $found = true;
                        }
                    }
                }
            }

            if ($found && $value !== null) {
                // Handle nested objects
                $type = $property->getType();
                if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                    /** @var class-string $className */
                    $className = $type->getName();

                    if (is_array($value)) {
                        // Standard case: value is an array, hydrate it
                        $value = self::hydrate($className, $value);
                    } else {
                        // Value is not an array but property expects a class
                        // Try to convert it (e.g., string/number to Money object)
                        $converted = self::tryConvertToClass($className, $value, $data, $property->getName(), $type->allowsNull());
                        if ($converted !== null) {
                            $value = $converted;
                        } elseif (!$type->allowsNull()) {
                            // Can't convert and property is not nullable - skip setting it
                            // This will preserve the original behavior for non-nullable properties
                            continue;
                        } else {
                            // Can't convert but property is nullable - set to null
                            $value = null;
                        }
                    }
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
