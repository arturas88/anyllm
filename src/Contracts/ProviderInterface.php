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

    public function generateText(
        string $model,
        string $prompt,
        ?float $temperature = null,
        ?int $maxTokens = null,
        ?array $stop = null,
        array $options = [],
    ): TextResponse;

    /**
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
     * @param Schema<T>|class-string<T> $schema
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

    public function getHttpClient(): HttpClientInterface;
}

