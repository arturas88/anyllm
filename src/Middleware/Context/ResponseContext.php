<?php

declare(strict_types=1);

namespace AnyLLM\Middleware\Context;

use AnyLLM\Responses\Response;

final class ResponseContext
{
    public function __construct(
        public RequestContext $request,
        public mixed $response,
        public ?string $error = null,
        public array $metadata = [],
    ) {}

    /**
     * Check if the response was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->error === null;
    }

    /**
     * Check if the response failed.
     */
    public function isFailed(): bool
    {
        return $this->error !== null;
    }

    /**
     * Add metadata to the response.
     */
    public function withMetadata(string $key, mixed $value): self
    {
        $new = clone $this;
        $new->metadata[$key] = $value;
        return $new;
    }

    /**
     * Replace the response.
     */
    public function withResponse(mixed $response): self
    {
        $new = clone $this;
        $new->response = $response;
        return $new;
    }

    /**
     * Get duration in milliseconds.
     */
    public function getDurationMs(): float
    {
        return $this->request->getElapsedMs();
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'request' => $this->request->toArray(),
            'response' => $this->response instanceof Response 
                ? get_class($this->response)
                : gettype($this->response),
            'error' => $this->error,
            'metadata' => $this->metadata,
            'duration_ms' => $this->getDurationMs(),
            'successful' => $this->isSuccessful(),
        ];
    }
}

