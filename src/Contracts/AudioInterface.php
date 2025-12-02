<?php

declare(strict_types=1);

namespace AnyLLM\Contracts;

use AnyLLM\Responses\AudioResponse;
use AnyLLM\Responses\TranscriptionResponse;

interface AudioInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function textToSpeech(
        string $model,
        string $text,
        string $voice,
        ?string $format = null,
        ?float $speed = null,
        array $options = [],
    ): AudioResponse;

    /**
     * @param array<string, mixed> $options
     * @return \Generator<int, string> Audio chunks
     */
    public function streamTextToSpeech(
        string $model,
        string $text,
        string $voice,
        array $options = [],
    ): \Generator;

    /**
     * @param resource|string $audio Audio file path or resource
     * @param array<string, mixed> $options
     */
    public function speechToText(
        string $model,
        mixed $audio,
        ?string $language = null,
        ?string $prompt = null,
        array $options = [],
    ): TranscriptionResponse;

    /**
     * @param array<string, mixed> $options
     */
    public function translateAudio(
        string $model,
        mixed $audio,
        array $options = [],
    ): TranscriptionResponse;
}
