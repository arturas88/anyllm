<?php

declare(strict_types=1);

namespace AnyLLM\Enums;

enum ContentType: string
{
    case Text = 'text';
    case Image = 'image';
    case File = 'file';
    case Audio = 'audio';
}
