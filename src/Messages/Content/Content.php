<?php

declare(strict_types=1);

namespace AnyLLM\Messages\Content;

interface Content
{
    /**
     * @return array<string, mixed>
     */
    public function toOpenAIFormat(): array;

    /**
     * @return array<string, mixed>
     */
    public function toAnthropicFormat(): array;
}
