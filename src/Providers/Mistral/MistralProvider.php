<?php

declare(strict_types=1);

namespace AnyLLM\Providers\Mistral;

use AnyLLM\Contracts\ImageGenerationInterface;
use AnyLLM\Messages\Message;
use AnyLLM\Providers\AbstractProvider;
use AnyLLM\Responses\ChatResponse;
use AnyLLM\Responses\ImageResponse;
use AnyLLM\Responses\StructuredResponse;
use AnyLLM\Responses\TextResponse;
use AnyLLM\StructuredOutput\Schema;
use AnyLLM\Tools\Tool;

/**
 * Mistral AI Provider with OCR support
 * 
 * Mistral's API is OpenAI-compatible with additional features
 * including OCR capabilities through their vision models.
 */
final class MistralProvider extends AbstractProvider implements ImageGenerationInterface
{
    protected const CAPABILITIES = [
        'chat',
        'streaming',
        'tools',
        'structured_output',
        'image_input',
        'vision',
        'ocr',
        'embeddings',
    ];

    public function name(): string
    {
        return 'mistral';
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, self::CAPABILITIES, true);
    }

    protected function getBaseUri(): string
    {
        return $this->config->baseUri ?? 'https://api.mistral.ai/v1';
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
                'type' => 'json_object',
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

    /**
     * Extract text from an image using Mistral's OCR capabilities.
     * Use models like pixtral-12b-2409 for vision tasks.
     */
    public function extractText(
        string $model,
        mixed $image,
        ?string $prompt = null,
        array $options = [],
    ): array {
        $imageUrl = is_string($image) ? $image : '';
        
        $messages = [
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $prompt ?? 'Extract all text from this image.'],
                    ['type' => 'image_url', 'image_url' => ['url' => $imageUrl]],
                ],
            ],
        ];

        $response = $this->chat(
            model: $model,
            messages: $messages,
            options: $options,
        );

        return [
            'text' => $response->content,
            'usage' => $response->usage?->toArray(),
        ];
    }

    public function generateImage(
        string $model,
        string $prompt,
        ?string $size = null,
        ?int $n = 1,
        ?string $quality = null,
        ?string $style = null,
        array $options = [],
    ): ImageResponse {
        // Mistral doesn't have native image generation yet,
        // but this method is here for interface compatibility
        return ImageResponse::fromArray(['urls' => []]);
    }

    public function editImage(
        string $model,
        mixed $image,
        string $prompt,
        mixed $mask = null,
        array $options = [],
    ): ImageResponse {
        return ImageResponse::fromArray(['urls' => []]);
    }

    public function upscaleImage(
        string $model,
        mixed $image,
        ?int $scale = null,
        array $options = [],
    ): ImageResponse {
        return ImageResponse::fromArray(['urls' => []]);
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

