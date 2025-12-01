<?php

declare(strict_types=1);

namespace AnyLLM\Responses;

use AnyLLM\Responses\Parts\Usage;

final class ImageResponse extends Response
{
    /**
     * @param array<string> $urls
     */
    public function __construct(
        public readonly array $urls,
        ?string $id = null,
        ?string $model = null,
        ?Usage $usage = null,
        ?array $raw = null,
    ) {
        parent::__construct($id, $model, $usage, $raw);
    }

    public static function fromArray(array $data): static
    {
        $urls = [];
        
        if (isset($data['data'])) {
            foreach ($data['data'] as $image) {
                $urls[] = $image['url'] ?? $image['b64_json'] ?? '';
            }
        } elseif (isset($data['urls'])) {
            $urls = $data['urls'];
        }

        return new self(
            urls: $urls,
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
            'urls' => $this->urls,
        ];
    }
}

