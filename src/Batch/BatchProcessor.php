<?php

declare(strict_types=1);

namespace AnyLLM\Batch;

use AnyLLM\Contracts\ProviderInterface;
use AnyLLM\Messages\Message;
use AnyLLM\Responses\ChatResponse;
use AnyLLM\Responses\TextResponse;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;

/**
 * BatchProcessor handles concurrent batch processing of multiple LLM requests.
 * 
 * This class allows you to process multiple requests in parallel, significantly
 * improving performance when making multiple API calls.
 * 
 * @example
 * ```php
 * $processor = new BatchProcessor($provider);
 * 
 * $promises = [
 *     'prompt1' => $processor->generateText('gpt-4', 'Hello'),
 *     'prompt2' => $processor->generateText('gpt-4', 'World'),
 * ];
 * 
 * $results = $processor->wait($promises);
 * ```
 */
final class BatchProcessor
{
    public function __construct(
        private readonly ProviderInterface $provider,
    ) {}

    /**
     * Create a batch text generation request.
     * 
     * @param string $model The model to use
     * @param string $prompt The prompt text
     * @param float|null $temperature Optional temperature
     * @param int|null $maxTokens Optional max tokens
     * @param array|null $stop Optional stop sequences
     * @param array $options Additional options
     * @return PromiseInterface<TextResponse>
     */
    public function generateText(
        string $model,
        string $prompt,
        ?float $temperature = null,
        ?int $maxTokens = null,
        ?array $stop = null,
        array $options = [],
    ): PromiseInterface {
        return $this->provider->generateTextAsync(
            model: $model,
            prompt: $prompt,
            temperature: $temperature,
            maxTokens: $maxTokens,
            stop: $stop,
            options: $options,
        );
    }

    /**
     * Create a batch chat request.
     * 
     * @param string $model The model to use
     * @param array<Message> $messages The conversation messages
     * @param float|null $temperature Optional temperature
     * @param int|null $maxTokens Optional max tokens
     * @param array|null $tools Optional tools
     * @param string|null $toolChoice Optional tool choice
     * @param array $options Additional options
     * @return PromiseInterface<ChatResponse>
     */
    public function chat(
        string $model,
        array $messages,
        ?float $temperature = null,
        ?int $maxTokens = null,
        ?array $tools = null,
        ?string $toolChoice = null,
        array $options = [],
    ): PromiseInterface {
        return $this->provider->chatAsync(
            model: $model,
            messages: $messages,
            temperature: $temperature,
            maxTokens: $maxTokens,
            tools: $tools,
            toolChoice: $toolChoice,
            options: $options,
        );
    }

    /**
     * Wait for all promises to resolve and return results.
     * 
     * @param array<string, PromiseInterface> $promises Associative array of promises
     * @return array<string, mixed> Associative array of results with same keys
     * @throws \Throwable If any promise is rejected
     */
    public function wait(array $promises): array
    {
        $results = Utils::settle($promises)->wait();

        $resolved = [];
        foreach ($results as $key => $result) {
            if ($result['state'] === 'fulfilled') {
                $resolved[$key] = $result['value'];
            } else {
                throw $result['reason'];
            }
        }

        return $resolved;
    }

    /**
     * Wait for all promises to resolve, returning both fulfilled and rejected results.
     * 
     * @param array<string, PromiseInterface> $promises Associative array of promises
     * @return array<string, array{state: 'fulfilled'|'rejected', value?: mixed, reason?: \Throwable}> Results with state information
     */
    public function settle(array $promises): array
    {
        $results = Utils::settle($promises)->wait();

        $settled = [];
        foreach ($results as $key => $result) {
            $settled[$key] = [
                'state' => $result['state'],
                'value' => $result['value'] ?? null,
                'reason' => $result['reason'] ?? null,
            ];
        }

        return $settled;
    }

    /**
     * Wait for all promises with a timeout.
     * 
     * @param array<string, PromiseInterface> $promises Associative array of promises
     * @param float $timeout Timeout in seconds
     * @return array<string, mixed> Associative array of results
     * @throws \RuntimeException If timeout occurs
     */
    public function waitWithTimeout(array $promises, float $timeout): array
    {
        $startTime = microtime(true);
        $settledPromise = Utils::settle($promises);
        
        // Poll the promise state until it's resolved or timeout
        while (true) {
            $state = $settledPromise->getState();
            
            if ($state !== 'pending') {
                // Promise is resolved, get results
                break;
            }
            
            // Check timeout
            $elapsed = microtime(true) - $startTime;
            if ($elapsed >= $timeout) {
                throw new \RuntimeException("Operation timed out after {$timeout} seconds");
            }
            
            // Small delay to avoid busy waiting
            usleep(50000); // 50ms
        }
        
        $results = $settledPromise->wait();

        $resolved = [];
        foreach ($results as $key => $result) {
            if ($result['state'] === 'fulfilled') {
                $resolved[$key] = $result['value'];
            } else {
                throw $result['reason'];
            }
        }

        return $resolved;
    }

    /**
     * Get the underlying provider.
     */
    public function getProvider(): ProviderInterface
    {
        return $this->provider;
    }
}

