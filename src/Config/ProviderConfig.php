<?php

declare(strict_types=1);

namespace AnyLLM\Config;

final readonly class ProviderConfig
{
    public function __construct(
        public ?string $apiKey = null,
        public ?string $baseUri = null,
        public ?string $organization = null,
        public ?string $project = null,
        public int $timeout = 60,
        public array $headers = [],
        public array $options = [],
    ) {}

    public static function fromArray(array $config): self
    {
        return new self(
            apiKey: $config['api_key'] ?? $config['apiKey'] ?? null,
            baseUri: $config['base_uri'] ?? $config['baseUri'] ?? null,
            organization: $config['organization'] ?? null,
            project: $config['project'] ?? null,
            timeout: $config['timeout'] ?? 60,
            headers: $config['headers'] ?? [],
            options: $config['options'] ?? [],
        );
    }
}
