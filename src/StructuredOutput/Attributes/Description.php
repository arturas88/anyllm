<?php

declare(strict_types=1);

namespace AnyLLM\StructuredOutput\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class Description
{
    public function __construct(
        public string $value,
    ) {}
}
