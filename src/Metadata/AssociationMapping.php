<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Metadata;

/**
 * Association mapping implementation
 */
class AssociationMapping implements AssociationMappingInterface
{
    public function __construct(
        private readonly string $fieldName,
        private readonly string $targetEntity,
        private readonly string $type,
        private readonly bool $isOwningSide = true,
        private readonly ?string $mappedBy = null,
        private readonly ?string $inversedBy = null,
        private readonly ?string $joinColumn = null,
        private readonly ?string $referencedColumnName = null,
        private readonly bool $lazy = true,
        private readonly bool $nullable = true,
        private readonly array $cascade = [],
        private readonly mixed $joinTable = null
    ) {
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    public function getTargetEntity(): string
    {
        return $this->targetEntity;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isOwningSide(): bool
    {
        return $this->isOwningSide;
    }

    public function getMappedBy(): ?string
    {
        return $this->mappedBy;
    }

    public function getInversedBy(): ?string
    {
        return $this->inversedBy;
    }

    public function getJoinColumn(): ?string
    {
        return $this->joinColumn;
    }

    public function getReferencedColumnName(): ?string
    {
        return $this->referencedColumnName;
    }

    public function isLazy(): bool
    {
        return $this->lazy;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function getCascade(): array
    {
        return $this->cascade;
    }

    public function isCascade(string $cascade): bool
    {
        return in_array($cascade, $this->cascade, true) || in_array('all', $this->cascade, true);
    }

    public function getJoinTable(): mixed
    {
        return $this->joinTable;
    }

    public function isManyToMany(): bool
    {
        return $this->type === 'ManyToMany';
    }
}
