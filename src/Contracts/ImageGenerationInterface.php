<?php

declare(strict_types=1);

namespace AnyLLM\Contracts;

use AnyLLM\Responses\ImageResponse;

interface ImageGenerationInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function generateImage(
        string $model,
        string $prompt,
        ?string $size = null,
        ?int $n = 1,
        ?string $quality = null,
        ?string $style = null,
        array $options = [],
    ): ImageResponse;

    /**
     * @param resource|string $image Image file path or resource
     * @param resource|string|null $mask Optional mask for inpainting
     * @param array<string, mixed> $options
     */
    public function editImage(
        string $model,
        mixed $image,
        string $prompt,
        mixed $mask = null,
        array $options = [],
    ): ImageResponse;

    /**
     * @param array<string, mixed> $options
     */
    public function upscaleImage(
        string $model,
        mixed $image,
        ?int $scale = null,
        array $options = [],
    ): ImageResponse;
}
