<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Attribute;

use Attribute;

/**
 * Column attribute for field mapping
 *
 * The type parameter is now optional and will be inferred from the PHP property type if not provided.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Column
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $type = null, // Now nullable for type inference
        public readonly ?int $length = null,
        public readonly ?int $precision = null,
        public readonly ?int $scale = null,
        public readonly bool $nullable = false,
        public readonly bool $unique = false,
        public readonly mixed $default = null
    ) {
    }
}
