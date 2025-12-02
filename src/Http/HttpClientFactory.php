<?php

declare(strict_types=1);

namespace AnyLLM\Http;

use Psr\Http\Client\ClientInterface;

final class HttpClientFactory
{
    public static function create(): ?ClientInterface
    {
        // Try to auto-discover PSR-18 client
        if (class_exists(\GuzzleHttp\Client::class)) {
            return new \GuzzleHttp\Client([
                'timeout' => 60,
                'http_errors' => false,
            ]);
        }

        return null;
    }
}
