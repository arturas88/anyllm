<?php

declare(strict_types=1);

namespace AnyLLM\StructuredOutput\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Enum
{
    /**
     * @param array<int|string, mixed> $values
     */
    public function __construct(
        /** @var array<int|string, mixed> */
        public array $values,
    ) {}
}
