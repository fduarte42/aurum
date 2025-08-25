<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Attribute;

use Attribute;

/**
 * Id attribute for marking identifier fields
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Id
{
    public function __construct(
        public readonly string $strategy = 'UUID_TIME_BASED'
    ) {
    }
}
