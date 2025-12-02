<?php

declare(strict_types=1);

namespace AnyLLM\Responses;

use AnyLLM\Responses\Parts\Usage;

final class AudioResponse extends Response
{
    public function __construct(
        public readonly string $data,
        public readonly string $format,
        public readonly bool $isBinary = true,
        ?string $id = null,
        ?string $model = null,
        ?Usage $usage = null,
        ?array $raw = null,
    ) {
        parent::__construct($id, $model, $usage, $raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            data: $data['data'] ?? $data['audio'] ?? '',
            format: $data['format'] ?? 'mp3',
            isBinary: $data['is_binary'] ?? false,
            id: $data['id'] ?? null,
            model: $data['model'] ?? null,
            usage: isset($data['usage']) ? Usage::fromArray($data['usage']) : null,
            raw: $data,
        );
    }

    public static function fromBinary(string $binary, string $format = 'mp3'): self
    {
        return new self(
            data: $binary,
            format: $format,
            isBinary: true,
        );
    }

    public function save(string $path): bool
    {
        return file_put_contents($path, $this->data) !== false;
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'format' => $this->format,
            'size' => strlen($this->data),
        ];
    }
}
