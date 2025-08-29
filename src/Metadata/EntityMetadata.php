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

    /** @var array<string> */
    private array $identifierFieldNames = [];

    private ?InheritanceMappingInterface $inheritanceMapping = null;

    /** @var array<string, array<string>> */
    private array $lifecycleCallbacks = [];

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

    public function addFieldMapping(FieldMappingInterface $fieldMapping): void
    {
        $this->fieldMappings[$fieldMapping->getFieldName()] = $fieldMapping;
        
        if ($fieldMapping->isIdentifier()) {
            if (!in_array($fieldMapping->getFieldName(), $this->identifierFieldNames, true)) {
                $this->identifierFieldNames[] = $fieldMapping->getFieldName();
            }
        }
    }

    public function addAssociationMapping(AssociationMappingInterface $associationMapping): void
    {
        $this->associationMappings[$associationMapping->getFieldName()] = $associationMapping;
    }

    public function getIdentifierFieldNames(): array
    {
        if (empty($this->identifierFieldNames)) {
            throw ORMException::noIdentifierFound($this->className);
        }
        
        return $this->identifierFieldNames;
    }

    public function getIdentifierColumnNames(): array
    {
        $columnNames = [];
        foreach ($this->getIdentifierFieldNames() as $fieldName) {
            $columnNames[] = $this->getFieldMapping($fieldName)?->getColumnName() ?? $fieldName;
        }
        return $columnNames;
    }

    public function getIdentifierValue(object $entity): mixed
    {
        $fieldNames = $this->getIdentifierFieldNames();
        if (count($fieldNames) === 1) {
            return $this->getFieldValue($entity, $fieldNames[0]);
        }
        
        return $this->getIdentifierValues($entity);
    }

    public function getIdentifierValues(object $entity): array
    {
        $values = [];
        foreach ($this->getIdentifierFieldNames() as $fieldName) {
            $values[$fieldName] = $this->getFieldValue($entity, $fieldName);
        }
        return $values;
    }

    public function setIdentifierValues(object $entity, array $values): void
    {
        foreach ($values as $fieldName => $value) {
            $this->setFieldValue($entity, $fieldName, $value);
        }
    }

    public function setIdentifierValue(object $entity, mixed $value): void
    {
        $fieldNames = $this->getIdentifierFieldNames();
        if (count($fieldNames) === 1) {
            $this->setFieldValue($entity, $fieldNames[0], $value);
            return;
        }

        if (is_array($value)) {
            $this->setIdentifierValues($entity, $value);
            return;
        }

        throw new \InvalidArgumentException("Cannot set single identifier value for composite primary key");
    }

    public function isIdentifier(string $fieldName): bool
    {
        return in_array($fieldName, $this->identifierFieldNames, true);
    }

    public function getIdentifierColumnName(): string
    {
        $columns = $this->getIdentifierColumnNames();
        return $columns[0] ?? '';
    }

    public function getIdentifierFieldName(): string
    {
        $fields = $this->getIdentifierFieldNames();
        return $fields[0] ?? '';
    }

    public function getFieldValue(object $entity, string $fieldName): mixed
    {
        // Handle discriminator field specially
        if ($fieldName === '__discriminator' && $this->hasInheritance()) {
            return get_class($entity);
        }

        $property = $this->getReflectionProperty($fieldName);

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

    public function getLifecycleCallbacks(string $eventName): array
    {
        return $this->lifecycleCallbacks[$eventName] ?? [];
    }

    public function hasLifecycleCallbacks(string $eventName): bool
    {
        return isset($this->lifecycleCallbacks[$eventName]) && !empty($this->lifecycleCallbacks[$eventName]);
    }

    public function addLifecycleCallback(string $eventName, string $methodName): void
    {
        if (!isset($this->lifecycleCallbacks[$eventName])) {
            $this->lifecycleCallbacks[$eventName] = [];
        }
        
        if (!in_array($methodName, $this->lifecycleCallbacks[$eventName], true)) {
            $this->lifecycleCallbacks[$eventName][] = $methodName;
        }
    }

    public function invokeLifecycleCallbacks(string $eventName, object $entity, array $args = []): void
    {
        foreach ($this->getLifecycleCallbacks($eventName) as $methodName) {
            $entity->$methodName(...$args);
        }
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
