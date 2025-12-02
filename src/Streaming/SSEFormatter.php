<?php

declare(strict_types=1);

namespace AnyLLM\Streaming;

/**
 * Server-Sent Events formatter for streaming responses to web clients.
 */
final class SSEFormatter
{
    /**
     * Format a chunk as SSE.
     */
    public static function formatChunk(string $content, ?string $eventType = null, ?string $id = null): string
    {
        $sse = '';

        if ($id !== null) {
            $sse .= "id: {$id}\n";
        }

        if ($eventType !== null) {
            $sse .= "event: {$eventType}\n";
        }

        // Split content into lines for proper SSE format
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $sse .= "data: {$line}\n";
        }

        $sse .= "\n";

        return $sse;
    }

    /**
     * Format chunk as JSON SSE.
     */
    public static function formatJsonChunk(array $data, ?string $eventType = null, ?string $id = null): string
    {
        return self::formatChunk(json_encode($data), $eventType, $id);
    }

    /**
     * Format a completion event.
     */
    public static function formatComplete(?array $metadata = null): string
    {
        $data = $metadata ?? ['status' => 'complete'];
        return self::formatJsonChunk($data, 'complete');
    }

    /**
     * Format an error event.
     */
    public static function formatError(string $message, ?array $details = null): string
    {
        $data = ['error' => $message];
        if ($details) {
            $data['details'] = $details;
        }
        return self::formatJsonChunk($data, 'error');
    }

    /**
     * Format a progress event.
     */
    public static function formatProgress(array $progress): string
    {
        return self::formatJsonChunk($progress, 'progress');
    }

    /**
     * Send SSE headers (call before sending any data).
     */
    public static function sendHeaders(): void
    {
        if (! headers_sent()) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no'); // Disable nginx buffering
            header('Connection: keep-alive');
        }
    }

    /**
     * Send a keep-alive comment (prevents connection timeout).
     */
    public static function sendKeepAlive(): string
    {
        return ": keep-alive\n\n";
    }

    /**
     * Flush output buffer (important for streaming).
     */
    public static function flush(): void
    {
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}
