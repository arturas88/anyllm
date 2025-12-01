<?php

declare(strict_types=1);

namespace AnyLLM\Messages;

use AnyLLM\Enums\Role;

final class SystemMessage extends Message
{
    protected static function getRole(): Role
    {
        return Role::System;
    }
}

