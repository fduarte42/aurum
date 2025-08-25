<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Metadata;

/**
 * Association mapping interface for entity relationships
 */
interface AssociationMappingInterface
{
    /**
     * Get the field name
     */
    public function getFieldName(): string;

    /**
     * Get the target entity class
     *
     * @return class-string
     */
    public function getTargetEntity(): string;

    /**
     * Get the association type (OneToOne, OneToMany, ManyToOne, ManyToMany)
     */
    public function getType(): string;

    /**
     * Check if this is the owning side of the association
     */
    public function isOwningSide(): bool;

    /**
     * Get the mapped by field (for inverse side)
     */
    public function getMappedBy(): ?string;

    /**
     * Get the inverse by field (for owning side)
     */
    public function getInversedBy(): ?string;

    /**
     * Get the join column name (for owning side)
     */
    public function getJoinColumn(): ?string;

    /**
     * Get the referenced column name
     */
    public function getReferencedColumnName(): ?string;

    /**
     * Check if the association should be fetched lazily
     */
    public function isLazy(): bool;

    /**
     * Check if the association is nullable
     */
    public function isNullable(): bool;

    /**
     * Get cascade options
     *
     * @return array<string>
     */
    public function getCascade(): array;

    /**
     * Check if a specific cascade option is enabled
     */
    public function isCascade(string $cascade): bool;
}
