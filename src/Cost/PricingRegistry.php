<?php

declare(strict_types=1);

namespace AnyLLM\Cost;

final class PricingRegistry
{
    /** @var array<string, array<string, ModelPricing>> */
    private static array $pricing = [];

    public static function init(): void
    {
        if (! empty(self::$pricing)) {
            return;
        }

        // OpenAI pricing
        self::$pricing['openai'] = [
            'gpt-5.1' => new ModelPricing(
                inputPer1M: 2.50,
                outputPer1M: 10.00,
            ),
            'gpt-5.1-mini' => new ModelPricing(
                inputPer1M: 0.40,
                outputPer1M: 1.60,
            ),
            'gpt-5.1-nano' => new ModelPricing(
                inputPer1M: 0.10,
                outputPer1M: 0.40,
            ),
            'gpt-4o' => new ModelPricing(
                inputPer1M: 2.50,
                outputPer1M: 10.00,
            ),
            'gpt-4o-mini' => new ModelPricing(
                inputPer1M: 0.15,
                outputPer1M: 0.60,
            ),
        ];

        // Anthropic pricing
        self::$pricing['anthropic'] = [
            'claude-opus-4-5' => new ModelPricing(
                inputPer1M: 15.00,
                outputPer1M: 75.00,
            ),
            'claude-sonnet-4-5' => new ModelPricing(
                inputPer1M: 3.00,
                outputPer1M: 15.00,
            ),
            'claude-haiku-4-5' => new ModelPricing(
                inputPer1M: 0.80,
                outputPer1M: 4.00,
            ),
        ];

        // Google pricing
        self::$pricing['google'] = [
            'gemini-2.5-flash' => new ModelPricing(
                inputPer1M: 0.075,
                outputPer1M: 0.30,
            ),
            'gemini-3-pro-preview' => new ModelPricing(
                inputPer1M: 1.25,
                outputPer1M: 5.00,
            ),
        ];

        // Mistral pricing
        self::$pricing['mistral'] = [
            'mistral-large-latest' => new ModelPricing(
                inputPer1M: 3.00,
                outputPer1M: 9.00,
            ),
            'mistral-medium-latest' => new ModelPricing(
                inputPer1M: 2.70,
                outputPer1M: 8.10,
            ),
            'mistral-small-latest' => new ModelPricing(
                inputPer1M: 1.00,
                outputPer1M: 3.00,
            ),
            'pixtral-12b-2409' => new ModelPricing(
                inputPer1M: 0.15,
                outputPer1M: 0.15,
            ),
        ];

        // xAI pricing
        self::$pricing['xai'] = [
            'grok-beta' => new ModelPricing(
                inputPer1M: 5.00,
                outputPer1M: 15.00,
            ),
            'grok-vision-beta' => new ModelPricing(
                inputPer1M: 5.00,
                outputPer1M: 15.00,
            ),
        ];

        // Ollama is free (local)
        self::$pricing['ollama'] = [];
        
        // OpenRouter varies by model - use their API for pricing
        self::$pricing['openrouter'] = [];
    }

    public static function get(string $provider, string $model): ?ModelPricing
    {
        self::init();
        return self::$pricing[$provider][$model] ?? null;
    }

    public static function register(string $provider, string $model, ModelPricing $pricing): void
    {
        self::init();
        self::$pricing[$provider][$model] = $pricing;
    }

    public static function calculateCost(
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        int $cachedTokens = 0,
    ): ?float {
        $pricing = self::get($provider, $model);

        if ($pricing === null) {
            return null;
        }

        return $pricing->calculateCost($inputTokens, $outputTokens, $cachedTokens);
    }
}

