<?php

declare(strict_types=1);

namespace AnyLLM\Messages;

use AnyLLM\Enums\Role;
use AnyLLM\Messages\Content\Content;
use AnyLLM\Messages\Content\ImageContent;
use AnyLLM\Messages\Content\FileContent;
use AnyLLM\Messages\Content\TextContent;

final class UserMessage extends Message
{
    public function __construct(
        array|string $content,
        ?string $name = null,
    ) {
        parent::__construct(Role::User, $content, $name);
    }

    protected static function getRole(): Role
    {
        return Role::User;
    }

    public static function create(string $content): static
    {
        return new static($content);
    }

    /**
     * @param array<Content|string> $content
     */
    public static function withContent(array $content): static
    {
        return new static($content);
    }

    public static function withImage(
        string $text,
        string|ImageContent $image,
    ): static {
        $imageContent = is_string($image)
            ? ImageContent::fromUrl($image)
            : $image;

        return static::withContent([
            TextContent::create($text),
            $imageContent,
        ]);
    }

    /**
     * @param array<string|FileContent> $files
     */
    public static function withFiles(
        string $text,
        array $files,
    ): static {
        $content = [TextContent::create($text)];

        foreach ($files as $file) {
            $content[] = is_string($file)
                ? FileContent::fromPath($file)
                : $file;
        }

        return static::withContent($content);
    }
}
