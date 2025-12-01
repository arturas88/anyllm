<?php

declare(strict_types=1);

namespace AnyLLM\Providers\XAI;

use AnyLLM\Messages\Message;
use AnyLLM\Providers\AbstractProvider;
use AnyLLM\Responses\ChatResponse;
use AnyLLM\Responses\StructuredResponse;
use AnyLLM\Responses\TextResponse;
use AnyLLM\StructuredOutput\Schema;
use AnyLLM\Tools\Tool;

/**
 * xAI Provider - Access to Grok models
 * 
 * xAI's API is OpenAI-compatible, making integration straightforward.
 */
final class XAIProvider extends AbstractProvider
{
    protected const CAPABILITIES = [
        'chat',
        'streaming',
        'tools',
        'structured_output',
        'image_input',
        'vision',
    ];

    public function name(): string
    {
        return 'xai';
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, self::CAPABILITIES, true);
    }

    protected function getBaseUri(): string
    {
        return $this->config->baseUri ?? 'https://api.x.ai/v1';
    }

    protected function getDefaultHeaders(): array
    {
        return array_merge([
            'Authorization' => "Bearer {$this->config->apiKey}",
            'Content-Type' => 'application/json',
        ], $this->config->headers);
    }

    public function generateText(
        string $model,
        string $prompt,
        ?float $temperature = null,
        ?int $maxTokens = null,
        ?array $stop = null,
        array $options = [],
    ): TextResponse {
        $response = $this->chat(
            model: $model,
            messages: [['role' => 'user', 'content' => $prompt]],
            temperature: $temperature,
            maxTokens: $maxTokens,
            options: $options,
        );

        return TextResponse::fromArray([
            'text' => $response->content,
            'id' => $response->id,
            'model' => $response->model,
            'usage' => $response->usage?->toArray(),
            'finish_reason' => $response->finishReason?->value,
        ]);
    }

    public function streamText(
        string $model,
        string $prompt,
        ?float $temperature = null,
        ?int $maxTokens = null,
        array $options = [],
    ): \Generator {
        foreach ($this->streamChat(
            model: $model,
            messages: [['role' => 'user', 'content' => $prompt]],
            temperature: $temperature,
            maxTokens: $maxTokens,
            options: $options
        ) as $chunk) {
            yield $chunk;
        }
    }

    public function chat(
        string $model,
        array $messages,
        ?float $temperature = null,
        ?int $maxTokens = null,
        ?array $tools = null,
        ?string $toolChoice = null,
        array $options = [],
    ): ChatResponse {
        $response = $this->request('chat.create', '/chat/completions', [
            'model' => $model,
            'messages' => $this->formatMessages($messages),
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'tools' => $tools ? $this->formatTools($tools) : null,
            'tool_choice' => $toolChoice,
            ...$options,
        ]);

        return ChatResponse::fromArray($response);
    }

    public function streamChat(
        string $model,
        array $messages,
        ?float $temperature = null,
        ?int $maxTokens = null,
        array $options = [],
    ): \Generator {
        $fullContent = '';
        $toolCalls = [];
        $usage = null;

        foreach ($this->stream('chat.create', '/chat/completions', [
            'model' => $model,
            'messages' => $this->formatMessages($messages),
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'stream_options' => ['include_usage' => true],
            ...$options,
        ]) as $chunk) {
            $delta = $chunk['choices'][0]['delta'] ?? [];

            if (isset($delta['content'])) {
                $fullContent .= $delta['content'];
                yield $delta['content'];
            }

            if (isset($delta['tool_calls'])) {
                foreach ($delta['tool_calls'] as $tc) {
                    $index = $tc['index'];
                    if (! isset($toolCalls[$index])) {
                        $toolCalls[$index] = [
                            'id' => $tc['id'] ?? '',
                            'name' => '',
                            'arguments' => '',
                        ];
                    }
                    if (isset($tc['function']['name'])) {
                        $toolCalls[$index]['name'] = $tc['function']['name'];
                    }
                    if (isset($tc['function']['arguments'])) {
                        $toolCalls[$index]['arguments'] .= $tc['function']['arguments'];
                    }
                }
            }

            if (isset($chunk['usage'])) {
                $usage = $chunk['usage'];
            }
        }

        return ChatResponse::fromArray([
            'content' => $fullContent,
            'tool_calls' => array_values($toolCalls),
            'usage' => $usage,
        ]);
    }

    public function generateObject(
        string $model,
        string|array $prompt,
        Schema|string $schema,
        ?string $schemaName = null,
        ?string $schemaDescription = null,
        array $options = [],
    ): StructuredResponse {
        $jsonSchema = $schema instanceof Schema
            ? $schema->toJsonSchema()
            : Schema::fromClass($schema)->toJsonSchema();

        $messages = is_array($prompt)
            ? $this->formatMessages($prompt)
            : [['role' => 'user', 'content' => $prompt]];

        $response = $this->request('chat.create', '/chat/completions', [
            'model' => $model,
            'messages' => $messages,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $schemaName ?? 'response',
                    'description' => $schemaDescription,
                    'schema' => $jsonSchema,
                    'strict' => true,
                ],
            ],
            ...$options,
        ]);

        $content = $response['choices'][0]['message']['content'] ?? '{}';

        return StructuredResponse::fromArray([
            'content' => $content,
            'id' => $response['id'] ?? null,
            'model' => $response['model'] ?? null,
            'usage' => $response['usage'] ?? null,
        ], $schema);
    }

    protected function mapRequest(string $method, array $params): array
    {
        return array_filter($params, fn ($v) => $v !== null);
    }

    protected function mapResponse(string $method, array $response): array
    {
        return match ($method) {
            'chat.create' => $this->mapChatResponse($response),
            default => $response,
        };
    }

    protected function mapStreamChunk(string $method, array $chunk): mixed
    {
        return $chunk;
    }

    private function mapChatResponse(array $response): array
    {
        $message = $response['choices'][0]['message'] ?? [];

        return [
            'id' => $response['id'],
            'content' => $message['content'] ?? '',
            'tool_calls' => $message['tool_calls'] ?? [],
            'model' => $response['model'],
            'usage' => $response['usage'] ?? null,
            'finish_reason' => $response['choices'][0]['finish_reason'] ?? null,
        ];
    }

    private function formatMessages(array $messages): array
    {
        return array_map(
            fn ($message) => $message instanceof Message
                ? $message->toProviderFormat('openai')
                : $message,
            $messages
        );
    }

    private function formatTools(array $tools): array
    {
        return array_map(
            fn ($tool) => $tool instanceof Tool
                ? $tool->toProviderFormat('openai')
                : $tool,
            $tools
        );
    }
}

