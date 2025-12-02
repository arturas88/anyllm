<?php

declare(strict_types=1);

namespace AnyLLM\Responses;

use AnyLLM\Responses\Parts\Usage;

final class AudioResponse extends Response
{
    /**
     * @param array<string, mixed>|null $raw
     */
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

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): static
    {
        $dataValue = $data['data'] ?? $data['audio'] ?? '';
        $formatValue = $data['format'] ?? 'mp3';
        $isBinaryValue = $data['is_binary'] ?? false;
        return new self(
            data: is_string($dataValue) ? $dataValue : (string) $dataValue,
            format: is_string($formatValue) ? $formatValue : 'mp3',
            isBinary: is_bool($isBinaryValue) ? $isBinaryValue : false,
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'format' => $this->format,
            'size' => strlen($this->data),
        ];
    }
}
