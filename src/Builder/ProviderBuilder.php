<?php

declare(strict_types=1);

namespace AnyLLM\Builder;

use AnyLLM\Contracts\ProviderInterface;
use AnyLLM\Enums\Provider;

final class ProviderBuilder
{
    private ?string $apiKey = null;
    private ?string $baseUri = null;
    private ?string $model = null;
    /** @var array<string, mixed> */
    private array $config = [];

    public function __construct(
        private readonly Provider|string $provider,
    ) {}

    public function apiKey(?string $apiKey): self
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    public function baseUri(?string $baseUri): self
    {
        $this->baseUri = $baseUri;
        return $this;
    }

    public function model(?string $model): self
    {
        $this->model = $model;
        return $this;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function config(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    public function build(): ProviderInterface
    {
        $config = $this->config;

        // Store model in options if provided (for potential future use)
        if ($this->model !== null) {
            $config['default_model'] = $this->model;
        }

        return \AnyLLM\AnyLLM::create(
            provider: $this->provider,
            apiKey: $this->apiKey,
            baseUri: $this->baseUri,
            config: $config,
        );
    }
}
