<?php

declare(strict_types=1);

namespace AnyLLM\Providers\OpenAI;

use AnyLLM\Contracts\AudioInterface;
use AnyLLM\Contracts\ContentModerationInterface;
use AnyLLM\Contracts\EmbeddingInterface;
use AnyLLM\Contracts\ImageGenerationInterface;
use AnyLLM\Messages\Message;
use AnyLLM\Moderation\ModerationResponse;
use AnyLLM\Providers\AbstractProvider;
use AnyLLM\Responses\AudioResponse;
use AnyLLM\Responses\ChatResponse;
use AnyLLM\Responses\EmbeddingResponse;
use AnyLLM\Responses\ImageResponse;
use AnyLLM\Responses\Parts\Usage;
use AnyLLM\Responses\StructuredResponse;
use AnyLLM\Responses\TextResponse;
use AnyLLM\Responses\TranscriptionResponse;
use AnyLLM\StructuredOutput\Schema;
use AnyLLM\Tools\Tool;
use GuzzleHttp\Promise\PromiseInterface;

final class OpenAIProvider extends AbstractProvider implements
    ImageGenerationInterface,
    AudioInterface,
    EmbeddingInterface,
    ContentModerationInterface
{
    protected const CAPABILITIES = [
        'chat',
        'streaming',
        'tools',
        'structured_output',
        'image_generation',
        'image_input',
        'tts',
        'stt',
        'embeddings',
        'vision',
        'moderation',
    ];

    public function name(): string
    {
        return 'openai';
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, self::CAPABILITIES, true);
    }

    protected function getBaseUri(): string
    {
        return $this->config->baseUri ?? 'https://api.openai.com/v1';
    }

    protected function getDefaultHeaders(): array
    {
        $headers = [
            'Authorization' => "Bearer {$this->config->apiKey}",
            'Content-Type' => 'application/json',
        ];

        if ($this->config->organization) {
            $headers['OpenAI-Organization'] = $this->config->organization;
        }

        if ($this->config->project) {
            $headers['OpenAI-Project'] = $this->config->project;
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

    public function chatAsync(
        string $model,
        array $messages,
        ?float $temperature = null,
        ?int $maxTokens = null,
        ?array $tools = null,
        ?string $toolChoice = null,
        array $options = [],
    ): PromiseInterface {
        $mappedRequest = $this->mapRequest('chat.create', [
            'model' => $model,
            'messages' => $this->formatMessages($messages),
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'tools' => $tools ? $this->formatTools($tools) : null,
            'tool_choice' => $toolChoice,
            ...$options,
        ]);

        return $this->http->postAsync('/chat/completions', $mappedRequest)
            ->then(function (array $response) {
                $mappedResponse = $this->mapResponse('chat.create', $response);
                return ChatResponse::fromArray($mappedResponse);
            });
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

        // The response structure varies - sometimes content is in choices[0].message.content,
        // sometimes it's at the root level (after mapping)
        $message = $response['choices'][0]['message'] ?? $response;

        // Check for refusal (OpenAI may refuse to generate content)
        if (isset($message['refusal']) && $message['refusal'] !== null) {
            throw new \AnyLLM\Exceptions\InvalidRequestException(
                "Model refused to generate structured output: {$message['refusal']}"
            );
        }

        // Try to get content from different possible locations
        $content = $message['content']
            ?? $response['content']
            ?? $response['choices'][0]['message']['content']
            ?? null;

        // If content is null or empty, provide better error message
        if ($content === null || $content === '') {
            $finishReason = $message['finish_reason'] ?? $response['finish_reason'] ?? 'unknown';
            throw new \AnyLLM\Exceptions\InvalidRequestException(
                "OpenAI returned empty content. Finish reason: {$finishReason}. "
                . "This may indicate: 1) Invalid API key, 2) Model doesn't support structured outputs, "
                . "3) Schema validation error, or 4) Network issue. "
                . "Response: " . json_encode($response)
            );
        }

        return StructuredResponse::fromArray([
            'content' => $content,
            'id' => $response['id'] ?? null,
            'model' => $response['model'] ?? null,
            'usage' => $response['usage'] ?? null,
        ], $schema);
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
        $response = $this->request('images.generate', '/images/generations', [
            'model' => $model,
            'prompt' => $prompt,
            'size' => $size ?? '1024x1024',
            'n' => $n,
            'quality' => $quality,
            'style' => $style,
            ...$options,
        ]);

        return ImageResponse::fromArray($response);
    }

    public function editImage(
        string $model,
        mixed $image,
        string $prompt,
        mixed $mask = null,
        array $options = [],
    ): ImageResponse {
        // Simplified - actual implementation would handle file uploads
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

    public function textToSpeech(
        string $model,
        string $text,
        string $voice,
        ?string $format = null,
        ?float $speed = null,
        array $options = [],
    ): AudioResponse {
        $response = $this->http->post('/audio/speech', [
            'model' => $model,
            'input' => $text,
            'voice' => $voice,
            'response_format' => $format ?? 'mp3',
            'speed' => $speed ?? 1.0,
            ...$options,
        ], raw: true);

        return AudioResponse::fromBinary($response['data'], $format ?? 'mp3');
    }

    public function streamTextToSpeech(
        string $model,
        string $text,
        string $voice,
        array $options = [],
    ): \Generator {
        yield '';
    }

    public function speechToText(
        string $model,
        mixed $audio,
        ?string $language = null,
        ?string $prompt = null,
        array $options = [],
    ): TranscriptionResponse {
        $response = $this->http->multipart('/audio/transcriptions', [
            'model' => $model,
            'file' => $audio,
            'language' => $language,
            'prompt' => $prompt,
            'response_format' => 'verbose_json',
            ...$options,
        ]);

        return TranscriptionResponse::fromArray($response);
    }

    public function translateAudio(
        string $model,
        mixed $audio,
        array $options = [],
    ): TranscriptionResponse {
        $response = $this->http->multipart('/audio/translations', [
            'model' => $model,
            'file' => $audio,
            ...$options,
        ]);

        return TranscriptionResponse::fromArray($response);
    }

    protected function mapRequest(string $method, array $params): array
    {
        return array_filter($params, fn($v) => $v !== null);
    }

    protected function mapResponse(string $method, array $response): array
    {
        return match ($method) {
            'chat.create' => $this->mapChatResponse($response),
            'moderation.create' => $response, // Moderation response is already in correct format
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

    public function embed(string $model, string|array $input): EmbeddingResponse
    {
        $inputs = is_array($input) ? $input : [$input];

        $response = $this->request('embed', '/embeddings', [
            'model' => $model,
            'input' => $inputs,
        ]);

        $embeddings = array_map(
            fn($data) => $data['embedding'],
            $response['data']
        );

        $usage = isset($response['usage']) ? new Usage(
            promptTokens: $response['usage']['prompt_tokens'] ?? 0,
            completionTokens: 0,
            totalTokens: $response['usage']['total_tokens'] ?? 0,
        ) : null;

        return new EmbeddingResponse(
            embeddings: $embeddings,
            model: $response['model'],
            usage: $usage,
        );
    }

    public function similarity(array $embedding1, array $embedding2): float
    {
        return \AnyLLM\Support\VectorMath::cosineSimilarity($embedding1, $embedding2);
    }

    public function moderate(string|array $input, ?string $model = null): ModerationResponse|array
    {
        $inputs = is_array($input) ? $input : [$input];
        $model = $model ?? 'omni-moderation-latest';

        $response = $this->request('moderation.create', '/moderations', [
            'input' => $inputs,
            'model' => $model,
        ]);

        if (is_array($input)) {
            // Multiple inputs - return array of responses
            return array_map(
                fn($result) => ModerationResponse::fromArray($result),
                $response['results'] ?? []
            );
        }

        // Single input - return single response
        return ModerationResponse::fromArray($response);
    }

    public function moderateAsync(string|array $input, ?string $model = null): PromiseInterface
    {
        $inputs = is_array($input) ? $input : [$input];
        $model = $model ?? 'omni-moderation-latest';

        $mappedRequest = $this->mapRequest('moderation.create', [
            'input' => $inputs,
            'model' => $model,
        ]);

        return $this->http->postAsync('/moderations', $mappedRequest)
            ->then(function (array $response) use ($input) {
                if (is_array($input)) {
                    // Multiple inputs - return array of responses
                    return array_map(
                        fn($result) => ModerationResponse::fromArray($result),
                        $response['results'] ?? []
                    );
                }

                // Single input - return single response
                return ModerationResponse::fromArray($response);
            });
    }
}
