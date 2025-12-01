<?php

declare(strict_types=1);

namespace AnyLLM\Messages;

use AnyLLM\Enums\Role;
use AnyLLM\Messages\Content\ImageContent;
use AnyLLM\Messages\Content\FileContent;
use AnyLLM\Messages\Content\TextContent;

final class UserMessage extends Message
{
    protected static function getRole(): Role
    {
        return Role::User;
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

