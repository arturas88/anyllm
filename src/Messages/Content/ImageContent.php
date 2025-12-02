<?php

declare(strict_types=1);

namespace AnyLLM\Messages\Content;

final readonly class ImageContent implements Content
{
    private function __construct(
        public string $data,
        public string $mediaType,
        public bool $isBase64,
    ) {}

    public static function fromUrl(string $url): self
    {
        return new self(
            data: $url,
            mediaType: 'image/url',
            isBase64: false,
        );
    }

    public static function fromBase64(string $data, string $mediaType): self
    {
        return new self(
            data: $data,
            mediaType: $mediaType,
            isBase64: true,
        );
    }

    public static function fromPath(string $path): self
    {
        $fileContents = file_get_contents($path);
        if ($fileContents === false) {
            throw new \RuntimeException("Failed to read file: {$path}");
        }
        $data = base64_encode($fileContents);
        $mediaType = mime_content_type($path) ?: 'image/jpeg';

        return new self(
            data: $data,
            mediaType: $mediaType,
            isBase64: true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toOpenAIFormat(): array
    {
        if ($this->isBase64) {
            return [
                'type' => 'image_url',
                'image_url' => [
                    'url' => "data:{$this->mediaType};base64,{$this->data}",
                ],
            ];
        }

        return [
            'type' => 'image_url',
            'image_url' => ['url' => $this->data],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAnthropicFormat(): array
    {
        if (! $this->isBase64) {
            // Anthropic requires base64 for images - fetch and convert
            $fileContents = file_get_contents($this->data);
            if ($fileContents === false) {
                throw new \RuntimeException("Failed to read image from URL: {$this->data}");
            }
            $data = base64_encode($fileContents);
            $mediaType = 'image/jpeg';

            return [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $mediaType,
                    'data' => $data,
                ],
            ];
        }

        return [
            'type' => 'image',
            'source' => [
                'type' => 'base64',
                'media_type' => $this->mediaType,
                'data' => $this->data,
            ],
        ];
    }
}
