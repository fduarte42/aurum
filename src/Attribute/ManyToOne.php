<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Attribute;

use Attribute;

/**
 * ManyToOne association attribute
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class ManyToOne
{
    public function __construct(
        public readonly string $targetEntity,
        public readonly ?string $inversedBy = null,
        public readonly ?string $joinColumn = null,
        public readonly ?string $referencedColumnName = null,
        public readonly bool $nullable = true,
        public readonly bool $lazy = true,
        public readonly array $cascade = []
    ) {
    }
}
