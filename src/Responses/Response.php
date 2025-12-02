<?php

declare(strict_types=1);

namespace AnyLLM\Responses;

use AnyLLM\Responses\Parts\Usage;

abstract class Response
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $model = null,
        public readonly ?Usage $usage = null,
        /** @var array<string, mixed>|null */
        public readonly ?array $raw = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    abstract public static function fromArray(array $data): static;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'model' => $this->model,
            'usage' => $this->usage?->toArray(),
        ], fn($v) => $v !== null);
    }
}
