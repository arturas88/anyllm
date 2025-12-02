<?php

declare(strict_types=1);

namespace AnyLLM\Agents;

use AnyLLM\Contracts\ProviderInterface;
use AnyLLM\Exceptions\MaxIterationsExceededException;
use AnyLLM\Messages\AssistantMessage;
use AnyLLM\Messages\Message;
use AnyLLM\Messages\SystemMessage;
use AnyLLM\Messages\ToolMessage;
use AnyLLM\Messages\UserMessage;
use AnyLLM\Responses\Parts\Usage;
use AnyLLM\Tools\Tool;

final class Agent
{
    /** @var array<Tool> */
    private array $tools = [];
    private int $maxIterations = 10;
    /** @var callable|null */
    private $beforeToolExecutionCallback = null;
    /** @var callable|null */
    private $afterToolExecutionCallback = null;
    /** @var callable|null */
    private $beforeFinalResponseCallback = null;

    public function __construct(
        private readonly ProviderInterface $provider,
        private readonly string $model,
        private readonly ?string $systemPrompt = null,
    ) {}

    public static function create(
        ProviderInterface $provider,
        string $model,
        ?string $systemPrompt = null,
    ): self {
        return new self($provider, $model, $systemPrompt);
    }

    public function withTools(Tool ...$tools): self
    {
        $clone = clone $this;
        $clone->tools = [...$this->tools, ...$tools];
        return $clone;
    }

    public function withMaxIterations(int $max): self
    {
        $clone = clone $this;
        $clone->maxIterations = $max;
        return $clone;
    }

    /**
     * Set a callback that will be called before tool execution.
     * The callback receives: string $toolName, array $arguments
     * Return true to proceed, false to skip, or throw an exception to abort.
     *
     * @param callable(string, array): bool $callback
     */
    public function withBeforeToolExecution(callable $callback): self
    {
        $clone = clone $this;
        $clone->beforeToolExecutionCallback = $callback;
        return $clone;
    }

    /**
     * Set a callback that will be called after tool execution.
     * The callback receives: ToolExecution $execution
     * Can modify the result or throw an exception to retry.
     *
     * @param callable(ToolExecution): mixed|null $callback
     */
    public function withAfterToolExecution(callable $callback): self
    {
        $clone = clone $this;
        $clone->afterToolExecutionCallback = $callback;
        return $clone;
    }

    /**
     * Set a callback that will be called before returning the final response.
     * The callback receives: string $content, array $messages, array $toolExecutions
     * Return a modified content string, or null to use original.
     *
     * @param callable(string, array<Message>, array<ToolExecution>): string|null $callback
     */
    public function withBeforeFinalResponse(callable $callback): self
    {
        $clone = clone $this;
        $clone->beforeFinalResponseCallback = $callback;
        return $clone;
    }

    public function run(string|Message $input): AgentResult
    {
        $messages = [];

        if ($this->systemPrompt) {
            $messages[] = SystemMessage::create($this->systemPrompt);
        }

        $messages[] = is_string($input) ? UserMessage::create($input) : $input;

        $iterations = 0;
        $toolExecutions = [];
        $totalUsage = null;

        while ($iterations < $this->maxIterations) {
            $response = $this->provider->chat(
                model: $this->model,
                messages: $messages,
                tools: $this->tools ?: null,
                toolChoice: $this->tools ? 'auto' : null,
            );

            $messages[] = AssistantMessage::fromResponse($response);

            // Accumulate usage
            if ($response->usage) {
                if ($totalUsage === null) {
                    $totalUsage = $response->usage;
                } else {
                    $totalUsage = new Usage(
                        promptTokens: $totalUsage->promptTokens + $response->usage->promptTokens,
                        completionTokens: $totalUsage->completionTokens + $response->usage->completionTokens,
                        totalTokens: $totalUsage->totalTokens + $response->usage->totalTokens,
                    );
                }
            }

            if (! $response->hasToolCalls()) {
                // No more tool calls, we're done
                $finalContent = $response->content;

                // Human in the loop: allow review/modification before final response
                if ($this->beforeFinalResponseCallback !== null) {
                    $modifiedContent = ($this->beforeFinalResponseCallback)(
                        $finalContent,
                        $messages,
                        $toolExecutions
                    );
                    if ($modifiedContent !== null) {
                        $finalContent = $modifiedContent;
                    }
                }

                return new AgentResult(
                    content: $finalContent,
                    messages: $messages,
                    toolExecutions: $toolExecutions,
                    iterations: $iterations + 1,
                    usage: $totalUsage,
                );
            }

            // Execute tool calls
            foreach ($response->toolCalls as $toolCall) {
                $tool = $this->findTool($toolCall->name);

                if ($tool === null) {
                    throw new \RuntimeException("Unknown tool: {$toolCall->name}");
                }

                // Human in the loop: request approval before tool execution
                if ($this->beforeToolExecutionCallback !== null) {
                    $shouldProceed = ($this->beforeToolExecutionCallback)($toolCall->name, $toolCall->arguments);
                    if ($shouldProceed === false) {
                        // Skip this tool execution
                        $messages[] = new ToolMessage(
                            content: json_encode(['skipped' => true, 'reason' => 'Human approval denied']),
                            toolCallId: $toolCall->id,
                        );
                        continue;
                    }
                }

                $startTime = microtime(true);
                $result = $tool->execute($toolCall->arguments);
                $duration = microtime(true) - $startTime;

                $toolExecution = new ToolExecution(
                    name: $toolCall->name,
                    arguments: $toolCall->arguments,
                    result: $result,
                    duration: $duration,
                );

                // Human in the loop: allow review/modification after tool execution
                if ($this->afterToolExecutionCallback !== null) {
                    $modifiedResult = ($this->afterToolExecutionCallback)($toolExecution);
                    if ($modifiedResult !== null) {
                        $toolExecution = new ToolExecution(
                            name: $toolExecution->name,
                            arguments: $toolExecution->arguments,
                            result: $modifiedResult,
                            duration: $toolExecution->duration,
                        );
                    }
                }

                $toolExecutions[] = $toolExecution;

                $messages[] = new ToolMessage(
                    content: is_string($toolExecution->result) ? $toolExecution->result : json_encode($toolExecution->result),
                    toolCallId: $toolCall->id,
                );
            }

            $iterations++;
        }

        throw new MaxIterationsExceededException(
            "Agent exceeded maximum iterations ({$this->maxIterations})"
        );
    }

    private function findTool(string $name): ?Tool
    {
        foreach ($this->tools as $tool) {
            if ($tool->name === $name) {
                return $tool;
            }
        }

        return null;
    }
}
