<?php

declare(strict_types=1);

namespace AnyLLM\Providers;

use AnyLLM\Config\ProviderConfig;
use AnyLLM\Contracts\HttpClientInterface;
use AnyLLM\Contracts\ProviderInterface;
use AnyLLM\Http\HttpClient;
use AnyLLM\Http\HttpClientFactory;
use AnyLLM\Http\RetryHandler;
use AnyLLM\Messages\Message;
use AnyLLM\Messages\UserMessage;
use AnyLLM\Responses\ChatResponse;
use AnyLLM\Responses\TextResponse;
use GuzzleHttp\Promise\PromiseInterface;

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
        $retryMaxAttempts = is_int($config->options['retry_max_attempts'] ?? null) ? $config->options['retry_max_attempts'] : 3;
        $retryInitialDelay = is_int($config->options['retry_initial_delay'] ?? null) ? $config->options['retry_initial_delay'] : 1000;
        $this->retryHandler = new RetryHandler(
            maxRetries: $retryMaxAttempts,
            initialDelayMs: $retryInitialDelay,
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

    /**
     * @return array<string, string>
     */
    abstract protected function getDefaultHeaders(): array;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    abstract protected function mapRequest(string $method, array $params): array;

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    abstract protected function mapResponse(string $method, array $response): array;

    /**
     * @param array<string, mixed> $chunk
     */
    abstract protected function mapStreamChunk(string $method, array $chunk): mixed;

    /**
     * @return array<string>
     */
    public function listModels(): array
    {
        // Default implementation - providers can override
        return [];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
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

    /**
     * @param array<string, mixed> $params
     * @return \Generator<int, mixed>
     */
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
        $model = $this->config->options['default_model'] ?? null;
        return is_string($model) ? $model : null;
    }

    /**
     * @param array<string>|null $stop
     * @param array<string, mixed> $options
     * @return PromiseInterface<TextResponse>
     */
    public function generateTextAsync(
        string $model,
        string $prompt,
        ?float $temperature = null,
        ?int $maxTokens = null,
        ?array $stop = null,
        array $options = [],
    ): PromiseInterface {
        return $this->chatAsync(
            model: $model,
            messages: [UserMessage::create($prompt)],
            temperature: $temperature,
            maxTokens: $maxTokens,
            options: $options,
        )->then(function (ChatResponse $response) {
            return TextResponse::fromArray([
                'text' => $response->content,
                'id' => $response->id,
                'model' => $response->model,
                'usage' => $response->usage?->toArray(),
                'finish_reason' => $response->finishReason?->value,
            ]);
        });
    }

    /**
     * @param array<\AnyLLM\Messages\Message|array<string, mixed>> $messages
     * @param array<\AnyLLM\Tools\Tool>|null $tools
     * @param array<string, mixed> $options
     * @return PromiseInterface<ChatResponse>
     */
    public function chatAsync(
        string $model,
        array $messages,
        ?float $temperature = null,
        ?int $maxTokens = null,
        ?array $tools = null,
        ?string $toolChoice = null,
        array $options = [],
    ): PromiseInterface {
        // Default implementation - providers can override for custom behavior
        // Format messages if they're Message objects (like sync chat() does)
        /** @var array<Message|array<string, mixed>> $messages */
        $formattedMessages = array_map(
            fn($msg) => $msg instanceof Message
                ? $msg->toProviderFormat($this->name())
                : $msg,
            $messages
        );

        $mappedRequest = $this->mapRequest('chat.create', [
            'model' => $model,
            'messages' => $formattedMessages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'tools' => $tools,
            'tool_choice' => $toolChoice,
            ...$options,
        ]);

        $endpoint = $this->getChatEndpoint();

        return $this->http->postAsync($endpoint, $mappedRequest)
            ->then(function (array $response): ChatResponse {
                $mappedResponse = $this->mapResponse('chat.create', $response);
                return ChatResponse::fromArray($mappedResponse);
            });
    }

    /**
     * Get the chat endpoint for this provider.
     * Override in provider implementations if different endpoint is needed.
     */
    protected function getChatEndpoint(): string
    {
        return '/chat/completions';
    }
}
