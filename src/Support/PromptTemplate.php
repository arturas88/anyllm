<?php

declare(strict_types=1);

namespace AnyLLM\Support;

use AnyLLM\Exceptions\ValidationException;

final class PromptTemplate
{
    private array $variables = [];

    public function __construct(
        private string $template,
    ) {}

    /**
     * Create a new prompt template.
     */
    public static function make(string $template): self
    {
        return new self($template);
    }

    /**
     * Set a variable value.
     */
    public function with(string $key, mixed $value): self
    {
        $this->variables[$key] = $value;
        return $this;
    }

    /**
     * Set multiple variables at once.
     */
    public function withMany(array $variables): self
    {
        $this->variables = array_merge($this->variables, $variables);
        return $this;
    }

    /**
     * Render the template with current variables.
     *
     * @throws ValidationException
     */
    public function render(): string
    {
        $output = $this->template;
        $missing = [];

        // Find all {{variable}} placeholders
        preg_match_all('/\{\{([a-zA-Z0-9_]+)\}\}/', $output, $matches);

        foreach ($matches[1] as $variable) {
            if (! array_key_exists($variable, $this->variables)) {
                $missing[] = $variable;
                continue;
            }

            $value = $this->variables[$variable];
            $output = str_replace(
                '{{' . $variable . '}}',
                $this->formatValue($value),
                $output
            );
        }

        if (! empty($missing)) {
            throw new ValidationException(
                'Missing required template variables: ' . implode(', ', $missing)
            );
        }

        return $output;
    }

    /**
     * Render the template, returning null if variables are missing.
     */
    public function tryRender(): ?string
    {
        try {
            return $this->render();
        } catch (ValidationException) {
            return null;
        }
    }

    /**
     * Get all required variables (placeholders) in the template.
     */
    public function getRequiredVariables(): array
    {
        preg_match_all('/\{\{([a-zA-Z0-9_]+)\}\}/', $this->template, $matches);
        return array_unique($matches[1]);
    }

    /**
     * Check if all required variables are set.
     */
    public function isComplete(): bool
    {
        $required = $this->getRequiredVariables();
        foreach ($required as $var) {
            if (! array_key_exists($var, $this->variables)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Format a value for insertion into template.
     */
    private function formatValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_numeric($value) || is_bool($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT);
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }
            return json_encode($value, JSON_PRETTY_PRINT);
        }

        return '';
    }

    /**
     * Load a template from a file.
     */
    public static function fromFile(string $path): self
    {
        if (! file_exists($path)) {
            throw new ValidationException("Template file not found: {$path}");
        }

        $content = file_get_contents($path);
        return new self($content);
    }

    /**
     * Common template helpers for typical scenarios.
     */
    public static function classification(array $categories): self
    {
        $categoriesList = implode(', ', $categories);

        return self::make(
            "Classify the following text into one of these categories: {$categoriesList}\n\n"
            . "Text: {{text}}\n\n"
            . "Category:"
        );
    }

    public static function summarization(int $maxWords = 100): self
    {
        return self::make(
            "Summarize the following text in no more than {$maxWords} words:\n\n"
            . "{{text}}\n\n"
            . "Summary:"
        );
    }

    public static function translation(string $targetLanguage): self
    {
        return self::make(
            "Translate the following text to {$targetLanguage}:\n\n"
            . "{{text}}\n\n"
            . "Translation:"
        );
    }

    public static function extraction(): self
    {
        return self::make(
            "Extract {{entity}} from the following text:\n\n"
            . "{{text}}\n\n"
            . "Extracted {{entity}}:"
        );
    }

    public static function questionAnswer(): self
    {
        return self::make(
            "Context: {{context}}\n\n"
            . "Question: {{question}}\n\n"
            . "Answer based on the context above:"
        );
    }

    public static function sentiment(): self
    {
        return self::make(
            "Analyze the sentiment of the following text and classify it as positive, negative, or neutral:\n\n"
            . "{{text}}\n\n"
            . "Sentiment:"
        );
    }

    public static function codeReview(): self
    {
        return self::make(
            "Review the following {{language}} code and provide feedback:\n\n"
            . "```{{language}}\n"
            . "{{code}}\n"
            . "```\n\n"
            . "Review:"
        );
    }

    public static function brainstorming(): self
    {
        return self::make(
            "Generate {{count}} creative ideas for:\n\n"
            . "{{topic}}\n\n"
            . "Ideas:"
        );
    }
}
