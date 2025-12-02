<?php

declare(strict_types=1);

namespace AnyLLM\Responses\Parts;

final readonly class ToolCall
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $arguments,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'] ?? $data['function']['name'],
            arguments: is_string($data['arguments'] ?? $data['function']['arguments'] ?? null)
                ? (json_decode($data['arguments'] ?? $data['function']['arguments'] ?? '{}', true) ?: [])
                : (is_array($data['arguments'] ?? $data['function']['arguments'] ?? null) ? ($data['arguments'] ?? $data['function']['arguments']) : []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'arguments' => $this->arguments,
        ];
    }
}
