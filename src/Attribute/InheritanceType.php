<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Attribute;

use Attribute;

/**
 * Marks an entity as the root of an inheritance hierarchy
 */
#[Attribute(Attribute::TARGET_CLASS)]
class InheritanceType
{
    public const SINGLE_TABLE = 'SINGLE_TABLE';
    public const JOINED = 'JOINED';
    public const TABLE_PER_CLASS = 'TABLE_PER_CLASS';

    public function __construct(
        public readonly string $strategy = self::SINGLE_TABLE,
        public readonly ?string $discriminatorColumn = null
    ) {
    }
}
