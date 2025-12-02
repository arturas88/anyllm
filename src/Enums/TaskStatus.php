<?php

declare(strict_types=1);

namespace AnyLLM\Enums;

enum TaskStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
