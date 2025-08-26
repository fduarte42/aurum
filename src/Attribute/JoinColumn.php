<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Attribute;

use Attribute;

/**
 * Configures a join column for relationships
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class JoinColumn
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $referencedColumnName = null,
        public readonly bool $nullable = true,
        public readonly bool $unique = false,
        public readonly ?string $onDelete = null,
        public readonly ?string $onUpdate = null
    ) {
    }

    /**
     * Get the column name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the referenced column name
     */
    public function getReferencedColumnName(): ?string
    {
        return $this->referencedColumnName;
    }

    /**
     * Check if the column is nullable
     */
    public function isNullable(): bool
    {
        return $this->nullable;
    }

    /**
     * Check if the column is unique
     */
    public function isUnique(): bool
    {
        return $this->unique;
    }

    /**
     * Get the ON DELETE action
     */
    public function getOnDelete(): ?string
    {
        return $this->onDelete;
    }

    /**
     * Get the ON UPDATE action
     */
    public function getOnUpdate(): ?string
    {
        return $this->onUpdate;
    }
}
