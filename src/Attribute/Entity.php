<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Attribute;

use Attribute;

/**
 * Entity attribute for marking classes as entities
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Entity
{
    public function __construct(
        public readonly ?string $table = null,
        public readonly ?string $repositoryClass = null
    ) {
    }
}
