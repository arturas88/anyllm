<?php

declare(strict_types=1);

namespace AnyLLM\Contracts;

interface HttpClientInterface
{
    public function post(string $endpoint, array $data, bool $raw = false): array;

    public function multipart(string $endpoint, array $data): array;

    public function stream(string $endpoint, array $data): \Generator;
}
