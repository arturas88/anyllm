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
        /** @var array<string, string> */
        public array $headers = [],
        /** @var array<string, mixed> */
        public array $options = [],
    ) {}

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $apiKey = $config['api_key'] ?? $config['apiKey'] ?? null;
        $baseUri = $config['base_uri'] ?? $config['baseUri'] ?? null;
        $organization = $config['organization'] ?? null;
        $project = $config['project'] ?? null;
        return new self(
            apiKey: ($apiKey === null || is_string($apiKey)) ? $apiKey : (string) $apiKey,
            baseUri: ($baseUri === null || is_string($baseUri)) ? $baseUri : (string) $baseUri,
            organization: ($organization === null || is_string($organization)) ? $organization : (string) $organization,
            project: ($project === null || is_string($project)) ? $project : (string) $project,
            timeout: is_int($config['timeout'] ?? null) ? $config['timeout'] : 60,
            headers: is_array($config['headers'] ?? null) ? $config['headers'] : [],
            options: is_array($config['options'] ?? null) ? $config['options'] : [],
        );
    }
}
