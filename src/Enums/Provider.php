<?php

declare(strict_types=1);

namespace AnyLLM\Enums;

enum Provider: string
{
    case OpenAI = 'openai';
    case Anthropic = 'anthropic';
    case Google = 'google';
    case Mistral = 'mistral';
    case XAI = 'xai';
    case OpenRouter = 'openrouter';
    case Ollama = 'ollama';
}

