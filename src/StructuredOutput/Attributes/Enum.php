<?php

declare(strict_types=1);

namespace AnyLLM\StructuredOutput\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Enum
{
    public function __construct(
        public array $values,
    ) {}
}

