<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Attribute;

use Attribute;

/**
 * Defines the discriminator column for inheritance
 */
#[Attribute(Attribute::TARGET_CLASS)]
class DiscriminatorColumn
{
    public function __construct(
        public readonly string $name = 'dtype',
        public readonly string $type = 'string',
        public readonly int $length = 255
    ) {
    }
}
