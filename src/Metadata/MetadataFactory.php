<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Metadata;

use Fduarte42\Aurum\Attribute\Column;
use Fduarte42\Aurum\Attribute\Entity;
use Fduarte42\Aurum\Attribute\Id;
use Fduarte42\Aurum\Attribute\ManyToOne;
use Fduarte42\Aurum\Attribute\OneToMany;
use Fduarte42\Aurum\Exception\ORMException;
use ReflectionClass;
use ReflectionProperty;

/**
 * Factory for creating entity metadata from attributes
 */
class MetadataFactory
{
    /** @var array<string, EntityMetadataInterface> */
    private array $metadataCache = [];

    /**
     * Get metadata for an entity class
     *
     * @param class-string $className
     */
    public function getMetadataFor(string $className): EntityMetadataInterface
    {
        if (isset($this->metadataCache[$className])) {
            return $this->metadataCache[$className];
        }

        $metadata = $this->loadMetadata($className);
        $this->metadataCache[$className] = $metadata;
        
        return $metadata;
    }

    /**
     * Load metadata from class attributes
     *
     * @param class-string $className
     */
    private function loadMetadata(string $className): EntityMetadataInterface
    {
        $reflectionClass = new ReflectionClass($className);
        
        // Check if class has Entity attribute
        $entityAttributes = $reflectionClass->getAttributes(Entity::class);
        if (empty($entityAttributes)) {
            throw ORMException::invalidEntityClass($className);
        }

        $entityAttribute = $entityAttributes[0]->newInstance();
        $tableName = $entityAttribute->table ?? $this->getTableNameFromClassName($className);
        
        $metadata = new EntityMetadata($className, $tableName);
        
        // Process properties
        foreach ($reflectionClass->getProperties() as $property) {
            $this->processProperty($metadata, $property);
        }
        
        return $metadata;
    }

    private function processProperty(EntityMetadata $metadata, ReflectionProperty $property): void
    {
        $fieldName = $property->getName();
        
        // Check for Id attribute
        $idAttributes = $property->getAttributes(Id::class);
        $isIdentifier = !empty($idAttributes);
        $generationStrategy = $isIdentifier ? $idAttributes[0]->newInstance()->strategy : null;
        
        // Check for Column attribute
        $columnAttributes = $property->getAttributes(Column::class);
        if (!empty($columnAttributes)) {
            $columnAttribute = $columnAttributes[0]->newInstance();
            
            $fieldMapping = new FieldMapping(
                fieldName: $fieldName,
                columnName: $columnAttribute->name ?? $this->getColumnNameFromFieldName($fieldName),
                type: $columnAttribute->type,
                nullable: $columnAttribute->nullable,
                unique: $columnAttribute->unique,
                length: $columnAttribute->length,
                precision: $columnAttribute->precision,
                scale: $columnAttribute->scale,
                default: $columnAttribute->default,
                isIdentifier: $isIdentifier,
                isGenerated: $isIdentifier,
                generationStrategy: $generationStrategy
            );
            
            $metadata->addFieldMapping($fieldMapping);
        }
        
        // Check for association attributes
        $this->processAssociations($metadata, $property);
    }

    private function processAssociations(EntityMetadata $metadata, ReflectionProperty $property): void
    {
        $fieldName = $property->getName();
        
        // ManyToOne
        $manyToOneAttributes = $property->getAttributes(ManyToOne::class);
        if (!empty($manyToOneAttributes)) {
            $attribute = $manyToOneAttributes[0]->newInstance();
            
            $associationMapping = new AssociationMapping(
                fieldName: $fieldName,
                targetEntity: $attribute->targetEntity,
                type: 'ManyToOne',
                isOwningSide: true,
                mappedBy: null,
                inversedBy: $attribute->inversedBy,
                joinColumn: $attribute->joinColumn ?? $fieldName . '_id',
                referencedColumnName: $attribute->referencedColumnName ?? 'id',
                lazy: $attribute->lazy,
                nullable: $attribute->nullable,
                cascade: $attribute->cascade
            );
            
            $metadata->addAssociationMapping($associationMapping);
        }
        
        // OneToMany
        $oneToManyAttributes = $property->getAttributes(OneToMany::class);
        if (!empty($oneToManyAttributes)) {
            $attribute = $oneToManyAttributes[0]->newInstance();
            
            $associationMapping = new AssociationMapping(
                fieldName: $fieldName,
                targetEntity: $attribute->targetEntity,
                type: 'OneToMany',
                isOwningSide: false,
                mappedBy: $attribute->mappedBy,
                inversedBy: null,
                joinColumn: null,
                referencedColumnName: null,
                lazy: $attribute->lazy,
                nullable: true,
                cascade: $attribute->cascade
            );
            
            $metadata->addAssociationMapping($associationMapping);
        }
    }

    private function getTableNameFromClassName(string $className): string
    {
        $shortName = (new ReflectionClass($className))->getShortName();
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $shortName));
    }

    private function getColumnNameFromFieldName(string $fieldName): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $fieldName));
    }

    /**
     * Clear metadata cache
     */
    public function clearCache(): void
    {
        $this->metadataCache = [];
    }

    /**
     * Check if metadata is cached
     *
     * @param class-string $className
     */
    public function hasMetadata(string $className): bool
    {
        return isset($this->metadataCache[$className]);
    }
}
