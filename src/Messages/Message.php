<?php

declare(strict_types=1);

namespace AnyLLM\Messages;

use AnyLLM\Enums\Role;
use AnyLLM\Messages\Content\Content;

abstract class Message implements \JsonSerializable
{
    /**
     * @param array<Content|string>|string $content
     */
    protected function __construct(
        public readonly Role $role,
        public readonly array|string $content,
        public readonly ?string $name = null,
    ) {}

    public static function create(string $content): static
    {
        /** @phpstan-ignore-next-line */
        return new static(
            role: static::getRole(),
            content: $content,
        );
    }

    /**
     * @param array<Content|string> $content
     */
    public static function withContent(array $content): static
    {
        /** @phpstan-ignore-next-line */
        return new static(
            role: static::getRole(),
            content: $content,
        );
    }

    abstract protected static function getRole(): Role;

    /**
     * Get the message content.
     * @return array<Content|string>|string
     */
    public function getContent(): array|string
    {
        return $this->content;
    }

    public function toProviderFormat(string $provider): array
    {
        return match ($provider) {
            'openai', 'openrouter', 'xai', 'mistral', 'ollama' => $this->toOpenAIFormat(),
            'anthropic' => $this->toAnthropicFormat(),
            'google' => $this->toGoogleFormat(),
            default => $this->toOpenAIFormat(),
        };
    }

    protected function toOpenAIFormat(): array
    {
        $formatted = ['role' => $this->role->value];

        if (is_string($this->content)) {
            $formatted['content'] = $this->content;
        } else {
            $formatted['content'] = array_map(
                fn($part) => $part instanceof Content
                    ? $part->toOpenAIFormat()
                    : ['type' => 'text', 'text' => $part],
                $this->content
            );
        }

        if ($this->name !== null) {
            $formatted['name'] = $this->name;
        }

        return $formatted;
    }

    protected function toAnthropicFormat(): array
    {
        $formatted = ['role' => $this->mapRoleForAnthropic()];

        if (is_string($this->content)) {
            $formatted['content'] = $this->content;
        } else {
            $formatted['content'] = array_map(
                fn($part) => $part instanceof Content
                    ? $part->toAnthropicFormat()
                    : ['type' => 'text', 'text' => $part],
                $this->content
            );
        }

        return $formatted;
    }

    protected function toGoogleFormat(): array
    {
        // Google Gemini format
        return $this->toOpenAIFormat();
    }

    private function mapRoleForAnthropic(): string
    {
        return match ($this->role) {
            Role::System => throw new \InvalidArgumentException('System messages should be extracted for Anthropic'),
            Role::Assistant => 'assistant',
            default => 'user',
        };
    }

    public function jsonSerialize(): mixed
    {
        return [
            'role' => $this->role->value,
            'content' => $this->content,
            'name' => $this->name,
        ];
    }
}
