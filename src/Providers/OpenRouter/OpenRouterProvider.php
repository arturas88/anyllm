<?php

declare(strict_types=1);

namespace AnyLLM\Providers\OpenRouter;

use AnyLLM\Messages\Message;
use AnyLLM\Messages\UserMessage;
use AnyLLM\Providers\AbstractProvider;
use AnyLLM\Responses\ChatResponse;
use AnyLLM\Responses\ImageResponse;
use AnyLLM\Responses\StructuredResponse;
use AnyLLM\Responses\TextResponse;
use AnyLLM\StructuredOutput\Schema;
use AnyLLM\Tools\Tool;

/**
 * OpenRouter Provider - Access to 100+ models through a unified interface
 *
 * OpenRouter normalizes the schema across models and providers using an
 * OpenAI-compatible API with additional routing features.
 *
 * @see https://openrouter.ai/docs/api/reference/overview
 */
final class OpenRouterProvider extends AbstractProvider
{
    protected const CAPABILITIES = [
        'chat',
        'streaming',
        'tools',
        'structured_output',
        'image_generation',
        'image_input',
        'vision',
        'model_routing',
        'provider_routing',
    ];

    public function name(): string
    {
        return 'openrouter';
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, self::CAPABILITIES, true);
    }

    protected function getBaseUri(): string
    {
        return $this->config->baseUri ?? 'https://openrouter.ai/api/v1';
    }

    /**
     * @return array<string, string>
     */
    protected function getDefaultHeaders(): array
    {
        $headers = [
            'Authorization' => "Bearer {$this->config->apiKey}",
            'Content-Type' => 'application/json',
        ];

        // Optional: Identify your app on openrouter.ai
        if (isset($this->config->options['site_url'])) {
            $siteUrl = $this->config->options['site_url'];
            $headers['HTTP-Referer'] = is_string($siteUrl) ? $siteUrl : (is_scalar($siteUrl) ? (string) $siteUrl : '');
        }

        if (isset($this->config->options['app_name'])) {
            $appName = $this->config->options['app_name'];
            $headers['X-Title'] = is_string($appName) ? $appName : (is_scalar($appName) ? (string) $appName : '');
        }

        $merged = array_merge($headers, $this->config->headers);
        return array_map(fn($v) => (string) $v, $merged);
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
            messages: [UserMessage::create($prompt)],
            temperature: $temperature,
            maxTokens: $maxTokens,
            options: array_merge(['stop' => $stop], $options),
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
            messages: [UserMessage::create($prompt)],
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
        $requestData = [
            'model' => $model,
            'messages' => $this->formatMessages($messages),
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'tools' => $tools ? $this->formatTools($tools) : null,
            'tool_choice' => $toolChoice,
        ];

        // OpenRouter-specific parameters
        if (isset($options['transforms'])) {
            $requestData['transforms'] = $options['transforms'];
        }

        if (isset($options['models'])) {
            $requestData['models'] = $options['models'];
        }

        if (isset($options['route'])) {
            $requestData['route'] = $options['route'];
        }

        if (isset($options['provider'])) {
            $requestData['provider'] = $options['provider'];
        }

        // Add other supported parameters
        $supportedParams = [
            'stop', 'seed', 'top_p', 'top_k', 'frequency_penalty',
            'presence_penalty', 'repetition_penalty', 'logit_bias',
            'top_logprobs', 'min_p', 'top_a', 'prediction',
        ];

        foreach ($supportedParams as $param) {
            if (isset($options[$param])) {
                $requestData[$param] = $options[$param];
            }
        }

        $response = $this->request('chat.create', '/chat/completions', array_merge(
            $requestData,
            array_diff_key($options, array_flip([
                'transforms', 'models', 'route', 'provider', ...$supportedParams,
            ]))
        ));

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
        $modelUsed = null;
        $generationId = null;

        foreach ($this->stream('chat.create', '/chat/completions', [
            'model' => $model,
            'messages' => $this->formatMessages($messages),
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            ...$options,
        ]) as $chunk) {
            if (! isset($chunk['choices'][0])) {
                continue;
            }

            $choice = $chunk['choices'][0];
            $delta = $choice['delta'] ?? [];

            // Store model info
            if (isset($chunk['model'])) {
                $modelUsed = $chunk['model'];
            }

            if (isset($chunk['id'])) {
                $generationId = $chunk['id'];
            }

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
            'model' => $modelUsed,
            'id' => $generationId,
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
        $schemaInstance = $schema instanceof Schema
            ? $schema
            : Schema::fromClass($schema); // @phpstan-ignore-line

        $jsonSchema = $schemaInstance->toJsonSchema();

        // Automatically enhance prompt with field names if schema is from a class
        $enhancedPrompt = $prompt;
        if (is_string($schema) && class_exists($schema)) {
            $fieldList = $schemaInstance->toFieldList();
            if ($fieldList) {
                $enhancement = "\n\n**IMPORTANT: You MUST use these EXACT field names in your JSON response:**\n\n{$fieldList}\n\n**CRITICAL:** Use the EXACT field names listed above. All dates must be in YYYY-MM-DD format. All amounts must be numbers (not strings). Arrays must be arrays, not strings. If a field is not found, set it to null.";

                if (is_array($prompt) && !empty($prompt)) {
                    // Check if array contains Message objects
                    /** @phpstan-ignore-next-line */
                    $firstItem = array_key_exists(0, $prompt) ? $prompt[0] : null;
                    /** @phpstan-ignore-next-line */
                    $hasMessageObjects = $firstItem instanceof Message;

                    /** @phpstan-ignore-next-line */
                    if ($hasMessageObjects) {
                        // Find the last UserMessage to enhance
                        $lastUserMessage = null;
                        $lastUserIndex = -1;
                        /** @var array<int, Message> $prompt */
                        for ($i = count($prompt) - 1; $i >= 0; $i--) {
                            $item = array_key_exists($i, $prompt) ? $prompt[$i] : null;
                            if ($item instanceof UserMessage) {
                                $lastUserMessage = $item;
                                $lastUserIndex = $i;
                                break;
                            }
                        }

                        if ($lastUserMessage !== null) {
                            // Enhance the last UserMessage's content
                            $content = $lastUserMessage->content;
                            if (is_string($content)) {
                                // Simple string content - create new message with enhanced content
                                $enhancedPrompt = $prompt;
                                $enhancedPrompt[$lastUserIndex] = UserMessage::create($content . $enhancement);
                            } else {
                                // Array content - add enhancement as new UserMessage
                                $enhancedPrompt = $prompt;
                                $enhancedPrompt[] = UserMessage::create($enhancement);
                            }
                        } else {
                            // No UserMessage found - add enhancement as new UserMessage
                            $enhancedPrompt = $prompt;
                            $enhancedPrompt[] = UserMessage::create($enhancement);
                        }
                    } else {
                        // Array of arrays (already formatted) - enhance the last user message
                        /** @var array<int, array<string, mixed>> $prompt */
                        $enhancedPrompt = $prompt;
                        $lastIndex = count($enhancedPrompt) - 1;
                        if ($lastIndex >= 0) {
                            $lastItem = $enhancedPrompt[$lastIndex] ?? null;
                            if (is_array($lastItem) && isset($lastItem['content']) && is_string($lastItem['content'])) {
                                $enhancedPrompt[$lastIndex]['content'] = $lastItem['content'] . $enhancement;
                            } else {
                                // Add as new user message
                                $enhancedPrompt[] = ['role' => 'user', 'content' => $enhancement];
                            }
                        } else {
                            // Add as new user message
                            $enhancedPrompt[] = ['role' => 'user', 'content' => $enhancement];
                        }
                    }
                } elseif (is_array($prompt)) {
                    // Empty array - add enhancement as new user message
                    $enhancedPrompt = $prompt;
                    $enhancedPrompt[] = ['role' => 'user', 'content' => $enhancement];
                } else {
                    // String prompt - append enhancement
                    $enhancedPrompt = $prompt . $enhancement;
                }
            }
        }

        $messages = is_array($enhancedPrompt)
            ? $this->formatMessages($enhancedPrompt)
            : [['role' => 'user', 'content' => $enhancedPrompt]];

        // OpenRouter supports response_format for compatible models
        // For models that support structured output (like Gemini), use json_schema format
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

        // Check for refusal
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
                "OpenRouter returned empty content. Finish reason: {$finishReason}. "
                . "This may indicate: 1) Model doesn't support structured outputs, "
                . "2) Schema validation error, or 3) Model rejected the request. "
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

    /**
     * @param array<string, mixed> $options
     */
    public function generateImage(
        string $model,
        string $prompt,
        ?string $size = null,
        ?int $n = 1,
        ?string $quality = null,
        ?string $style = null,
        array $options = [],
    ): ImageResponse {
        // OpenRouter supports image generation through various models
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

    /**
     * Get generation statistics including native token counts and cost.
     *
     * @see https://openrouter.ai/docs/api-reference/get-a-generation
     */
    /**
     * @return array<string, mixed>
     */
    public function getGenerationStats(string $generationId): array
    {
        return $this->http->post("/generation?id={$generationId}", []);
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

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function mapChatResponse(array $response): array
    {
        $choice = $response['choices'][0] ?? [];
        $message = $choice['message'] ?? [];

        return [
            'id' => $response['id'],
            'content' => $message['content'] ?? '',
            'tool_calls' => $message['tool_calls'] ?? [],
            'model' => $response['model'],
            'usage' => $response['usage'] ?? null,
            'finish_reason' => $choice['finish_reason'] ?? null,
            // OpenRouter-specific: native_finish_reason
            'native_finish_reason' => $choice['native_finish_reason'] ?? null,
        ];
    }

    /**
     * @param array<Message|array<string, mixed>> $messages
     * @return array<int, array<string, mixed>>
     */
    private function formatMessages(array $messages): array
    {
        return array_map(
            fn($message) => $message instanceof Message
                ? $message->toProviderFormat('openai') // OpenRouter uses OpenAI format
                : $message,
            $messages
        );
    }

    /**
     * @param array<Tool|array<string, mixed>> $tools
     * @return array<int, array<string, mixed>>
     */
    private function formatTools(array $tools): array
    {
        return array_map(
            fn($tool) => $tool instanceof Tool
                ? $tool->toProviderFormat('openai') // OpenRouter uses OpenAI format
                : $tool,
            $tools
        );
    }
}
