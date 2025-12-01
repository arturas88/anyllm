<?php

declare(strict_types=1);

namespace AnyLLM\Messages\Content;

interface Content
{
    public function toOpenAIFormat(): array;

    public function toAnthropicFormat(): array;
}

