<?php

declare(strict_types=1);

if (! function_exists('array_filter_null')) {
    function array_filter_null(array $array): array
    {
        return array_filter($array, fn ($value) => $value !== null);
    }
}

