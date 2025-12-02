<?php

declare(strict_types=1);

namespace AnyLLM\Cost;

final readonly class ModelPricing
{
    public function __construct(
        public float $inputPer1M,
        public float $outputPer1M,
        public ?float $cachedInputPer1M = null,
    ) {}

    public function calculateCost(int $inputTokens, int $outputTokens, int $cachedTokens = 0): float
    {
        $inputCost = ($inputTokens / 1_000_000) * $this->inputPer1M;
        $outputCost = ($outputTokens / 1_000_000) * $this->outputPer1M;

        $cachedCost = 0;
        if ($cachedTokens > 0 && $this->cachedInputPer1M !== null) {
            $cachedCost = ($cachedTokens / 1_000_000) * $this->cachedInputPer1M;
        }

        return round($inputCost + $outputCost + $cachedCost, 6);
    }
}
