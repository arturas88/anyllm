<?php

declare(strict_types=1);

namespace AnyLLM\Exceptions;

class RateLimitException extends ProviderException
{
    public function __construct(
        string $message = 'Rate limit exceeded',
        public readonly ?int $retryAfter = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
