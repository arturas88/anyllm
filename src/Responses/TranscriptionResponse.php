<?php

declare(strict_types=1);

namespace AnyLLM\Responses;

use AnyLLM\Responses\Parts\Usage;

final class TranscriptionResponse extends Response
{
    public function __construct(
        public readonly string $text,
        public readonly ?string $language = null,
        public readonly ?float $duration = null,
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
            text: $data['text'] ?? '',
            language: $data['language'] ?? null,
            duration: $data['duration'] ?? null,
            id: $data['id'] ?? null,
            model: $data['model'] ?? null,
            usage: isset($data['usage']) ? Usage::fromArray($data['usage']) : null,
            raw: $data,
        );
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'text' => $this->text,
            'language' => $this->language,
            'duration' => $this->duration,
        ];
    }
}
