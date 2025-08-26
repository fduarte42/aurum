<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Attribute;

use Attribute;

/**
 * Marks a property as a Many-to-Many relationship
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class ManyToMany
{
    public function __construct(
        public readonly string $targetEntity,
        public readonly ?string $mappedBy = null,
        public readonly ?string $inversedBy = null,
        public readonly array $cascade = [],
        public readonly array $fetch = ['LAZY'],
        public readonly bool $orphanRemoval = false
    ) {
    }

    /**
     * Get the target entity class name
     */
    public function getTargetEntity(): string
    {
        return $this->targetEntity;
    }

    /**
     * Get the mapped by property name (for inverse side)
     */
    public function getMappedBy(): ?string
    {
        return $this->mappedBy;
    }

    /**
     * Get the inversed by property name (for owning side)
     */
    public function getInversedBy(): ?string
    {
        return $this->inversedBy;
    }

    /**
     * Get cascade operations
     */
    public function getCascade(): array
    {
        return $this->cascade;
    }

    /**
     * Get fetch strategy
     */
    public function getFetch(): array
    {
        return $this->fetch;
    }

    /**
     * Check if orphan removal is enabled
     */
    public function isOrphanRemoval(): bool
    {
        return $this->orphanRemoval;
    }

    /**
     * Check if this is the owning side of the relationship
     */
    public function isOwningSide(): bool
    {
        return $this->mappedBy === null;
    }

    /**
     * Check if this is the inverse side of the relationship
     */
    public function isInverseSide(): bool
    {
        return $this->mappedBy !== null;
    }
}
