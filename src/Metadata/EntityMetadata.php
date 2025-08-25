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
        $property = $this->getReflectionProperty($fieldName);
        $property->setAccessible(true);
        return $property->getValue($entity);
    }

    public function setFieldValue(object $entity, string $fieldName, mixed $value): void
    {
        $property = $this->getReflectionProperty($fieldName);
        $property->setAccessible(true);
        
        // Convert database value to PHP value if we have a field mapping
        $fieldMapping = $this->getFieldMapping($fieldName);
        if ($fieldMapping !== null) {
            $value = $fieldMapping->convertToPHPValue($value);
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
            $columns[] = $fieldMapping->getColumnName();
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
            if ($fieldMapping->getColumnName() === $columnName) {
                return $fieldMapping->getFieldName();
            }
        }
        return $columnName;
    }

    private function getReflectionProperty(string $fieldName): ReflectionProperty
    {
        try {
            return $this->reflectionClass->getProperty($fieldName);
        } catch (\ReflectionException $e) {
            throw ORMException::metadataNotFound("Property {$fieldName} not found in {$this->className}");
        }
    }
}
