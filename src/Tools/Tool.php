<?php

declare(strict_types=1);

namespace AnyLLM\Tools;

use AnyLLM\StructuredOutput\Attributes\Description;
use Closure;
use ReflectionFunction;
use ReflectionNamedType;

final class Tool
{
    /**
     * @param array<string, array<string, mixed>> $parameters
     */
    private function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $parameters,
        public readonly ?Closure $handler = null,
    ) {}

    public static function fromCallable(
        string $name,
        callable $handler,
        string $description,
    ): self {
        $reflection = new ReflectionFunction(Closure::fromCallable($handler));
        $parameters = self::extractParameters($reflection);

        return new self(
            name: $name,
            description: $description,
            parameters: $parameters,
            handler: Closure::fromCallable($handler),
        );
    }

    /**
     * @param array<string, array<string, mixed>> $parameters
     */
    public static function create(
        string $name,
        string $description,
        array $parameters,
        ?callable $handler = null,
    ): self {
        return new self(
            name: $name,
            description: $description,
            parameters: $parameters,
            handler: $handler ? Closure::fromCallable($handler) : null,
        );
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function execute(array $arguments): mixed
    {
        if ($this->handler === null) {
            throw new \RuntimeException("Tool '{$this->name}' has no handler defined");
        }

        return ($this->handler)(...$arguments);
    }

    /**
     * @return array<string, mixed>
     */
    public function toProviderFormat(string $provider): array
    {
        return match ($provider) {
            'openai', 'openrouter', 'xai', 'mistral' => $this->toOpenAIFormat(),
            'anthropic' => $this->toAnthropicFormat(),
            default => $this->toOpenAIFormat(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function toOpenAIFormat(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => [
                    'type' => 'object',
                    'properties' => $this->parameters,
                    'required' => array_keys(
                        array_filter($this->parameters, fn($p) => ! ($p['optional'] ?? false))
                    ),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toAnthropicFormat(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'input_schema' => [
                'type' => 'object',
                'properties' => $this->parameters,
                'required' => array_keys(
                    array_filter($this->parameters, fn($p) => ! ($p['optional'] ?? false))
                ),
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function extractParameters(ReflectionFunction $reflection): array
    {
        $params = [];

        foreach ($reflection->getParameters() as $param) {
            $type = $param->getType();
            $paramSchema = [
                'type' => self::phpTypeToJsonType($type),
            ];

            // Check for Description attribute
            $descAttr = $param->getAttributes(Description::class)[0] ?? null;
            if ($descAttr) {
                $paramSchema['description'] = $descAttr->newInstance()->value;
            }

            if ($param->isOptional()) {
                $paramSchema['optional'] = true;
            }

            $params[$param->getName()] = $paramSchema;
        }

        return $params;
    }

    private static function phpTypeToJsonType(?\ReflectionType $type): string
    {
        if ($type === null) {
            return 'string';
        }

        if ($type instanceof ReflectionNamedType) {
            return match ($type->getName()) {
                'int', 'integer' => 'integer',
                'float', 'double' => 'number',
                'bool', 'boolean' => 'boolean',
                'array' => 'array',
                default => 'string',
            };
        }

        return 'string';
    }
}
