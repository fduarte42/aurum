<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Metadata;

/**
 * Entity metadata interface for storing entity mapping information
 */
interface EntityMetadataInterface
{
    /**
     * Get the entity class name
     *
     * @return class-string
     */
    public function getClassName(): string;

    /**
     * Get the table name
     */
    public function getTableName(): string;

    /**
     * Get the primary key field name
     */
    public function getIdentifierFieldName(): string;

    /**
     * Get the primary key column name
     */
    public function getIdentifierColumnName(): string;

    /**
     * Get all field mappings
     *
     * @return array<string, FieldMappingInterface>
     */
    public function getFieldMappings(): array;

    /**
     * Get field mapping by field name
     */
    public function getFieldMapping(string $fieldName): ?FieldMappingInterface;

    /**
     * Get association mappings
     *
     * @return array<string, AssociationMappingInterface>
     */
    public function getAssociationMappings(): array;

    /**
     * Get association mapping by field name
     */
    public function getAssociationMapping(string $fieldName): ?AssociationMappingInterface;

    /**
     * Check if a field is the identifier
     */
    public function isIdentifier(string $fieldName): bool;

    /**
     * Get the identifier value from an entity
     */
    public function getIdentifierValue(object $entity): mixed;

    /**
     * Set the identifier value on an entity
     */
    public function setIdentifierValue(object $entity, mixed $value): void;

    /**
     * Get a field value from an entity
     */
    public function getFieldValue(object $entity, string $fieldName): mixed;

    /**
     * Set a field value on an entity
     */
    public function setFieldValue(object $entity, string $fieldName, mixed $value): void;

    /**
     * Create a new instance of the entity
     */
    public function newInstance(): object;

    /**
     * Get all column names
     *
     * @return array<string>
     */
    public function getColumnNames(): array;

    /**
     * Get column name for field
     */
    public function getColumnName(string $fieldName): string;

    /**
     * Get field name for column
     */
    public function getFieldName(string $columnName): string;
}
