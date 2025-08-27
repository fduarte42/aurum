<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Metadata;

/**
 * Field mapping interface for entity field metadata
 */
interface FieldMappingInterface
{
    /**
     * Get the field name
     */
    public function getFieldName(): string;

    /**
     * Get the column name (for single-column mappings)
     */
    public function getColumnName(): string;

    /**
     * Get all column names for this field mapping
     * For single-column mappings, returns array with one element
     * For multi-column mappings, returns array with multiple elements
     *
     * @return array<string>
     */
    public function getColumnNames(): array;

    /**
     * Check if this is a multi-column field mapping
     */
    public function isMultiColumn(): bool;

    /**
     * Get the field type
     */
    public function getType(): string;

    /**
     * Check if the field is nullable
     */
    public function isNullable(): bool;

    /**
     * Check if the field is unique
     */
    public function isUnique(): bool;

    /**
     * Get the field length (for string types)
     */
    public function getLength(): ?int;

    /**
     * Get the precision (for decimal types)
     */
    public function getPrecision(): ?int;

    /**
     * Get the scale (for decimal types)
     */
    public function getScale(): ?int;

    /**
     * Get the default value
     */
    public function getDefault(): mixed;

    /**
     * Check if this is an identifier field
     */
    public function isIdentifier(): bool;

    /**
     * Check if this field is generated (auto-increment, UUID, etc.)
     */
    public function isGenerated(): bool;

    /**
     * Get the generation strategy
     */
    public function getGenerationStrategy(): ?string;

    /**
     * Convert a database value to PHP value
     */
    public function convertToPHPValue(mixed $value): mixed;

    /**
     * Convert a PHP value to database value
     */
    public function convertToDatabaseValue(mixed $value): mixed;
}
