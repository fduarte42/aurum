<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Metadata;

use Fduarte42\Aurum\Exception\ORMException;
use ReflectionClass;
use ReflectionProperty;

/**
 * Entity metadata implementation
 */
class EntityMetadata implements EntityMetadataInterface
{
    private ReflectionClass $reflectionClass;
    
    /** @var array<string, FieldMappingInterface> */
    private array $fieldMappings = [];
    
    /** @var array<string, AssociationMappingInterface> */
    private array $associationMappings = [];

    private ?string $identifierFieldName = null;

    private ?InheritanceMappingInterface $inheritanceMapping = null;

    public function __construct(
        private readonly string $className,
        private readonly string $tableName
    ) {
        $this->reflectionClass = new ReflectionClass($className);
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function addFieldMapping(FieldMappingInterface $fieldMapping): void
    {
        $this->fieldMappings[$fieldMapping->getFieldName()] = $fieldMapping;
        
        if ($fieldMapping->isIdentifier()) {
            $this->identifierFieldName = $fieldMapping->getFieldName();
        }
    }

    public function addAssociationMapping(AssociationMappingInterface $associationMapping): void
    {
        $this->associationMappings[$associationMapping->getFieldName()] = $associationMapping;
    }

    public function getIdentifierFieldName(): string
    {
        if ($this->identifierFieldName === null) {
            throw ORMException::metadataNotFound("No identifier field found for {$this->className}");
        }
        
        return $this->identifierFieldName;
    }

    public function getIdentifierColumnName(): string
    {
        $identifierField = $this->getIdentifierFieldName();
        return $this->getFieldMapping($identifierField)?->getColumnName() ?? $identifierField;
    }

    public function getFieldMappings(): array
    {
        return $this->fieldMappings;
    }

    public function getFieldMapping(string $fieldName): ?FieldMappingInterface
    {
        return $this->fieldMappings[$fieldName] ?? null;
    }

    public function getAssociationMappings(): array
    {
        return $this->associationMappings;
    }

    public function getAssociationMapping(string $fieldName): ?AssociationMappingInterface
    {
        return $this->associationMappings[$fieldName] ?? null;
    }

    public function isIdentifier(string $fieldName): bool
    {
        return $fieldName === $this->identifierFieldName;
    }

    public function getIdentifierValue(object $entity): mixed
    {
        $identifierField = $this->getIdentifierFieldName();
        return $this->getFieldValue($entity, $identifierField);
    }

    public function setIdentifierValue(object $entity, mixed $value): void
    {
        $identifierField = $this->getIdentifierFieldName();
        $this->setFieldValue($entity, $identifierField, $value);
    }

    public function getFieldValue(object $entity, string $fieldName): mixed
    {
        // Handle discriminator field specially
        if ($fieldName === '__discriminator' && $this->hasInheritance()) {
            return get_class($entity);
        }

        $property = $this->getReflectionProperty($fieldName);
        $property->setAccessible(true);

        // Check if property is initialized before accessing it
        if (!$property->isInitialized($entity)) {
            // For nullable properties, return null if not initialized
            $fieldMapping = $this->getFieldMapping($fieldName);
            if ($fieldMapping !== null && $fieldMapping->isNullable()) {
                return null;
            }

            // For non-nullable properties, this is an error
            throw new \RuntimeException(
                "Property '{$fieldName}' in entity '{$this->className}' is not initialized. " .
                "This usually happens when an entity is created without calling its constructor."
            );
        }

        return $property->getValue($entity);
    }

    /**
     * Get field value as multiple database column values (for multi-column mappings)
     */
    public function getFieldValueAsMultipleColumns(object $entity, string $fieldName): array
    {
        $value = $this->getFieldValue($entity, $fieldName);

        $fieldMapping = $this->getFieldMapping($fieldName);
        if ($fieldMapping !== null && $fieldMapping->isMultiColumn()) {
            // Use multi-column conversion
            return $fieldMapping->convertToMultipleDatabaseValues($value);
        } else {
            // Fallback: convert single value
            $dbValue = $fieldMapping?->convertToDatabaseValue($value) ?? $value;
            return [$fieldMapping?->getColumnName() ?? $fieldName => $dbValue];
        }
    }

    public function setFieldValue(object $entity, string $fieldName, mixed $value): void
    {
        // Handle discriminator field specially - it's read-only, determined by entity class
        if ($fieldName === '__discriminator') {
            return; // Discriminator is automatically determined by entity class
        }

        $property = $this->getReflectionProperty($fieldName);
        $property->setAccessible(true);

        // Convert database value to PHP value if we have a field mapping
        $fieldMapping = $this->getFieldMapping($fieldName);
        if ($fieldMapping !== null) {
            $value = $fieldMapping->convertToPHPValue($value);
        }

        $property->setValue($entity, $value);
    }

    /**
     * Set field value from multiple database column values (for multi-column mappings)
     */
    public function setFieldValueFromMultipleColumns(object $entity, string $fieldName, array $columnValues): void
    {
        $property = $this->getReflectionProperty($fieldName);
        $property->setAccessible(true);

        $fieldMapping = $this->getFieldMapping($fieldName);
        if ($fieldMapping !== null && $fieldMapping->isMultiColumn()) {
            // Use multi-column conversion
            $value = $fieldMapping->convertFromMultipleDatabaseValues($columnValues);
        } else {
            // Fallback: use the first non-null value
            $value = null;
            foreach ($columnValues as $columnValue) {
                if ($columnValue !== null) {
                    $value = $fieldMapping?->convertToPHPValue($columnValue) ?? $columnValue;
                    break;
                }
            }
        }

        $property->setValue($entity, $value);
    }

    public function newInstance(): object
    {
        return $this->reflectionClass->newInstanceWithoutConstructor();
    }

    public function getColumnNames(): array
    {
        $columns = [];
        foreach ($this->fieldMappings as $fieldMapping) {
            if ($fieldMapping->isMultiColumn()) {
                // Add all columns for multi-column mappings
                $columns = array_merge($columns, $fieldMapping->getColumnNames());
            } else {
                // Add single column for regular mappings
                $columns[] = $fieldMapping->getColumnName();
            }
        }
        return $columns;
    }

    public function getColumnName(string $fieldName): string
    {
        $fieldMapping = $this->getFieldMapping($fieldName);
        return $fieldMapping?->getColumnName() ?? $fieldName;
    }

    public function getFieldName(string $columnName): string
    {
        foreach ($this->fieldMappings as $fieldMapping) {
            if ($fieldMapping->isMultiColumn()) {
                // Check if column name matches any of the multi-column names
                if (in_array($columnName, $fieldMapping->getColumnNames(), true)) {
                    return $fieldMapping->getFieldName();
                }
            } else {
                // Check single column mapping
                if ($fieldMapping->getColumnName() === $columnName) {
                    return $fieldMapping->getFieldName();
                }
            }
        }
        return $columnName;
    }

    public function getInheritanceMapping(): ?InheritanceMappingInterface
    {
        return $this->inheritanceMapping;
    }

    public function setInheritanceMapping(?InheritanceMappingInterface $inheritanceMapping): void
    {
        $this->inheritanceMapping = $inheritanceMapping;

        // Automatically add discriminator field mapping if this is the root class
        if ($inheritanceMapping !== null && $inheritanceMapping->isRootClass()) {
            $this->addDiscriminatorFieldMapping($inheritanceMapping);
        }
    }

    public function hasInheritance(): bool
    {
        return $this->inheritanceMapping !== null;
    }

    public function isInheritanceRoot(): bool
    {
        return $this->inheritanceMapping !== null && $this->inheritanceMapping->isRootClass();
    }

    public function getDiscriminatorValue(): ?string
    {
        if ($this->inheritanceMapping === null) {
            return null;
        }

        return $this->inheritanceMapping->getDiscriminatorValue($this->className);
    }

    /**
     * Add discriminator field mapping for inheritance
     */
    private function addDiscriminatorFieldMapping(InheritanceMappingInterface $inheritanceMapping): void
    {
        // Check if discriminator field mapping already exists
        $discriminatorColumn = $inheritanceMapping->getDiscriminatorColumn();

        // Don't add if it already exists
        foreach ($this->fieldMappings as $fieldMapping) {
            if ($fieldMapping->getColumnName() === $discriminatorColumn) {
                return;
            }
        }

        // Create a virtual field mapping for the discriminator column
        $discriminatorFieldMapping = new FieldMapping(
            fieldName: '__discriminator',
            columnName: $discriminatorColumn,
            type: $inheritanceMapping->getDiscriminatorType(),
            nullable: false,
            unique: false,
            length: $inheritanceMapping->getDiscriminatorLength(),
            precision: null,
            scale: null,
            default: null,
            isIdentifier: false,
            isGenerated: false,
            generationStrategy: null,
            typeRegistry: null
        );

        $this->fieldMappings['__discriminator'] = $discriminatorFieldMapping;
    }

    private function getReflectionProperty(string $fieldName): ReflectionProperty
    {
        // Handle discriminator field specially
        if ($fieldName === '__discriminator') {
            throw ORMException::metadataNotFound("Discriminator field {$fieldName} is virtual and has no reflection property");
        }

        try {
            return $this->reflectionClass->getProperty($fieldName);
        } catch (\ReflectionException $e) {
            throw ORMException::metadataNotFound("Property {$fieldName} not found in {$this->className}");
        }
    }
}
