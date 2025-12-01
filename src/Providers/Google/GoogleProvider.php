<?php

declare(strict_types=1);

namespace AnyLLM\Providers\Google;

use AnyLLM\Messages\Message;
use AnyLLM\Providers\AbstractProvider;
use AnyLLM\Responses\ChatResponse;
use AnyLLM\Responses\StructuredResponse;
use AnyLLM\Responses\TextResponse;
use AnyLLM\StructuredOutput\Schema;
use AnyLLM\Tools\Tool;

final class GoogleProvider extends AbstractProvider
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
        return 'google';
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, self::CAPABILITIES, true);
    }

    protected function getBaseUri(): string
    {
        return $this->config->baseUri ?? 'https://generativelanguage.googleapis.com/v1beta';
    }

    protected function getDefaultHeaders(): array
    {
        return array_merge([
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
        $response = $this->request('generateContent', "/models/{$model}:generateContent?key={$this->config->apiKey}", [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => array_filter([
                'temperature' => $temperature,
                'maxOutputTokens' => $maxTokens,
                'stopSequences' => $stop,
            ]),
            ...$options,
        ]);

        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

        return TextResponse::fromArray([
            'text' => $text,
            'model' => $model,
            'usage' => $response['usageMetadata'] ?? null,
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
        $response = $this->request('generateContent', "/models/{$model}:generateContent?key={$this->config->apiKey}", [
            'contents' => $this->formatMessages($messages),
            'generationConfig' => array_filter([
                'temperature' => $temperature,
                'maxOutputTokens' => $maxTokens,
            ]),
            'tools' => $tools ? $this->formatTools($tools) : null,
            ...$options,
        ]);

        $content = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

        return ChatResponse::fromArray([
            'content' => $content,
            'model' => $model,
            'usage' => $response['usageMetadata'] ?? null,
        ]);
    }

    public function streamChat(
        string $model,
        array $messages,
        ?float $temperature = null,
        ?int $maxTokens = null,
        array $options = [],
    ): \Generator {
        $fullContent = '';

        foreach ($this->stream('generateContent', "/models/{$model}:streamGenerateContent?key={$this->config->apiKey}", [
            'contents' => $this->formatMessages($messages),
            'generationConfig' => array_filter([
                'temperature' => $temperature,
                'maxOutputTokens' => $maxTokens,
            ]),
            ...$options,
        ]) as $chunk) {
            $text = $chunk['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $fullContent .= $text;
            yield $text;
        }

        return ChatResponse::fromArray(['content' => $fullContent]);
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

        $response = $this->request('generateContent', "/models/{$model}:generateContent?key={$this->config->apiKey}", [
            'contents' => $this->formatMessages($messages),
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => $jsonSchema,
            ],
            ...$options,
        ]);

        $content = $response['candidates'][0]['content']['parts'][0]['text'] ?? '{}';

        return StructuredResponse::fromArray([
            'content' => $content,
            'model' => $model,
            'usage' => $response['usageMetadata'] ?? null,
        ], $schema);
    }

    protected function mapRequest(string $method, array $params): array
    {
        return array_filter($params, fn ($v) => $v !== null);
    }

    protected function mapResponse(string $method, array $response): array
    {
        return $response;
    }

    protected function mapStreamChunk(string $method, array $chunk): mixed
    {
        return $chunk;
    }

    private function formatMessages(array $messages): array
    {
        $formatted = [];

        foreach ($messages as $message) {
            if ($message instanceof Message) {
                $role = $message->role->value === 'assistant' ? 'model' : 'user';
                $content = is_string($message->content) ? $message->content : '';
            } else {
                $role = ($message['role'] ?? 'user') === 'assistant' ? 'model' : 'user';
                $content = $message['content'] ?? '';
            }

            $formatted[] = [
                'role' => $role,
                'parts' => [['text' => $content]],
            ];
        }

        return $formatted;
    }

    private function formatTools(array $tools): array
    {
        $functionDeclarations = [];

        foreach ($tools as $tool) {
            if ($tool instanceof Tool) {
                $functionDeclarations[] = [
                    'name' => $tool->name,
                    'description' => $tool->description,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => $tool->parameters,
                    ],
                ];
            }
        }

        return [['functionDeclarations' => $functionDeclarations]];
    }
}

