<?php

declare(strict_types=1);

namespace AnyLLM\Http;

use AnyLLM\Contracts\HttpClientInterface;
use AnyLLM\Exceptions\AuthenticationException;
use AnyLLM\Exceptions\InvalidRequestException;
use AnyLLM\Exceptions\ProviderException;
use AnyLLM\Exceptions\RateLimitException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class HttpClient implements HttpClientInterface
{
    public function __construct(
        private readonly ?ClientInterface $client = null,
        private readonly string $baseUri = '',
        private readonly array $headers = [],
        private readonly ?RequestFactoryInterface $requestFactory = null,
        private readonly ?StreamFactoryInterface $streamFactory = null,
    ) {}

    public function post(string $endpoint, array $data, bool $raw = false): array
    {
        if ($this->client === null) {
            throw new \RuntimeException('HTTP client not configured. Install guzzlehttp/guzzle or provide a PSR-18 client.');
        }

        $url = $this->buildUrl($endpoint);
        $body = json_encode($data);

        $request = $this->requestFactory?->createRequest('POST', $url)
            ?? new \GuzzleHttp\Psr7\Request('POST', $url);

        foreach ($this->headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $request = $request->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory?->createStream($body) ?? \GuzzleHttp\Psr7\Utils::streamFor($body));

        try {
            $response = $this->client->sendRequest($request);
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 400) {
                $this->handleError($statusCode, (string) $response->getBody());
            }

            if ($raw) {
                return ['data' => (string) $response->getBody()];
            }

            return json_decode((string) $response->getBody(), true) ?? [];
        } catch (\Throwable $e) {
            if ($e instanceof ProviderException) {
                throw $e;
            }
            throw new ProviderException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function multipart(string $endpoint, array $data): array
    {
        // Simplified multipart implementation
        // In production, this would properly handle file uploads
        return $this->post($endpoint, $data);
    }

    public function stream(string $endpoint, array $data): \Generator
    {
        if ($this->client === null) {
            throw new \RuntimeException('HTTP client not configured.');
        }

        $url = $this->buildUrl($endpoint);
        $body = json_encode($data);

        $request = $this->requestFactory?->createRequest('POST', $url)
            ?? new \GuzzleHttp\Psr7\Request('POST', $url);

        foreach ($this->headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $request = $request->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'text/event-stream')
            ->withBody($this->streamFactory?->createStream($body) ?? \GuzzleHttp\Psr7\Utils::streamFor($body));

        try {
            $response = $this->client->sendRequest($request);
            $stream = $response->getBody();

            $buffer = '';
            while (! $stream->eof()) {
                $chunk = $stream->read(1024);
                $buffer .= $chunk;

                // Process SSE events
                while (($pos = strpos($buffer, "\n\n")) !== false) {
                    $event = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 2);

                    if (strpos($event, 'data: ') === 0) {
                        $data = substr($event, 6);
                        if ($data === '[DONE]') {
                            return;
                        }

                        $decoded = json_decode($data, true);
                        if ($decoded !== null) {
                            yield $decoded;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            throw new ProviderException($e->getMessage(), $e->getCode(), $e);
        }
    }

    private function buildUrl(string $endpoint): string
    {
        return rtrim($this->baseUri, '/') . '/' . ltrim($endpoint, '/');
    }

    private function handleError(int $statusCode, string $body): void
    {
        $error = json_decode($body, true);
        $message = $error['error']['message'] ?? $error['message'] ?? 'Unknown error';

        match (true) {
            $statusCode === 401 => throw new AuthenticationException($message),
            $statusCode === 429 => throw new RateLimitException($message),
            $statusCode >= 400 && $statusCode < 500 => throw new InvalidRequestException($message, $statusCode),
            default => throw new ProviderException($message, $statusCode),
        };
    }
}
