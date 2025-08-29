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
     * Get the primary key field names
     *
     * @return array<string>
     */
    public function getIdentifierFieldNames(): array;

    /**
     * Get the primary key column names
     *
     * @return array<string>
     */
    public function getIdentifierColumnNames(): array;

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
     * 
     * @return mixed Single value for single-column PK, associative array for composite PK
     */
    public function getIdentifierValue(object $entity): mixed;

    /**
     * Get the identifier values from an entity
     *
     * @return array<string, mixed> Field name => value
     */
    public function getIdentifierValues(object $entity): array;

    /**
     * Set the identifier value on an entity
     * 
     * @param mixed $value Single value for single-column PK, associative array for composite PK
     */
    public function setIdentifierValue(object $entity, mixed $value): void;

    /**
     * Set the identifier values on an entity
     *
     * @param array<string, mixed> $values Field name => value
     */
    public function setIdentifierValues(object $entity, array $values): void;

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

    /**
     * Get inheritance mapping (null if not part of inheritance hierarchy)
     */
    public function getInheritanceMapping(): ?InheritanceMappingInterface;

    /**
     * Check if this entity is part of an inheritance hierarchy
     */
    public function hasInheritance(): bool;

    /**
     * Check if this entity is the root of an inheritance hierarchy
     */
    public function isInheritanceRoot(): bool;

    /**
     * Get the discriminator value for this entity class
     */
    public function getDiscriminatorValue(): ?string;

    /**
     * Get lifecycle callbacks for a specific event
     *
     * @return array<string> List of method names
     */
    public function getLifecycleCallbacks(string $eventName): array;

    /**
     * Check if the entity has any lifecycle callbacks for a specific event
     */
    public function hasLifecycleCallbacks(string $eventName): bool;

    /**
     * Invoke lifecycle callbacks for a specific event
     */
    public function invokeLifecycleCallbacks(string $eventName, object $entity, array $args = []): void;
}
