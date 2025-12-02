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
                return new AgentResult(
                    content: $response->content,
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

                $startTime = microtime(true);
                $result = $tool->execute($toolCall->arguments);
                $duration = microtime(true) - $startTime;

                $toolExecutions[] = new ToolExecution(
                    name: $toolCall->name,
                    arguments: $toolCall->arguments,
                    result: $result,
                    duration: $duration,
                );

                $messages[] = new ToolMessage(
                    content: is_string($result) ? $result : json_encode($result),
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
