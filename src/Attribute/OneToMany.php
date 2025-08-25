<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Attribute;

use Attribute;

/**
 * OneToMany association attribute
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class OneToMany
{
    public function __construct(
        public readonly string $targetEntity,
        public readonly string $mappedBy,
        public readonly bool $lazy = true,
        public readonly array $cascade = []
    ) {
    }
}
