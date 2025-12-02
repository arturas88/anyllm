<?php

declare(strict_types=1);

namespace AnyLLM\Providers\Anthropic;

use AnyLLM\Messages\Message;
use AnyLLM\Messages\SystemMessage;
use AnyLLM\Providers\AbstractProvider;
use AnyLLM\Responses\ChatResponse;
use AnyLLM\Responses\StructuredResponse;
use AnyLLM\Responses\TextResponse;
use AnyLLM\StructuredOutput\Schema;
use AnyLLM\Tools\Tool;

final class AnthropicProvider extends AbstractProvider
{
    protected const CAPABILITIES = [
        'chat',
        'streaming',
        'tools',
        'structured_output',
        'image_input',
        'pdf_input',
        'vision',
        'extended_thinking',
    ];

    public function name(): string
    {
        return 'anthropic';
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, self::CAPABILITIES, true);
    }

    protected function getBaseUri(): string
    {
        return $this->config->baseUri ?? 'https://api.anthropic.com/v1';
    }

    protected function getDefaultHeaders(): array
    {
        $headers = [
            'anthropic-version' => '2024-01-01',
            'Content-Type' => 'application/json',
        ];

        if ($this->config->apiKey !== null) {
            $headers['x-api-key'] = $this->config->apiKey;
        }

        return array_merge($headers, $this->config->headers);
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
        [$systemPrompt, $conversationMessages] = $this->extractSystemMessage($messages);

        $response = $this->request('messages.create', '/messages', [
            'model' => $model,
            'system' => $systemPrompt,
            'messages' => $this->formatMessages($conversationMessages),
            'temperature' => $temperature,
            'max_tokens' => $maxTokens ?? 4096,
            'tools' => $tools ? $this->formatTools($tools) : null,
            'tool_choice' => $toolChoice ? $this->formatToolChoice($toolChoice) : null,
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
        [$systemPrompt, $conversationMessages] = $this->extractSystemMessage($messages);

        $fullContent = '';
        $usage = null;

        foreach ($this->stream('messages.create', '/messages', [
            'model' => $model,
            'system' => $systemPrompt,
            'messages' => $this->formatMessages($conversationMessages),
            'temperature' => $temperature,
            'max_tokens' => $maxTokens ?? 4096,
            ...$options,
        ]) as $chunk) {
            if ($chunk['type'] === 'content_block_delta') {
                $delta = $chunk['delta']['text'] ?? '';
                $fullContent .= $delta;
                yield $delta;
            }

            if ($chunk['type'] === 'message_delta' && isset($chunk['usage'])) {
                $usage = $chunk['usage'];
            }
        }

        return ChatResponse::fromArray([
            'content' => $fullContent,
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
            ? $prompt
            : [['role' => 'user', 'content' => $prompt]];

        $toolName = $schemaName ?? 'extract_data';
        $tool = [
            'name' => $toolName,
            'description' => $schemaDescription ?? 'Extract structured data',
            'input_schema' => $jsonSchema,
        ];

        $response = $this->chat(
            model: $model,
            messages: $messages,
            tools: [$tool],
            toolChoice: ['type' => 'tool', 'name' => $toolName],
            options: $options,
        );

        $toolInput = $response->toolCalls[0]->arguments ?? [];

        return StructuredResponse::fromArray([
            'object' => $toolInput,
            'id' => $response->id,
            'model' => $response->model,
            'usage' => $response->usage?->toArray(),
        ], $schema);
    }

    protected function mapRequest(string $method, array $params): array
    {
        return array_filter($params, fn($v) => $v !== null);
    }

    protected function mapResponse(string $method, array $response): array
    {
        return match ($method) {
            'messages.create' => $this->mapMessagesResponse($response),
            default => $response,
        };
    }

    protected function mapStreamChunk(string $method, array $chunk): mixed
    {
        return $chunk;
    }

    private function mapMessagesResponse(array $response): array
    {
        return [
            'id' => $response['id'],
            'content' => $this->extractContent($response['content']),
            'tool_calls' => $this->extractToolCalls($response['content']),
            'usage' => [
                'prompt_tokens' => $response['usage']['input_tokens'],
                'completion_tokens' => $response['usage']['output_tokens'],
                'total_tokens' => $response['usage']['input_tokens'] + $response['usage']['output_tokens'],
            ],
            'finish_reason' => $this->mapStopReason($response['stop_reason']),
            'model' => $response['model'],
        ];
    }

    private function extractSystemMessage(array $messages): array
    {
        $system = null;
        $filtered = [];

        foreach ($messages as $message) {
            if ($message instanceof SystemMessage || ($message['role'] ?? null) === 'system') {
                $system = $message instanceof SystemMessage
                    ? $message->content
                    : $message['content'];
            } else {
                $filtered[] = $message;
            }
        }

        return [$system, $filtered];
    }

    private function extractContent(array $content): string
    {
        $text = '';
        foreach ($content as $block) {
            if ($block['type'] === 'text') {
                $text .= $block['text'];
            }
        }
        return $text;
    }

    private function extractToolCalls(array $content): array
    {
        $toolCalls = [];
        foreach ($content as $block) {
            if ($block['type'] === 'tool_use') {
                $toolCalls[] = [
                    'id' => $block['id'],
                    'name' => $block['name'],
                    'arguments' => $block['input'],
                ];
            }
        }
        return $toolCalls;
    }

    private function mapStopReason(?string $reason): string
    {
        return match ($reason) {
            'end_turn' => 'stop',
            'max_tokens' => 'length',
            'tool_use' => 'tool_calls',
            default => $reason ?? 'stop',
        };
    }

    private function formatMessages(array $messages): array
    {
        return array_map(
            fn($message) => $message instanceof Message
                ? $message->toProviderFormat('anthropic')
                : $message,
            $messages
        );
    }

    private function formatTools(array $tools): array
    {
        return array_map(
            fn($tool) => $tool instanceof Tool
                ? $tool->toProviderFormat('anthropic')
                : $tool,
            $tools
        );
    }

    private function formatToolChoice(string $toolChoice): array
    {
        return match ($toolChoice) {
            'auto' => ['type' => 'auto'],
            'required', 'any' => ['type' => 'any'],
            'none' => ['type' => 'none'],
            default => ['type' => 'tool', 'name' => $toolChoice],
        };
    }
}
