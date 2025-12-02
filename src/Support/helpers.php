<?php

declare(strict_types=1);

if (! function_exists('array_filter_null')) {
    /**
     * @param array<int|string, mixed> $array
     * @return array<int|string, mixed>
     */
    function array_filter_null(array $array): array
    {
        return array_filter($array, fn($value) => $value !== null);
    }
}
