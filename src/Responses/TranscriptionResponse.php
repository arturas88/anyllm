<?php

declare(strict_types=1);

namespace AnyLLM\Responses;

use AnyLLM\Responses\Parts\Usage;

final class TranscriptionResponse extends Response
{
    /**
     * @param array<string, mixed>|null $raw
     */
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

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): static
    {
        $text = $data['text'] ?? '';
        $language = $data['language'] ?? null;
        $duration = $data['duration'] ?? null;
        $textValue = is_string($text) ? $text : (is_scalar($text) ? (string) $text : '');
        $languageValue = is_string($language) || $language === null ? $language : (is_scalar($language) ? (string) $language : null);
        return new self(
            text: $textValue,
            language: $languageValue,
            duration: is_float($duration) || is_int($duration) || $duration === null ? ($duration === null ? null : (float) $duration) : null,
            id: $data['id'] ?? null,
            model: $data['model'] ?? null,
            usage: isset($data['usage']) ? Usage::fromArray($data['usage']) : null,
            raw: $data,
        );
    }

    /**
     * @return array<string, mixed>
     */
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
