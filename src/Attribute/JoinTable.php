<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Attribute;

use Attribute;

/**
 * Configures the junction table for Many-to-Many relationships
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class JoinTable
{
    public function __construct(
        public readonly string $name,
        public readonly array $joinColumns = [],
        public readonly array $inverseJoinColumns = []
    ) {
    }

    /**
     * Get the junction table name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the join columns (from owning entity to junction table)
     */
    public function getJoinColumns(): array
    {
        return $this->joinColumns;
    }

    /**
     * Get the inverse join columns (from target entity to junction table)
     */
    public function getInverseJoinColumns(): array
    {
        return $this->inverseJoinColumns;
    }
}
