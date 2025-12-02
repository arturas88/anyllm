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
        // Handle image URL or base64 data
        $imageData = '';
        $mimeType = 'image/jpeg';

        if (is_string($image)) {
            // Check if it's already a data URI (base64)
            if (str_starts_with($image, 'data:')) {
                $imageData = $image;
            } elseif (filter_var($image, FILTER_VALIDATE_URL)) {
                // Fetch image from URL and convert to base64
                $imageContent = $this->fetchImageFromUrl($image);

                // Determine MIME type from URL or content
                $mimeType = $this->detectMimeType($image, $imageContent);
                $base64 = base64_encode($imageContent);
                $imageData = "data:{$mimeType};base64,{$base64}";
            } elseif (file_exists($image)) {
                // Local file path
                $imageContent = file_get_contents($image);
                $mimeType = mime_content_type($image) ?: 'image/jpeg';
                $base64 = base64_encode($imageContent);
                $imageData = "data:{$mimeType};base64,{$base64}";
            } else {
                // Assume it's already base64 encoded
                $imageData = $image;
            }
        }

        $messages = [
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $prompt ?? 'Extract all text from this image.'],
                    ['type' => 'image_url', 'image_url' => ['url' => $imageData]],
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

    /**
     * Fetch image content from URL using cURL or file_get_contents.
     */
    private function fetchImageFromUrl(string $url): string
    {
        // Try cURL first if available (more reliable)
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_USERAGENT => 'AnyLLM/1.0',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER => [
                    'Accept: image/*',
                ],
            ]);

            $content = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($content === false || $httpCode >= 400) {
                $errorMsg = $error ?: "HTTP {$httpCode}";
                throw new \AnyLLM\Exceptions\InvalidRequestException(
                    "File could not be fetched from url '{$url}': {$errorMsg}"
                );
            }

            return $content;
        }

        // Fallback to file_get_contents
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'AnyLLM/1.0',
                'follow_location' => 1,
                'max_redirects' => 5,
            ],
        ]);

        $content = @file_get_contents($url, false, $context);
        if ($content === false) {
            $error = error_get_last();
            $errorMsg = $error ? $error['message'] : 'Unknown error';
            throw new \AnyLLM\Exceptions\InvalidRequestException(
                "File could not be fetched from url '{$url}': {$errorMsg}"
            );
        }

        return $content;
    }

    /**
     * Detect MIME type from URL extension or content.
     */
    private function detectMimeType(string $url, string $content): string
    {
        // Try to detect from content first if finfo is available
        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = @finfo_buffer($finfo, $content);
                if ($detected && str_starts_with($detected, 'image/')) {
                    return $detected;
                }
            }
        }

        // Fallback to URL extension
        $path = parse_url($url, PHP_URL_PATH);
        $extension = $path ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : '';
        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            default => 'image/jpeg',
        };
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
        return array_filter($params, fn($v) => $v !== null);
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
            fn($message) => $message instanceof Message
                ? $message->toProviderFormat('openai')
                : $message,
            $messages
        );
    }

    private function formatTools(array $tools): array
    {
        return array_map(
            fn($tool) => $tool instanceof Tool
                ? $tool->toProviderFormat('openai')
                : $tool,
            $tools
        );
    }
}
