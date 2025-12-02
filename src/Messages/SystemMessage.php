<?php

declare(strict_types=1);

namespace AnyLLM\Messages;

use AnyLLM\Enums\Role;
use AnyLLM\Messages\Content\Content;

final class SystemMessage extends Message
{
    public function __construct(
        array|string $content,
        ?string $name = null,
    ) {
        parent::__construct(Role::System, $content, $name);
    }

    protected static function getRole(): Role
    {
        return Role::System;
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
}
