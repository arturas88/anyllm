<?php

declare(strict_types=1);

namespace AnyLLM\Responses\Parts;

final readonly class ToolCall
{
    public function __construct(
        public string $id,
        public string $name,
        public array $arguments,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'] ?? $data['function']['name'],
            arguments: is_string($data['arguments'] ?? $data['function']['arguments'] ?? [])
                ? json_decode($data['arguments'] ?? $data['function']['arguments'], true)
                : $data['arguments'] ?? $data['function']['arguments'],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'arguments' => $this->arguments,
        ];
    }
}
