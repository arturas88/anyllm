<?php

/**
 * Bootstrap file for AnyLLM
 *
 * This file loads environment variables from .env file
 * and should be included at the start of examples and tests.
 */

declare(strict_types=1);

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load .env file if it exists
if (file_exists(__DIR__ . '/.env') && class_exists(\Dotenv\Dotenv::class)) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad(); // Use safeLoad() to avoid throwing exception if file doesn't exist
}

// Ensure $_ENV is populated from getenv() if using phpdotenv
// phpdotenv loads into $_ENV by default, but we also support getenv()
if (function_exists('getenv')) {
    foreach (array_keys($_ENV) as $key) {
        if (getenv($key) === false && isset($_ENV[$key])) {
            putenv("$key={$_ENV[$key]}");
        }
    }
}
