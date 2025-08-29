<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Hydration;

use Fduarte42\Aurum\Metadata\EntityMetadataInterface;
use Fduarte42\Aurum\Metadata\MetadataFactory;
use Fduarte42\Aurum\Proxy\ProxyFactoryInterface;
use Fduarte42\Aurum\UnitOfWork\UnitOfWorkInterface;

/**
 * Centralized entity hydration service
 * 
 * Consolidates all entity hydration logic into a single, reusable service
 * that handles different hydration scenarios while maintaining consistency
 * and reducing code duplication.
 */
class EntityHydrator implements EntityHydratorInterface
{
    public function __construct(
        private readonly MetadataFactory $metadataFactory
    ) {
    }

    /**
     * Hydrate a managed entity (tracked by UnitOfWork)
     */
    public function hydrateManaged(
        array $data, 
        string $entityClass, 
        UnitOfWorkInterface $unitOfWork
    ): object {
        $metadata = $this->metadataFactory->getMetadataFor($entityClass);
        
        // Determine the correct entity class for inheritance hierarchies
        $actualEntityClass = $this->resolveEntityClass($data, $entityClass, $metadata);
        if ($actualEntityClass !== $entityClass) {
            $metadata = $this->metadataFactory->getMetadataFor($actualEntityClass);
        }

        // Create new entity instance
        $entity = $metadata->newInstance();
        
        // Populate entity with data
        $this->populateEntity($entity, $data, $metadata);
        
        // Add to UnitOfWork for tracking
        $id = $metadata->getIdentifierValue($entity);
        if ($id !== null) {
            $identityKey = $actualEntityClass . '.' . $id;
            $unitOfWork->addToIdentityMap($identityKey, $entity);
            $unitOfWork->setOriginalEntityData($entity);
        }
        
        return $entity;
    }

    /**
     * Hydrate a detached entity (not tracked by UnitOfWork)
     */
    public function hydrateDetached(array $data, string $entityClass): object
    {
        $metadata = $this->metadataFactory->getMetadataFor($entityClass);
        
        // Determine the correct entity class for inheritance hierarchies
        $actualEntityClass = $this->resolveEntityClass($data, $entityClass, $metadata);
        if ($actualEntityClass !== $entityClass) {
            $metadata = $this->metadataFactory->getMetadataFor($actualEntityClass);
        }

        // Create new entity instance
        $entity = $metadata->newInstance();
        
        // Populate entity with data
        $this->populateEntity($entity, $data, $metadata);
        
        return $entity;
    }

    /**
     * Populate an existing entity with data
     */
    public function populateEntity(object $entity, array $data, ?EntityMetadataInterface $metadata = null): void
    {
        if ($metadata === null) {
            $className = $this->proxyFactory->getRealClass($entity);
            $metadata = $this->metadataFactory->getMetadataFor($className);
        }
        
        foreach ($metadata->getFieldMappings() as $fieldMapping) {
            $fieldName = $fieldMapping->getFieldName();
            $columnName = $fieldMapping->getColumnName();

            // Skip discriminator field as it's virtual
            if ($fieldName === '__discriminator') {
                continue;
            }

            if (isset($data[$fieldName])) {
                // Data is already mapped by field name (from query builder)
                $metadata->setFieldValue($entity, $fieldName, $data[$fieldName]);
            } elseif (isset($data[$columnName])) {
                // Data is mapped by column name (from raw SQL)
                $metadata->setFieldValue($entity, $fieldName, $data[$columnName]);
            }
        }
    }

    /**
     * Copy field values from source entity to target entity
     */
    public function mergeEntities(object $sourceEntity, object $targetEntity, bool $skipIdentifier = true): void
    {
        $sourceClassName = $this->getRealClass($sourceEntity);
        $targetClassName = $this->getRealClass($targetEntity);

        if ($sourceClassName !== $targetClassName) {
            throw new \InvalidArgumentException(
                "Cannot merge entities of different types: {$sourceClassName} and {$targetClassName}"
            );
        }

        $metadata = $this->metadataFactory->getMetadataFor($sourceClassName);

        foreach ($metadata->getFieldMappings() as $fieldMapping) {
            if ($skipIdentifier && $fieldMapping->isIdentifier()) {
                continue;
            }

            $fieldName = $fieldMapping->getFieldName();
            $value = $metadata->getFieldValue($sourceEntity, $fieldName);
            $metadata->setFieldValue($targetEntity, $fieldName, $value);
        }
    }

    /**
     * Hydrate multiple entities from array data
     */
    public function hydrateMultiple(
        array $dataArray, 
        string $entityClass, 
        bool $managed = true,
        ?UnitOfWorkInterface $unitOfWork = null
    ): array {
        $entities = [];
        
        foreach ($dataArray as $data) {
            if ($managed && $unitOfWork !== null) {
                $entities[] = $this->hydrateManaged($data, $entityClass, $unitOfWork);
            } else {
                $entities[] = $this->hydrateDetached($data, $entityClass);
            }
        }
        
        return $entities;
    }

    /**
     * Extract entity data as array (reverse of hydration)
     */
    public function extractEntityData(object $entity): array
    {
        $className = $this->getRealClass($entity);
        $metadata = $this->metadataFactory->getMetadataFor($className);

        $data = [];
        foreach ($metadata->getFieldMappings() as $fieldMapping) {
            $fieldName = $fieldMapping->getFieldName();
            $data[$fieldName] = $metadata->getFieldValue($entity, $fieldName);
        }

        return $data;
    }

    /**
     * Resolve the actual entity class for inheritance hierarchies
     */
    private function resolveEntityClass(array $data, string $rootEntityClass, EntityMetadataInterface $metadata): string
    {
        // Check if this is an inheritance hierarchy
        if (!$metadata->hasInheritance()) {
            return $rootEntityClass;
        }

        $inheritanceMapping = $metadata->getInheritanceMapping();
        $discriminatorColumn = $inheritanceMapping->getDiscriminatorColumn();
        
        // Look for discriminator value in data
        if (isset($data[$discriminatorColumn])) {
            $discriminatorValue = $data[$discriminatorColumn];
            $discriminatorMap = $inheritanceMapping->getDiscriminatorMap();
            
            if (isset($discriminatorMap[$discriminatorValue])) {
                return $discriminatorMap[$discriminatorValue];
            }
        }
        
        return $rootEntityClass;
    }

    /**
     * Get the real class name of an entity (handles proxies)
     */
    private function getRealClass(object $entity): string
    {
        $className = get_class($entity);

        // Handle Doctrine-style proxy classes
        if (str_contains($className, '__CG__\\')) {
            return substr($className, strpos($className, '__CG__\\') + 6);
        }

        // Handle other proxy patterns
        if (str_contains($className, 'Proxy\\')) {
            $parts = explode('\\', $className);
            $proxyIndex = array_search('Proxy', $parts);
            if ($proxyIndex !== false && isset($parts[$proxyIndex + 1])) {
                return implode('\\', array_slice($parts, $proxyIndex + 1));
            }
        }

        return $className;
    }
}
