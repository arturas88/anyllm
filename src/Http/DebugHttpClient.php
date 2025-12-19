<?php

declare(strict_types=1);

namespace AnyLLM\Http;

use AnyLLM\Contracts\HttpClientInterface;

/**
 * Debug HTTP Client that logs all requests and responses.
 *
 * This is automatically used when you call ->withDebugging() on a provider.
 *
 * @see \AnyLLM\Providers\AbstractProvider::withDebugging()
 */
final class DebugHttpClient implements HttpClientInterface
{
    /** @var callable(string, array<string, mixed>): void|null */
    private $logger;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly bool $enabled = true,
        ?callable $logger = null,
        private readonly bool $truncateBase64 = true,
    ) {
        $this->logger = $logger;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function post(string $endpoint, array $data, bool $raw = false): array
    {
        if ($this->enabled) {
            $this->logRequest('POST', $endpoint, $data);
        }

        $response = $this->client->post($endpoint, $data, $raw);

        if ($this->enabled) {
            $this->logResponse('POST', $endpoint, $response);
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function multipart(string $endpoint, array $data): array
    {
        if ($this->enabled) {
            $this->logRequest('MULTIPART', $endpoint, $data);
        }

        $response = $this->client->multipart($endpoint, $data);

        if ($this->enabled) {
            $this->logResponse('MULTIPART', $endpoint, $response);
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $data
     * @return \Generator<int, array<string, mixed>>
     */
    public function stream(string $endpoint, array $data): \Generator
    {
        if ($this->enabled) {
            $this->logRequest('STREAM', $endpoint, $data);
        }

        foreach ($this->client->stream($endpoint, $data) as $chunk) {
            if ($this->enabled) {
                $this->logChunk($chunk);
            }
            yield $chunk;
        }
    }

    public function postAsync(string $endpoint, array $data, bool $raw = false)
    {
        if ($this->enabled) {
            $this->logRequest('POST_ASYNC', $endpoint, $data);
        }

        $promise = $this->client->postAsync($endpoint, $data, $raw);

        // Note: Response logging for async would need to be done in the promise callback
        return $promise;
    }

    public function multipartAsync(string $endpoint, array $data)
    {
        if ($this->enabled) {
            $this->logRequest('MULTIPART_ASYNC', $endpoint, $data);
        }

        return $this->client->multipartAsync($endpoint, $data);
    }

    /**
     * Get the wrapped HTTP client.
     * Useful for unwrapping when disabling debugging.
     */
    public function getWrappedClient(): HttpClientInterface
    {
        return $this->client;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function logRequest(string $method, string $endpoint, array $data): void
    {
        $processedData = $this->truncateBase64 ? $this->truncateBase64InArray($data) : $data;

        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $method,
            'endpoint' => $endpoint,
            'request' => $processedData,
        ];

        if ($this->logger) {
            ($this->logger)('REQUEST', $logData);
        } else {
            $this->defaultLog('REQUEST', $logData);
        }
    }

    /**
     * @param array<string, mixed> $response
     */
    private function logResponse(string $method, string $endpoint, array $response): void
    {
        $processedResponse = $this->truncateBase64 ? $this->truncateBase64InArray($response) : $response;

        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $method,
            'endpoint' => $endpoint,
            'response' => $processedResponse,
        ];

        if ($this->logger) {
            ($this->logger)('RESPONSE', $logData);
        } else {
            $this->defaultLog('RESPONSE', $logData);
        }
    }

    /**
     * @param array<string, mixed> $chunk
     */
    private function logChunk(array $chunk): void
    {
        $processedChunk = $this->truncateBase64 ? $this->truncateBase64InArray($chunk) : $chunk;

        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'chunk' => $processedChunk,
        ];

        if ($this->logger) {
            ($this->logger)('CHUNK', $logData);
        } else {
            $this->defaultLog('CHUNK', $logData);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function defaultLog(string $type, array $data): void
    {
        echo "\n";
        echo "═══════════════════════════════════════════════════════════\n";
        echo "  {$type}\n";
        echo "═══════════════════════════════════════════════════════════\n";
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        echo "\n";
        echo "═══════════════════════════════════════════════════════════\n";
        echo "\n";
    }

    /**
     * Recursively process array to truncate base64-encoded content.
     * Detects base64 in:
     * - Data URIs: "data:image/jpeg;base64,{base64_string}"
     * - Plain base64 strings (long strings that are valid base64)
     *
     * @param array<string, mixed>|mixed $data
     * @return array<string, mixed>|mixed
     */
    private function truncateBase64InArray(mixed $data): mixed
    {
        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                $result[$key] = $this->truncateBase64InArray($value);
            }
            return $result;
        }

        if (is_string($data)) {
            return $this->truncateBase64String($data);
        }

        return $data;
    }

    /**
     * Truncate base64 content in a string.
     * Handles both data URIs and plain base64 strings.
     */
    private function truncateBase64String(string $value): string
    {
        // Check for data URI format: "data:image/jpeg;base64,{base64_string}"
        if (preg_match('/^data:([^;]+);base64,(.+)$/i', $value, $matches)) {
            $mimeType = $matches[1];
            $base64Data = $matches[2];

            // If base64 data is long, truncate it
            if (strlen($base64Data) > 100) {
                $preview = substr($base64Data, 0, 50);
                return "data:{$mimeType};base64,{$preview}... [truncated, length: " . strlen($base64Data) . " bytes]";
            }

            return $value;
        }

        // Check if it's a plain base64 string (long enough and valid base64)
        // Base64 strings are typically long and contain only A-Z, a-z, 0-9, +, /, and = padding
        if (strlen($value) > 200 && preg_match('/^[A-Za-z0-9+\/]+=*$/', $value)) {
            // Verify it's likely base64 by checking if it decodes properly
            $decoded = @base64_decode($value, true);
            if ($decoded !== false && strlen($decoded) > 100) {
                $preview = substr($value, 0, 50);
                return "{$preview}... [truncated base64, length: " . strlen($value) . " bytes]";
            }
        }

        return $value;
    }
}
