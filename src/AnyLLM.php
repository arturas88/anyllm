<?php

declare(strict_types=1);

namespace AnyLLM;

use AnyLLM\Builder\ProviderBuilder;
use AnyLLM\Config\ProviderConfig;
use AnyLLM\Contracts\ProviderInterface;
use AnyLLM\Enums\Provider;
use AnyLLM\Providers\OpenAI\OpenAIProvider;
use AnyLLM\Providers\Anthropic\AnthropicProvider;
use AnyLLM\Providers\Google\GoogleProvider;
use AnyLLM\Providers\Mistral\MistralProvider;
use AnyLLM\Providers\XAI\XAIProvider;
use AnyLLM\Providers\OpenRouter\OpenRouterProvider;
use AnyLLM\Providers\Ollama\OllamaProvider;

final class AnyLLM
{
    /**
     * Create a provider builder for fluent configuration.
     */
    public static function provider(Provider|string $provider): ProviderBuilder
    {
        return new ProviderBuilder($provider);
    }

    /**
     * Create a provider instance.
     *
     * @param array<string, mixed> $config
     */
    public static function create(
        Provider|string $provider,
        ?string $apiKey = null,
        ?string $baseUri = null,
        array $config = [],
    ): ProviderInterface {
        $providerName = $provider instanceof Provider ? $provider->value : $provider;

        $providerConfig = ProviderConfig::fromArray([
            'api_key' => $apiKey,
            'base_uri' => $baseUri,
            ...$config,
        ]);

        return match ($providerName) {
            'openai', Provider::OpenAI->value => new OpenAIProvider($providerConfig),
            'anthropic', Provider::Anthropic->value => new AnthropicProvider($providerConfig),
            'google', Provider::Google->value => new GoogleProvider($providerConfig),
            'mistral', Provider::Mistral->value => new MistralProvider($providerConfig),
            'xai', Provider::XAI->value => new XAIProvider($providerConfig),
            'openrouter', Provider::OpenRouter->value => new OpenRouterProvider($providerConfig),
            'ollama', Provider::Ollama->value => new OllamaProvider($providerConfig),
            default => throw new \InvalidArgumentException("Unsupported provider: {$providerName}"),
        };
    }

    /**
     * Create an OpenAI provider instance.
     *
     * @param array<string, mixed> $config
     */
    public static function openai(?string $apiKey = null, array $config = []): ProviderInterface
    {
        return self::create(Provider::OpenAI, $apiKey, config: $config);
    }

    /**
     * Create an Anthropic provider instance.
     *
     * @param array<string, mixed> $config
     */
    public static function anthropic(?string $apiKey = null, array $config = []): ProviderInterface
    {
        return self::create(Provider::Anthropic, $apiKey, config: $config);
    }

    /**
     * Create a Google AI provider instance.
     *
     * @param array<string, mixed> $config
     */
    public static function google(?string $apiKey = null, array $config = []): ProviderInterface
    {
        return self::create(Provider::Google, $apiKey, config: $config);
    }

    /**
     * Create a Mistral AI provider instance.
     *
     * @param array<string, mixed> $config
     */
    public static function mistral(?string $apiKey = null, array $config = []): ProviderInterface
    {
        return self::create(Provider::Mistral, $apiKey, config: $config);
    }

    /**
     * Create an xAI (Grok) provider instance.
     *
     * @param array<string, mixed> $config
     */
    public static function xai(?string $apiKey = null, array $config = []): ProviderInterface
    {
        return self::create(Provider::XAI, $apiKey, config: $config);
    }

    /**
     * Create an OpenRouter provider instance (access to 100+ models).
     *
     * @param array<string, mixed> $config
     */
    public static function openrouter(?string $apiKey = null, array $config = []): ProviderInterface
    {
        return self::create(Provider::OpenRouter, $apiKey, config: $config);
    }

    /**
     * Create an Ollama provider instance (local models).
     *
     * @param array<string, mixed> $config
     */
    public static function ollama(?string $baseUri = null, array $config = []): ProviderInterface
    {
        return self::create(Provider::Ollama, null, $baseUri ?? 'http://localhost:11434/v1', $config);
    }
}
