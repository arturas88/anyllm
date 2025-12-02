<?php

declare(strict_types=1);

namespace AnyLLM\Providers;

use AnyLLM\Config\ProviderConfig;
use AnyLLM\Contracts\HttpClientInterface;
use AnyLLM\Contracts\ProviderInterface;
use AnyLLM\Http\HttpClient;
use AnyLLM\Http\HttpClientFactory;
use AnyLLM\Http\RetryHandler;

abstract class AbstractProvider implements ProviderInterface
{
    protected HttpClient $http;
    protected ?RetryHandler $retryHandler = null;

    public function __construct(
        protected ProviderConfig $config,
        ?HttpClientInterface $httpClient = null,
    ) {
        if ($httpClient instanceof HttpClient) {
            $this->http = $httpClient;
        } else {
            $psrClient = HttpClientFactory::create();
            $this->http = new HttpClient(
                client: $psrClient,
                baseUri: $this->getBaseUri(),
                headers: $this->getDefaultHeaders(),
            );
        }

        // Enable retry by default
        $this->retryHandler = new RetryHandler(
            maxRetries: $config->options['retry_max_attempts'] ?? 3,
            initialDelayMs: $config->options['retry_initial_delay'] ?? 1000,
        );
    }

    public function withRetry(int $maxRetries = 3, int $initialDelayMs = 1000, float $multiplier = 2.0): static
    {
        $clone = clone $this;
        $clone->retryHandler = new RetryHandler(
            maxRetries: $maxRetries,
            initialDelayMs: $initialDelayMs,
            multiplier: $multiplier,
        );
        return $clone;
    }

    public function withoutRetry(): static
    {
        $clone = clone $this;
        $clone->retryHandler = null;
        return $clone;
    }

    abstract protected function getBaseUri(): string;

    abstract protected function getDefaultHeaders(): array;

    abstract protected function mapRequest(string $method, array $params): array;

    abstract protected function mapResponse(string $method, array $response): array;

    abstract protected function mapStreamChunk(string $method, array $chunk): mixed;

    public function listModels(): array
    {
        // Default implementation - providers can override
        return [];
    }

    protected function request(
        string $method,
        string $endpoint,
        array $params = [],
    ): array {
        $mappedRequest = $this->mapRequest($method, $params);

        $operation = fn() => $this->http->post($endpoint, $mappedRequest);

        $response = $this->retryHandler
            ? $this->retryHandler->retry($operation)
            : $operation();

        return $this->mapResponse($method, $response);
    }

    protected function stream(
        string $method,
        string $endpoint,
        array $params = [],
    ): \Generator {
        $mappedRequest = $this->mapRequest($method, $params);
        $mappedRequest['stream'] = true;

        foreach ($this->http->stream($endpoint, $mappedRequest) as $chunk) {
            yield $this->mapStreamChunk($method, $chunk);
        }
    }

    public function getHttpClient(): HttpClientInterface
    {
        return $this->http;
    }

    /**
     * Get the default model from config if available.
     */
    protected function getDefaultModel(): ?string
    {
        return $this->config->options['default_model'] ?? null;
    }
}
