<?php

declare(strict_types=1);

namespace AnyLLM\Config;

use AnyLLM\Exceptions\ValidationException;

final class ConfigValidator
{
    /**
     * Validate provider configuration.
     *
     * @throws ValidationException
     */
    public static function validateProvider(ProviderConfig $config): void
    {
        $errors = [];

        // Validate API key for providers that require it
        if (! $config->apiKey && ! self::isLocalProvider($config->name)) {
            $errors[] = "API key is required for provider '{$config->name}'";
        }

        // Validate model
        if (! $config->model) {
            $errors[] = "Model is required for provider '{$config->name}'";
        }

        // Validate temperature
        if ($config->temperature !== null) {
            if ($config->temperature < 0 || $config->temperature > 2) {
                $errors[] = "Temperature must be between 0 and 2 (got: {$config->temperature})";
            }
        }

        // Validate maxTokens
        if ($config->maxTokens !== null) {
            if ($config->maxTokens < 1) {
                $errors[] = "Max tokens must be greater than 0 (got: {$config->maxTokens})";
            }
            if ($config->maxTokens > 1000000) {
                $errors[] = "Max tokens exceeds reasonable limit (got: {$config->maxTokens})";
            }
        }

        // Validate topP
        if ($config->topP !== null) {
            if ($config->topP < 0 || $config->topP > 1) {
                $errors[] = "Top P must be between 0 and 1 (got: {$config->topP})";
            }
        }

        // Validate base URI for custom endpoints
        if (isset($config->options['base_uri'])) {
            if (! filter_var($config->options['base_uri'], FILTER_VALIDATE_URL)) {
                $errors[] = "Invalid base URI: {$config->options['base_uri']}";
            }
        }

        if (! empty($errors)) {
            throw new ValidationException(
                "Configuration validation failed:\n- " . implode("\n- ", $errors)
            );
        }
    }

    /**
     * Validate array of configuration values.
     *
     * @throws ValidationException
     */
    public static function validate(array $config, array $rules): void
    {
        $errors = [];

        foreach ($rules as $key => $rule) {
            $value = $config[$key] ?? null;

            if (is_string($rule)) {
                $rule = self::parseRule($rule);
            }

            $errors = array_merge($errors, self::validateField($key, $value, $rule));
        }

        if (! empty($errors)) {
            throw new ValidationException(
                "Validation failed:\n- " . implode("\n- ", $errors)
            );
        }
    }

    /**
     * Validate a single field against rules.
     */
    private static function validateField(string $key, mixed $value, array $rules): array
    {
        $errors = [];

        foreach ($rules as $rule => $ruleValue) {
            switch ($rule) {
                case 'required':
                    if ($ruleValue && empty($value)) {
                        $errors[] = "Field '{$key}' is required";
                    }
                    break;

                case 'type':
                    if ($value !== null && gettype($value) !== $ruleValue) {
                        $errors[] = "Field '{$key}' must be of type {$ruleValue}";
                    }
                    break;

                case 'min':
                    if (is_numeric($value) && $value < $ruleValue) {
                        $errors[] = "Field '{$key}' must be at least {$ruleValue}";
                    }
                    break;

                case 'max':
                    if (is_numeric($value) && $value > $ruleValue) {
                        $errors[] = "Field '{$key}' must not exceed {$ruleValue}";
                    }
                    break;

                case 'in':
                    if ($value !== null && ! in_array($value, $ruleValue, true)) {
                        $allowed = implode(', ', $ruleValue);
                        $errors[] = "Field '{$key}' must be one of: {$allowed}";
                    }
                    break;

                case 'url':
                    if ($ruleValue && $value !== null && ! filter_var($value, FILTER_VALIDATE_URL)) {
                        $errors[] = "Field '{$key}' must be a valid URL";
                    }
                    break;

                case 'email':
                    if ($ruleValue && $value !== null && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "Field '{$key}' must be a valid email";
                    }
                    break;

                case 'regex':
                    if ($value !== null && ! preg_match($ruleValue, $value)) {
                        $errors[] = "Field '{$key}' format is invalid";
                    }
                    break;

                case 'callback':
                    if (is_callable($ruleValue)) {
                        $result = $ruleValue($value);
                        if ($result !== true && is_string($result)) {
                            $errors[] = $result;
                        } elseif ($result === false) {
                            $errors[] = "Field '{$key}' is invalid";
                        }
                    }
                    break;
            }
        }

        return $errors;
    }

    /**
     * Parse a rule string like "required|type:string|min:5".
     */
    private static function parseRule(string $rule): array
    {
        $rules = [];
        $parts = explode('|', $rule);

        foreach ($parts as $part) {
            if (str_contains($part, ':')) {
                [$name, $value] = explode(':', $part, 2);
                $rules[$name] = $value;
            } else {
                $rules[$part] = true;
            }
        }

        return $rules;
    }

    /**
     * Check if a provider runs locally and doesn't need API key.
     */
    private static function isLocalProvider(string $provider): bool
    {
        return in_array(strtolower($provider), ['ollama', 'local'], true);
    }

    /**
     * Quick validation helpers for common cases.
     */
    public static function requireApiKey(string $apiKey, string $provider): void
    {
        if (empty($apiKey)) {
            throw new ValidationException("API key is required for {$provider}");
        }
    }

    public static function requireModel(string $model): void
    {
        if (empty($model)) {
            throw new ValidationException("Model name is required");
        }
    }

    public static function validateTemperature(?float $temperature): void
    {
        if ($temperature !== null && ($temperature < 0 || $temperature > 2)) {
            throw new ValidationException("Temperature must be between 0 and 2");
        }
    }

    public static function validateTokenLimit(?int $maxTokens): void
    {
        if ($maxTokens !== null && $maxTokens < 1) {
            throw new ValidationException("Max tokens must be greater than 0");
        }
    }
}
