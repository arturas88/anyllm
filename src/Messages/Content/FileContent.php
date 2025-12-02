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

    public static function fromPath(string $path): self
    {
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
        // OpenAI handles files differently based on provider
        return [
            'type' => 'file',
            'file' => [
                'filename' => $this->filename,
                'data' => "data:{$this->mediaType};base64,{$this->data}",
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
