<?php

declare(strict_types=1);

namespace AnyLLM\Messages\Content;

final readonly class TextContent implements Content
{
    private function __construct(
        public string $text,
    ) {}

    public static function create(string $text): self
    {
        return new self($text);
    }

    /**
     * @return array<string, string>
     */
    public function toOpenAIFormat(): array
    {
        return [
            'type' => 'text',
            'text' => $this->text,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function toAnthropicFormat(): array
    {
        return [
            'type' => 'text',
            'text' => $this->text,
        ];
    }
}
