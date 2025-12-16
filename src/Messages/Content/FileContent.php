<?php

declare(strict_types=1);

namespace AnyLLM\Messages\Content;

final readonly class FileContent implements Content
{
    private function __construct(
        public string $data,
        public string $mediaType,
        public string $filename,
        public bool $isBase64,
    ) {}

    /**
     * Create FileContent from a local file path or remote URL.
     * Supports both local files and remote URLs (HTTP/HTTPS).
     */
    public static function fromPath(string $path): self
    {
        $isUrl = filter_var($path, FILTER_VALIDATE_URL) !== false
            && (str_starts_with($path, 'http://') || str_starts_with($path, 'https://'));

        if ($isUrl) {
            return self::fromUrl($path);
        }

        // Local file
        if (!file_exists($path)) {
            throw new \RuntimeException("File not found: {$path}");
        }

        $fileContents = file_get_contents($path);
        if ($fileContents === false) {
            throw new \RuntimeException("Failed to read file: {$path}");
        }
        $data = base64_encode($fileContents);
        $mediaType = mime_content_type($path) ?: 'application/octet-stream';
        $filename = basename($path);

        return new self(
            data: $data,
            mediaType: $mediaType,
            filename: $filename,
            isBase64: true,
        );
    }

    /**
     * Create FileContent from a remote URL (HTTP/HTTPS).
     * Fetches the file content and encodes it as base64.
     */
    public static function fromUrl(string $url): self
    {
        // Try cURL first if available (more reliable)
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_USERAGENT => 'AnyLLM/1.0',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_MAXREDIRS => 5,
            ]);

            $content = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($content === false || $httpCode >= 400) {
                $errorMsg = $error ?: "HTTP {$httpCode}";
                throw new \RuntimeException("Failed to fetch file from URL '{$url}': {$errorMsg}");
            }

            $data = base64_encode($content);
            $mediaType = self::detectMediaTypeFromContent($content, $url);
            $filename = basename(parse_url($url, PHP_URL_PATH)) ?: 'file';

            return new self(
                data: $data,
                mediaType: $mediaType,
                filename: $filename,
                isBase64: true,
            );
        }

        // Fallback to file_get_contents with stream context
        if (!ini_get('allow_url_fopen')) {
            throw new \RuntimeException(
                "Cannot fetch remote file: 'allow_url_fopen' is disabled. " .
                "Enable it in php.ini or install cURL extension."
            );
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'AnyLLM/1.0',
                'follow_location' => 1,
                'max_redirects' => 5,
            ],
        ]);

        $content = @file_get_contents($url, false, $context);
        if ($content === false) {
            $error = error_get_last();
            $errorMsg = $error['message'] ?? 'Unknown error';
            throw new \RuntimeException("Failed to fetch file from URL '{$url}': {$errorMsg}");
        }

        $data = base64_encode($content);
        $mediaType = self::detectMediaTypeFromContent($content, $url);
        $filename = basename(parse_url($url, PHP_URL_PATH)) ?: 'file';

        return new self(
            data: $data,
            mediaType: $mediaType,
            filename: $filename,
            isBase64: true,
        );
    }

    /**
     * Detect media type from file content or URL extension.
     */
    private static function detectMediaTypeFromContent(string $content, string $url): string
    {
        // Try to detect from content using finfo if available
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = finfo_buffer($finfo, $content);
                finfo_close($finfo);
                if ($detected !== false && $detected !== 'application/octet-stream') {
                    return $detected;
                }
            }
        }

        // Fallback to URL extension
        $path = parse_url($url, PHP_URL_PATH);
        if ($path !== null) {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mimeTypes = [
                'pdf' => 'application/pdf',
                'txt' => 'text/plain',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'csv' => 'text/csv',
                'json' => 'application/json',
                'xml' => 'application/xml',
                'html' => 'text/html',
                'md' => 'text/markdown',
            ];

            if (isset($mimeTypes[$extension])) {
                return $mimeTypes[$extension];
            }
        }

        return 'application/octet-stream';
    }

    public static function fromBase64(string $data, string $mediaType, string $filename): self
    {
        return new self(
            data: $data,
            mediaType: $mediaType,
            filename: $filename,
            isBase64: true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toOpenAIFormat(): array
    {
        // Both OpenAI and OpenRouter support 'file' type with 'file_data'
        // OpenRouter accepts data URIs in file_data: "data:application/pdf;base64,..."
        // OpenAI accepts base64 strings in file_data: "...base64 encoded bytes..."
        // Using data URI format for compatibility with both (OpenRouter prefers it, OpenAI accepts it)
        $dataUrl = "data:{$this->mediaType};base64,{$this->data}";
        
        return [
            'type' => 'file',
            'file' => [
                'filename' => $this->filename,
                'file_data' => $dataUrl,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAnthropicFormat(): array
    {
        // Anthropic supports document content
        return [
            'type' => 'document',
            'source' => [
                'type' => 'base64',
                'media_type' => $this->mediaType,
                'data' => $this->data,
            ],
        ];
    }
}
