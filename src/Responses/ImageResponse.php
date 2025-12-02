<?php

declare(strict_types=1);

namespace AnyLLM\Responses;

use AnyLLM\Responses\Parts\Usage;

final class ImageResponse extends Response
{
    /**
     * @param array<string> $urls
     * @param array<string, mixed>|null $raw
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

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): static
    {
        $urls = [];

        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $image) {
                if (is_array($image)) {
                    $url = $image['url'] ?? $image['b64_json'] ?? '';
                    if (is_string($url)) {
                        $urls[] = $url;
                    }
                }
            }
        } elseif (isset($data['urls']) && is_array($data['urls'])) {
            $urls = array_filter($data['urls'], fn($u) => is_string($u));
        }

        return new self(
            urls: $urls,
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
            'urls' => $this->urls,
        ];
    }
}
