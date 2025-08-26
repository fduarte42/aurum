<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Metadata;

use Fduarte42\Aurum\Attribute\Column;
use Fduarte42\Aurum\Attribute\Entity;
use Fduarte42\Aurum\Attribute\Id;
use Fduarte42\Aurum\Attribute\JoinColumn;
use Fduarte42\Aurum\Attribute\JoinTable;
use Fduarte42\Aurum\Attribute\ManyToMany;
use Fduarte42\Aurum\Attribute\ManyToOne;
use Fduarte42\Aurum\Attribute\OneToMany;
use Fduarte42\Aurum\Exception\ORMException;
use Fduarte42\Aurum\Type\TypeRegistry;
use Fduarte42\Aurum\Type\TypeInference;
use ReflectionClass;
use ReflectionProperty;

/**
 * Factory for creating entity metadata from attributes
 */
class MetadataFactory
{
    /** @var array<string, EntityMetadataInterface> */
    private array $metadataCache = [];

    public function __construct(
        private readonly ?TypeRegistry $typeRegistry = null,
        private readonly ?TypeInference $typeInference = null
    ) {
    }

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

    private function hasAssociationAttribute(ReflectionProperty $property): bool
    {
        return !empty($property->getAttributes(ManyToOne::class)) ||
               !empty($property->getAttributes(OneToMany::class)) ||
               !empty($property->getAttributes(ManyToMany::class));
    }

    private function processProperty(EntityMetadata $metadata, ReflectionProperty $property): void
    {
        $fieldName = $property->getName();

        // Check for Id attribute
        $idAttributes = $property->getAttributes(Id::class);
        $isIdentifier = !empty($idAttributes);
        $generationStrategy = $isIdentifier ? $idAttributes[0]->newInstance()->strategy : null;

        // Skip properties that have association attributes (unless they're also columns)
        if (!$isIdentifier && $this->hasAssociationAttribute($property) && empty($property->getAttributes(Column::class))) {
            // Process associations but don't create field mappings
            $this->processAssociations($metadata, $property);
            return;
        }

        // Check for Column attribute
        $columnAttributes = $property->getAttributes(Column::class);
        if (!empty($columnAttributes)) {
            $columnAttribute = $columnAttributes[0]->newInstance();

            // Determine the type - use explicit type or infer from property
            $type = $columnAttribute->type;
            $length = $columnAttribute->length;
            $precision = $columnAttribute->precision;
            $scale = $columnAttribute->scale;

            // If type is not explicitly set or is 'string' (default) and we have type inference, try to infer
            if (($type === 'string' || $type === null) && $this->typeInference !== null) {
                $inferredType = $this->typeInference->inferFromProperty($property);
                if ($inferredType !== null) {
                    $type = $inferredType;

                    // Get inferred options if not explicitly set
                    $inferredOptions = $this->typeInference->inferTypeOptions($property, $type);
                    $length = $length ?? $inferredOptions['length'] ?? null;
                    $precision = $precision ?? $inferredOptions['precision'] ?? null;
                    $scale = $scale ?? $inferredOptions['scale'] ?? null;
                }
            }

            // Fallback to string if type is still null
            if ($type === null) {
                $type = 'string';
            }

            $fieldMapping = new FieldMapping(
                fieldName: $fieldName,
                columnName: $columnAttribute->name ?? $this->getColumnNameFromFieldName($fieldName),
                type: $type,
                nullable: $columnAttribute->nullable,
                unique: $columnAttribute->unique,
                length: $length,
                precision: $precision,
                scale: $scale,
                default: $columnAttribute->default,
                isIdentifier: $isIdentifier,
                isGenerated: $isIdentifier,
                generationStrategy: $generationStrategy,
                typeRegistry: $this->typeRegistry
            );

            $metadata->addFieldMapping($fieldMapping);
        } elseif ($this->typeInference !== null && !$this->hasAssociationAttribute($property)) {
            // No Column attribute but we have type inference - try to create a mapping
            // Only for properties that are not associations
            $inferredType = $this->typeInference->inferFromProperty($property);
            if ($inferredType !== null) {
                $inferredOptions = $this->typeInference->inferTypeOptions($property, $inferredType);

                $fieldMapping = new FieldMapping(
                    fieldName: $fieldName,
                    columnName: $this->getColumnNameFromFieldName($fieldName),
                    type: $inferredType,
                    nullable: $property->getType()?->allowsNull() ?? false,
                    unique: false,
                    length: $inferredOptions['length'] ?? null,
                    precision: $inferredOptions['precision'] ?? null,
                    scale: $inferredOptions['scale'] ?? null,
                    default: null,
                    isIdentifier: $isIdentifier,
                    isGenerated: $isIdentifier,
                    generationStrategy: $generationStrategy,
                    typeRegistry: $this->typeRegistry
                );

                $metadata->addFieldMapping($fieldMapping);
            }
            // Don't create fallback string mappings for properties without explicit Column attributes
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

        // ManyToMany
        $manyToManyAttributes = $property->getAttributes(ManyToMany::class);
        if (!empty($manyToManyAttributes)) {
            $attribute = $manyToManyAttributes[0]->newInstance();

            // Get JoinTable configuration if present
            $joinTableAttributes = $property->getAttributes(JoinTable::class);
            $joinTable = null;
            if (!empty($joinTableAttributes)) {
                $joinTable = $joinTableAttributes[0]->newInstance();
            }

            $associationMapping = new AssociationMapping(
                fieldName: $fieldName,
                targetEntity: $attribute->targetEntity,
                type: 'ManyToMany',
                isOwningSide: $attribute->isOwningSide(),
                mappedBy: $attribute->mappedBy,
                inversedBy: $attribute->inversedBy,
                joinColumn: null,
                referencedColumnName: null,
                lazy: true, // ManyToMany is always lazy by default
                nullable: true,
                cascade: $attribute->cascade,
                joinTable: $joinTable
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
