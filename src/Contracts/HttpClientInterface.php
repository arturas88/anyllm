<?php

declare(strict_types=1);

namespace AnyLLM\Contracts;

interface HttpClientInterface
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function post(string $endpoint, array $data, bool $raw = false): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function multipart(string $endpoint, array $data): array;

    /**
     * @param array<string, mixed> $data
     * @return \Generator<int, array<string, mixed>>
     */
    public function stream(string $endpoint, array $data): \Generator;

    /**
     * Post request asynchronously (returns a Promise).
     *
     * @param array<string, mixed> $data
     * @return \GuzzleHttp\Promise\PromiseInterface<array<string, mixed>>
     */
    public function postAsync(string $endpoint, array $data, bool $raw = false);

    /**
     * Multipart request asynchronously (returns a Promise).
     *
     * @param array<string, mixed> $data
     * @return \GuzzleHttp\Promise\PromiseInterface<array<string, mixed>>
     */
    public function multipartAsync(string $endpoint, array $data);
}
