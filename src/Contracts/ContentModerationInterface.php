<?php

declare(strict_types=1);

namespace AnyLLM\Contracts;

use AnyLLM\Moderation\ModerationResponse;
use GuzzleHttp\Promise\PromiseInterface;

/**
 * Interface for providers that support content moderation.
 */
interface ContentModerationInterface
{
    /**
     * Moderate content synchronously.
     * 
     * @param string|array<string> $input The content to moderate (string or array of strings)
     * @param string|null $model Optional model to use for moderation
     * @return ModerationResponse|array<ModerationResponse> Single response or array of responses
     */
    public function moderate(string|array $input, ?string $model = null): ModerationResponse|array;

    /**
     * Moderate content asynchronously.
     * 
     * @param string|array<string> $input The content to moderate
     * @param string|null $model Optional model to use for moderation
     * @return PromiseInterface<ModerationResponse|array<ModerationResponse>>
     */
    public function moderateAsync(string|array $input, ?string $model = null): PromiseInterface;
}

