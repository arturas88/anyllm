<?php

declare(strict_types=1);

namespace AnyLLM\Testing;

use AnyLLM\Contracts\HttpClientInterface;
use AnyLLM\Contracts\ProviderInterface;
use AnyLLM\Responses\ChatResponse;
use AnyLLM\Responses\StructuredResponse;
use AnyLLM\Responses\TextResponse;
use AnyLLM\StructuredOutput\Schema;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\FulfilledPromise;

final class FakeProvider implements ProviderInterface
{
    /** @var array<int, array{method: string, params: array<string, mixed>}> */
    private array $recorded = [];
    /** @var array<int, mixed> */
    private array $responses = [];
    private int $responseIndex = 0;

    public function name(): string
    {
        return 'fake';
    }

    public function supports(string $capability): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function listModels(): array
    {
        return ['fake-model' => 'fake-model'];
    }

    public function withResponses(mixed ...$responses): self
    {
        $this->responses = $responses;
        return $this;
    }

    public function willReturn(string $text, ?int $inputTokens = null, ?int $outputTokens = null): self
    {
        $completionTokens = $outputTokens ?? (int) (strlen($text) / 4);
        $promptTokens = $inputTokens ?? 10;

        $this->responses[] = TextResponse::fake([
            'text' => $text,
            'usage' => [
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $promptTokens + $completionTokens,
            ],
        ]);

        return $this;
    }

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
    ): TextResponse {
        $this->recorded[] = [
            'method' => 'generateText',
            'params' => compact('model', 'prompt', 'temperature', 'maxTokens', 'stop', 'options'),
        ];

        $response = $this->nextResponse();
        if ($response instanceof TextResponse) {
            return $response;
        }
        return TextResponse::fake(['text' => 'Fake response']);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function streamText(
        string $model,
        string $prompt,
        ?float $temperature = null,
        ?int $maxTokens = null,
        array $options = [],
    ): \Generator {
        $this->recorded[] = [
            'method' => 'streamText',
            'params' => compact('model', 'prompt', 'temperature', 'maxTokens', 'options'),
        ];

        yield 'Fake';
        yield ' streaming';
        yield ' response';

        return TextResponse::fake(['text' => 'Fake streaming response']);
    }

    /**
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
    ): ChatResponse {
        $this->recorded[] = [
            'method' => 'chat',
            'params' => compact('model', 'messages', 'temperature', 'maxTokens', 'tools', 'toolChoice', 'options'),
        ];

        $response = $this->nextResponse();

        // Convert TextResponse to ChatResponse if needed
        if ($response instanceof TextResponse) {
            return ChatResponse::fake([
                'content' => $response->text,
                'id' => $response->id,
                'model' => $response->model,
                'usage' => $response->usage?->toArray(),
            ]);
        }

        if ($response instanceof ChatResponse) {
            return $response;
        }

        return ChatResponse::fake(['content' => 'Fake chat response']);
    }

    /**
     * @param array<string>|null $stop
     * @param array<string, mixed> $options
     */
    public function generateTextAsync(
        string $model,
        string $prompt,
        ?float $temperature = null,
        ?int $maxTokens = null,
        ?array $stop = null,
        array $options = [],
    ): PromiseInterface {
        $this->recorded[] = [
            'method' => 'generateTextAsync',
            'params' => compact('model', 'prompt', 'temperature', 'maxTokens', 'stop', 'options'),
        ];

        $response = $this->nextResponse();
        if (!($response instanceof TextResponse)) {
            $response = TextResponse::fake(['text' => 'Fake async response']);
        }

        return new FulfilledPromise($response);
    }

    /**
     * @param array<string, mixed> $options
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
        $this->recorded[] = [
            'method' => 'chatAsync',
            'params' => compact('model', 'messages', 'temperature', 'maxTokens', 'tools', 'toolChoice', 'options'),
        ];

        $response = $this->nextResponse();

        // Convert TextResponse to ChatResponse if needed
        if ($response instanceof TextResponse) {
            $chatResponse = ChatResponse::fake([
                'content' => $response->text,
                'id' => $response->id,
                'model' => $response->model,
                'usage' => $response->usage?->toArray(),
            ]);
            return new FulfilledPromise($chatResponse);
        }

        if ($response instanceof ChatResponse) {
            return new FulfilledPromise($response);
        }

        return new FulfilledPromise(ChatResponse::fake(['content' => 'Fake async chat response']));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function streamChat(
        string $model,
        array $messages,
        ?float $temperature = null,
        ?int $maxTokens = null,
        array $options = [],
    ): \Generator {
        $this->recorded[] = [
            'method' => 'streamChat',
            'params' => compact('model', 'messages', 'temperature', 'maxTokens', 'options'),
        ];

        yield 'Fake';
        yield ' chat';
        yield ' streaming';

        return ChatResponse::fake(['content' => 'Fake chat streaming']);
    }

    /**
     * @param array<string, mixed>|string $prompt
     * @param array<string, mixed> $options
     */
    public function generateObject(
        string $model,
        string|array $prompt,
        Schema|string $schema,
        ?string $schemaName = null,
        ?string $schemaDescription = null,
        array $options = [],
    ): StructuredResponse {
        $this->recorded[] = [
            'method' => 'generateObject',
            'params' => compact('model', 'prompt', 'schema', 'schemaName', 'schemaDescription', 'options'),
        ];

        $response = $this->nextResponse();
        if ($response instanceof StructuredResponse) {
            return $response;
        }
        return StructuredResponse::fromArray(['object' => []], $schema);
    }

    public function getHttpClient(): HttpClientInterface
    {
        throw new \RuntimeException('FakeProvider does not have an HTTP client');
    }

    public function assertCalled(string $method, ?callable $callback = null): void
    {
        $calls = array_filter(
            $this->recorded,
            fn($call) => $call['method'] === $method
        );

        if (empty($calls)) {
            throw new \RuntimeException(
                "Expected method '{$method}' to be called, but it wasn't."
            );
        }

        if ($callback !== null) {
            foreach ($calls as $call) {
                if ($callback($call['params']) === true) {
                    return;
                }
            }

            throw new \RuntimeException(
                "Method '{$method}' was called but no call matched the assertion."
            );
        }
    }

    public function assertCalledTimes(string $method, int $times): void
    {
        $count = count(array_filter(
            $this->recorded,
            fn($call) => $call['method'] === $method
        ));

        if ($count !== $times) {
            throw new \RuntimeException(
                "Expected method '{$method}' to be called {$times} times, but it was called {$count} times."
            );
        }
    }

    public function assertNothingCalled(): void
    {
        if (! empty($this->recorded)) {
            throw new \RuntimeException(
                "Expected no calls, but " . count($this->recorded) . " call(s) were made."
            );
        }
    }

    /**
     * @return array<int, array{method: string, params: array<string, mixed>}>
     */
    public function getRecordedCalls(): array
    {
        return $this->recorded;
    }

    private function nextResponse(): mixed
    {
        if ($this->responseIndex >= count($this->responses)) {
            return null;
        }

        return $this->responses[$this->responseIndex++];
    }
}
