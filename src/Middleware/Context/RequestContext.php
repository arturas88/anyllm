<?php

declare(strict_types=1);

namespace AnyLLM\Middleware\Context;

final class RequestContext
{
    public function __construct(
        public string $provider,
        public string $model,
        public string $method,
        /** @var array<string, mixed> */
        public array $params,
        /** @var array<string, mixed> */
        public array $metadata = [],
        public float $startTime = 0.0,
    ) {
        if ($this->startTime === 0.0) {
            $this->startTime = microtime(true);
        }
    }

    /**
     * Add metadata to the request.
     */
    public function withMetadata(string $key, mixed $value): self
    {
        $new = clone $this;
        $new->metadata[$key] = $value;
        return $new;
    }

    /**
     * Update request parameters.
     *
     * @param array<string, mixed> $params
     */
    public function withParams(array $params): self
    {
        $new = clone $this;
        $new->params = $params;
        return $new;
    }

    /**
     * Change the model.
     */
    public function withModel(string $model): self
    {
        $new = clone $this;
        $new->model = $model;
        return $new;
    }

    /**
     * Get elapsed time in milliseconds.
     */
    public function getElapsedMs(): float
    {
        return (microtime(true) - $this->startTime) * 1000;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'model' => $this->model,
            'method' => $this->method,
            'params' => $this->params,
            'metadata' => $this->metadata,
            'start_time' => $this->startTime,
            'elapsed_ms' => $this->getElapsedMs(),
        ];
    }
}
