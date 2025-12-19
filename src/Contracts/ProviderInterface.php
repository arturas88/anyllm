<?php

declare(strict_types=1);

namespace AnyLLM\Contracts;

use AnyLLM\Messages\Message;
use AnyLLM\Responses\ChatResponse;
use AnyLLM\Responses\TextResponse;
use AnyLLM\Responses\StructuredResponse;
use AnyLLM\StructuredOutput\Schema;
use AnyLLM\Tools\Tool;

interface ProviderInterface
{
    public function name(): string;

    public function supports(string $capability): bool;

    /**
     * @return array<string, mixed>
     */
    public function listModels(): array;

    /**
     * @param array<string>|null $stop
     * @param array<string, mixed> $options
     */
    public function generateText(
        string $model,
        string $prompt,
        ?float $temperature = null,
        ?int $maxTokens = null,
        ?array $stop = null,
        array $options = [],
    ): TextResponse;

    /**
     * @param array<string, mixed> $options
     * @return \Generator<int, string, mixed, TextResponse>
     */
    public function streamText(
        string $model,
        string $prompt,
        ?float $temperature = null,
        ?int $maxTokens = null,
        array $options = [],
    ): \Generator;

    /**
     * @param array<Message> $messages
     * @param array<Tool>|null $tools
     * @param array<string, mixed> $options
     */
    public function chat(
        string $model,
        array $messages,
        ?float $temperature = null,
        ?int $maxTokens = null,
        ?array $tools = null,
        ?string $toolChoice = null,
        array $options = [],
    ): ChatResponse;

    /**
     * @param array<Message> $messages
     * @param array<string, mixed> $options
     * @return \Generator<int, string, mixed, ChatResponse>
     */
    public function streamChat(
        string $model,
        array $messages,
        ?float $temperature = null,
        ?int $maxTokens = null,
        array $options = [],
    ): \Generator;

    /**
     * @template T
     * @param array<string, mixed>|string $prompt
     * @param Schema<T>|class-string<T> $schema
     * @param array<string, mixed> $options
     * @return StructuredResponse<T>
     */
    public function generateObject(
        string $model,
        string|array $prompt,
        Schema|string $schema,
        ?string $schemaName = null,
        ?string $schemaDescription = null,
        array $options = [],
    ): StructuredResponse;

    /**
     * Generate text asynchronously (returns a Promise).
     *
     * @param array<string>|null $stop
     * @param array<string, mixed> $options
     * @return \GuzzleHttp\Promise\PromiseInterface<TextResponse>
     */
    public function generateTextAsync(
        string $model,
        string $prompt,
        ?float $temperature = null,
        ?int $maxTokens = null,
        ?array $stop = null,
        array $options = [],
    ): \GuzzleHttp\Promise\PromiseInterface;

    /**
     * Chat asynchronously (returns a Promise).
     *
     * @param array<Message> $messages
     * @param array<Tool>|null $tools
     * @param array<string, mixed> $options
     * @return \GuzzleHttp\Promise\PromiseInterface<ChatResponse>
     */
    public function chatAsync(
        string $model,
        array $messages,
        ?float $temperature = null,
        ?int $maxTokens = null,
        ?array $tools = null,
        ?string $toolChoice = null,
        array $options = [],
    ): \GuzzleHttp\Promise\PromiseInterface;

    public function getHttpClient(): HttpClientInterface;

    /**
     * Configure retry behavior for HTTP requests.
     * 
     * @param int $maxRetries Maximum number of retry attempts
     * @param int $initialDelayMs Initial delay in milliseconds before first retry
     * @param float $multiplier Multiplier for exponential backoff
     * @return static
     */
    public function withRetry(int $maxRetries = 3, int $initialDelayMs = 1000, float $multiplier = 2.0): static;

    /**
     * Disable retry behavior.
     * 
     * @return static
     */
    public function withoutRetry(): static;

    /**
     * Enable debugging to see exact requests and responses sent to/from the LLM.
     * 
     * By default, base64-encoded content (like images or files) will be truncated in logs for readability.
     * 
     * @param callable(string, array<string, mixed>): void|null $logger Optional custom logger callback
     * @param bool $showFullBase64 If true, shows full base64 content in logs. If false (default), truncates base64 content.
     * @return static
     */
    public function withDebugging(?callable $logger = null, bool $showFullBase64 = false): static;

    /**
     * Disable debugging.
     * 
     * @return static
     */
    public function withoutDebugging(): static;
}
