<?php

declare(strict_types=1);

namespace AnyLLM\StructuredOutput\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class ArrayOf
{
    /**
     * @param class-string $class
     */
    public function __construct(
        public string $class,
    ) {}
}
