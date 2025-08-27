<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\UnitOfWork;

use Fduarte42\Aurum\Connection\ConnectionInterface;
use Fduarte42\Aurum\Exception\ORMException;
use Fduarte42\Aurum\Metadata\MetadataFactory;
use Fduarte42\Aurum\Proxy\ProxyFactoryInterface;
use Ramsey\Uuid\Uuid;

/**
 * Unit of Work implementation with change tracking and savepoint management
 */
class UnitOfWork implements UnitOfWorkInterface
{
    /** @var array<string, object> Identity map: className.id => entity */
    private array $identityMap = [];

    /** @var \SplObjectStorage<object, array<string, mixed>> Original entity data for change tracking */
    private \SplObjectStorage $originalEntityData;

    /** @var \SplObjectStorage<object, true> Entities scheduled for insertion */
    private \SplObjectStorage $entityInsertions;

    /** @var \SplObjectStorage<object, true> Entities scheduled for update */
    private \SplObjectStorage $entityUpdates;

    /** @var \SplObjectStorage<object, true> Entities scheduled for deletion */
    private \SplObjectStorage $entityDeletions;

    /** @var array<string, array> Many-to-Many associations scheduled for insertion */
    private array $manyToManyInsertions = [];

    /** @var array<string, array> Many-to-Many associations scheduled for deletion */
    private array $manyToManyDeletions = [];

    private string $savepointName;
    private bool $savepointCreated = false;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly MetadataFactory $metadataFactory,
        private readonly ProxyFactoryInterface $proxyFactory,
        private readonly string $unitOfWorkId
    ) {
        $this->savepointName = 'uow_' . $this->unitOfWorkId;
        $this->originalEntityData = new \SplObjectStorage();
        $this->entityInsertions = new \SplObjectStorage();
        $this->entityUpdates = new \SplObjectStorage();
        $this->entityDeletions = new \SplObjectStorage();
    }

    public function persist(object $entity): void
    {
        $className = $this->proxyFactory->getRealClass($entity);
        $metadata = $this->metadataFactory->getMetadataFor($className);

        $id = $metadata->getIdentifierValue($entity);

        if ($id === null) {
            // Generate UUID for new entities
            $identifierMapping = $metadata->getFieldMapping($metadata->getIdentifierFieldName());
            if ($identifierMapping && $identifierMapping->getGenerationStrategy() === 'UUID_TIME_BASED') {
                $uuid = Uuid::uuid1();
                $metadata->setIdentifierValue($entity, $uuid);
                $id = $uuid;
            }
        }

        if ($id !== null) {
            $identityKey = $className . '.' . $id;
            $this->identityMap[$identityKey] = $entity;
        }

        if (!$this->originalEntityData->contains($entity)) {
            $this->entityInsertions[$entity] = true;
            $this->originalEntityData[$entity] = $this->extractEntityData($entity);
        }

        // Auto-persist related entities
        $this->autoPersistRelatedEntities($entity, $metadata);
    }

    public function remove(object $entity): void
    {
        if (!$this->contains($entity)) {
            throw ORMException::entityNotManaged($entity);
        }
        
        $this->entityInsertions->detach($entity);
        $this->entityUpdates->detach($entity);
        $this->entityDeletions[$entity] = true;
    }

    public function refresh(object $entity): void
    {
        if (!$this->contains($entity)) {
            throw ORMException::entityNotManaged($entity);
        }
        
        $className = $this->proxyFactory->getRealClass($entity);
        $metadata = $this->metadataFactory->getMetadataFor($className);
        $id = $metadata->getIdentifierValue($entity);
        
        if ($id === null) {
            throw ORMException::entityNotFound($className, 'null');
        }
        
        // Load fresh data from database
        $sql = sprintf(
            'SELECT * FROM %s WHERE %s = :id',
            $this->connection->quoteIdentifier($metadata->getTableName()),
            $this->connection->quoteIdentifier($metadata->getIdentifierColumnName())
        );
        
        $data = $this->connection->fetchOne($sql, ['id' => $id]);
        
        if ($data === null) {
            throw ORMException::entityNotFound($className, $id);
        }
        
        // Update entity with fresh data
        foreach ($metadata->getFieldMappings() as $fieldMapping) {
            $columnName = $fieldMapping->getColumnName();
            if (isset($data[$columnName])) {
                $metadata->setFieldValue($entity, $fieldMapping->getFieldName(), $data[$columnName]);
            }
        }
        
        // Update original data
        $this->originalEntityData[$entity] = $this->extractEntityData($entity);

        // Remove from update queue
        $this->entityUpdates->detach($entity);
    }

    public function detach(object $entity): void
    {
        $className = $this->proxyFactory->getRealClass($entity);
        $metadata = $this->metadataFactory->getMetadataFor($className);
        $id = $metadata->getIdentifierValue($entity);
        
        if ($id !== null) {
            $identityKey = $className . '.' . $id;
            unset($this->identityMap[$identityKey]);
        }
        
        $this->originalEntityData->detach($entity);
        $this->entityInsertions->detach($entity);
        $this->entityUpdates->detach($entity);
        $this->entityDeletions->detach($entity);
    }

    public function contains(object $entity): bool
    {
        return $this->originalEntityData->contains($entity);
    }

    public function find(string $className, mixed $id): ?object
    {
        $identityKey = $className . '.' . $id;
        
        // Check identity map first
        if (isset($this->identityMap[$identityKey])) {
            return $this->identityMap[$identityKey];
        }
        
        // Load from database
        $metadata = $this->metadataFactory->getMetadataFor($className);
        
        $sql = sprintf(
            'SELECT * FROM %s WHERE %s = :id',
            $this->connection->quoteIdentifier($metadata->getTableName()),
            $this->connection->quoteIdentifier($metadata->getIdentifierColumnName())
        );
        
        $data = $this->connection->fetchOne($sql, ['id' => $id]);
        
        if ($data === null) {
            return null;
        }
        
        // Create entity and populate
        $entity = $metadata->newInstance();
        $this->populateEntity($entity, $data);
        
        // Add to identity map
        $this->identityMap[$identityKey] = $entity;
        $this->originalEntityData[$entity] = $this->extractEntityData($entity);
        
        return $entity;
    }

    public function flush(): void
    {
        if (!$this->connection->inTransaction()) {
            throw ORMException::transactionNotActive();
        }
        
        $this->createSavepoint();
        
        try {
            // Process deletions first
            foreach ($this->entityDeletions as $entity) {
                $this->executeDelete($entity);
            }

            // Process insertions in dependency order (referenced entities first)
            $sortedInsertions = $this->sortInsertionsByDependencies();
            foreach ($sortedInsertions as $entity) {
                $this->executeInsert($entity);
            }

            // Detect and process updates
            $this->computeChangeSets();
            foreach ($this->entityUpdates as $entity) {
                $this->executeUpdate($entity);
            }

            // Process Many-to-Many associations
            $this->processManyToManyAssociations();

            // Clear scheduled operations
            $this->entityInsertions = new \SplObjectStorage();
            $this->entityUpdates = new \SplObjectStorage();
            $this->entityDeletions = new \SplObjectStorage();
            $this->manyToManyInsertions = [];
            $this->manyToManyDeletions = [];
            
        } catch (\Exception $e) {
            $this->rollbackToSavepoint();
            throw $e;
        }
    }

    public function clear(): void
    {
        $this->identityMap = [];
        $this->originalEntityData = new \SplObjectStorage();
        $this->entityInsertions = new \SplObjectStorage();
        $this->entityUpdates = new \SplObjectStorage();
        $this->entityDeletions = new \SplObjectStorage();

        if ($this->savepointCreated && $this->connection->inTransaction()) {
            $this->rollbackToSavepoint();
        }
        $this->savepointCreated = false;
    }

    public function getSavepointName(): string
    {
        return $this->savepointName;
    }

    public function createSavepoint(): void
    {
        if (!$this->savepointCreated && $this->connection->inTransaction()) {
            $this->connection->createSavepoint($this->savepointName);
            $this->savepointCreated = true;
        }
    }

    public function releaseSavepoint(): void
    {
        if ($this->savepointCreated) {
            $this->connection->releaseSavepoint($this->savepointName);
            $this->savepointCreated = false;
        }
    }

    public function rollbackToSavepoint(): void
    {
        if ($this->savepointCreated) {
            $this->connection->rollbackToSavepoint($this->savepointName);
            $this->savepointCreated = false;
        }
    }

    public function getManagedEntities(): array
    {
        $entities = [];
        foreach ($this->originalEntityData as $entity) {
            $entities[] = $entity;
        }
        return $entities;
    }

    public function getScheduledInsertions(): array
    {
        $entities = [];
        foreach ($this->entityInsertions as $entity) {
            $entities[] = $entity;
        }
        return $entities;
    }

    public function getScheduledUpdates(): array
    {
        $entities = [];
        foreach ($this->entityUpdates as $entity) {
            $entities[] = $entity;
        }
        return $entities;
    }

    public function getScheduledDeletions(): array
    {
        $entities = [];
        foreach ($this->entityDeletions as $entity) {
            $entities[] = $entity;
        }
        return $entities;
    }

    private function extractEntityData(object $entity): array
    {
        $className = $this->proxyFactory->getRealClass($entity);
        $metadata = $this->metadataFactory->getMetadataFor($className);
        
        $data = [];
        foreach ($metadata->getFieldMappings() as $fieldMapping) {
            $data[$fieldMapping->getFieldName()] = $metadata->getFieldValue($entity, $fieldMapping->getFieldName());
        }
        
        return $data;
    }

    private function populateEntity(object $entity, array $data): void
    {
        $className = $this->proxyFactory->getRealClass($entity);
        $metadata = $this->metadataFactory->getMetadataFor($className);
        
        foreach ($metadata->getFieldMappings() as $fieldMapping) {
            $columnName = $fieldMapping->getColumnName();
            if (isset($data[$columnName])) {
                $metadata->setFieldValue($entity, $fieldMapping->getFieldName(), $data[$columnName]);
            }
        }
    }

    private function computeChangeSets(): void
    {
        foreach ($this->originalEntityData as $entity) {
            if ($this->entityInsertions->contains($entity) || $this->entityDeletions->contains($entity)) {
                continue;
            }

            $originalData = $this->originalEntityData[$entity];
            $currentData = $this->extractEntityData($entity);

            if ($currentData !== $originalData) {
                $this->entityUpdates[$entity] = true;
            }
        }
    }

    private function executeInsert(object $entity): void
    {
        $className = $this->proxyFactory->getRealClass($entity);
        $metadata = $this->metadataFactory->getMetadataFor($className);

        $data = [];

        // Process regular field mappings
        foreach ($metadata->getFieldMappings() as $fieldMapping) {
            $value = $metadata->getFieldValue($entity, $fieldMapping->getFieldName());
            $data[$fieldMapping->getColumnName()] = $fieldMapping->convertToDatabaseValue($value);
        }

        // Process association mappings for foreign keys
        foreach ($metadata->getAssociationMappings() as $association) {
            if ($association->isOwningSide() && $association->getJoinColumn()) {
                $joinColumn = $association->getJoinColumn();
                $fieldName = $association->getFieldName();
                $relatedEntity = $metadata->getFieldValue($entity, $fieldName);

                if ($relatedEntity !== null) {
                    $relatedClassName = $this->proxyFactory->getRealClass($relatedEntity);
                    $relatedMetadata = $this->metadataFactory->getMetadataFor($relatedClassName);

                    // Get the referenced value (usually the ID)
                    $referencedColumn = $association->getReferencedColumnName();
                    if ($referencedColumn === 'id' || $referencedColumn === $relatedMetadata->getIdentifierColumnName()) {
                        $referencedValue = $relatedMetadata->getIdentifierValue($relatedEntity);
                    } else {
                        // For non-ID references, find the field mapping
                        $referencedValue = null;
                        foreach ($relatedMetadata->getFieldMappings() as $fieldMapping) {
                            if ($fieldMapping->getColumnName() === $referencedColumn) {
                                $referencedValue = $relatedMetadata->getFieldValue($relatedEntity, $fieldMapping->getFieldName());
                                break;
                            }
                        }
                    }

                    $data[$joinColumn] = $referencedValue;
                } else {
                    // Set NULL for nullable foreign keys
                    if ($association->isNullable()) {
                        $data[$joinColumn] = null;
                    }
                }
            }
        }

        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ':' . $col, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->connection->quoteIdentifier($metadata->getTableName()),
            implode(', ', array_map([$this->connection, 'quoteIdentifier'], $columns)),
            implode(', ', $placeholders)
        );

        $this->connection->execute($sql, $data);

        // Update original data
        $this->originalEntityData[$entity] = $this->extractEntityData($entity);
    }

    private function executeUpdate(object $entity): void
    {
        $className = $this->proxyFactory->getRealClass($entity);
        $metadata = $this->metadataFactory->getMetadataFor($className);

        $data = [];
        $setParts = [];

        // Process regular field mappings
        foreach ($metadata->getFieldMappings() as $fieldMapping) {
            if ($fieldMapping->isIdentifier()) {
                continue;
            }

            $value = $metadata->getFieldValue($entity, $fieldMapping->getFieldName());
            $columnName = $fieldMapping->getColumnName();
            $data[$columnName] = $fieldMapping->convertToDatabaseValue($value);
            $setParts[] = $this->connection->quoteIdentifier($columnName) . ' = :' . $columnName;
        }

        // Process association mappings for foreign keys
        foreach ($metadata->getAssociationMappings() as $association) {
            if ($association->isOwningSide() && $association->getJoinColumn()) {
                $joinColumn = $association->getJoinColumn();
                $fieldName = $association->getFieldName();
                $relatedEntity = $metadata->getFieldValue($entity, $fieldName);

                if ($relatedEntity !== null) {
                    $relatedClassName = $this->proxyFactory->getRealClass($relatedEntity);
                    $relatedMetadata = $this->metadataFactory->getMetadataFor($relatedClassName);

                    // Get the referenced value (usually the ID)
                    $referencedColumn = $association->getReferencedColumnName();
                    if ($referencedColumn === 'id' || $referencedColumn === $relatedMetadata->getIdentifierColumnName()) {
                        $referencedValue = $relatedMetadata->getIdentifierValue($relatedEntity);
                    } else {
                        // For non-ID references, find the field mapping
                        $referencedValue = null;
                        foreach ($relatedMetadata->getFieldMappings() as $fieldMapping) {
                            if ($fieldMapping->getColumnName() === $referencedColumn) {
                                $referencedValue = $relatedMetadata->getFieldValue($relatedEntity, $fieldMapping->getFieldName());
                                break;
                            }
                        }
                    }

                    $data[$joinColumn] = $referencedValue;
                } else {
                    // Set NULL for nullable foreign keys
                    if ($association->isNullable()) {
                        $data[$joinColumn] = null;
                    }
                }

                $setParts[] = $this->connection->quoteIdentifier($joinColumn) . ' = :' . $joinColumn;
            }
        }

        $id = $metadata->getIdentifierValue($entity);
        $data[$metadata->getIdentifierColumnName()] = $id;

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = :%s',
            $this->connection->quoteIdentifier($metadata->getTableName()),
            implode(', ', $setParts),
            $this->connection->quoteIdentifier($metadata->getIdentifierColumnName()),
            $metadata->getIdentifierColumnName()
        );

        $this->connection->execute($sql, $data);

        // Update original data
        $this->originalEntityData[$entity] = $this->extractEntityData($entity);
    }

    private function executeDelete(object $entity): void
    {
        $className = $this->proxyFactory->getRealClass($entity);
        $metadata = $this->metadataFactory->getMetadataFor($className);
        
        $id = $metadata->getIdentifierValue($entity);
        
        $sql = sprintf(
            'DELETE FROM %s WHERE %s = :id',
            $this->connection->quoteIdentifier($metadata->getTableName()),
            $this->connection->quoteIdentifier($metadata->getIdentifierColumnName())
        );
        
        $this->connection->execute($sql, ['id' => $id]);
        
        // Remove from identity map and tracking
        $identityKey = $className . '.' . $id;
        unset($this->identityMap[$identityKey]);
        $this->originalEntityData->detach($entity);
    }

    /**
     * Automatically persist related entities that haven't been persisted yet
     */
    private function autoPersistRelatedEntities(object $entity, \Fduarte42\Aurum\Metadata\EntityMetadataInterface $metadata): void
    {
        $associations = $metadata->getAssociationMappings();

        foreach ($associations as $association) {
            $fieldName = $association->getFieldName();
            $relatedEntity = $metadata->getFieldValue($entity, $fieldName);

            if ($relatedEntity === null) {
                continue;
            }

            if ($association->getType() === 'ManyToOne') {
                // For ManyToOne, persist the related entity if it's not already managed
                if (!$this->contains($relatedEntity)) {
                    $this->persist($relatedEntity);
                }

                // Set the foreign key value automatically
                $this->setForeignKeyValue($entity, $metadata, $association, $relatedEntity);

            } elseif ($association->getType() === 'OneToMany') {
                // For OneToMany, persist all entities in the collection
                if (is_array($relatedEntity) || $relatedEntity instanceof \Traversable) {
                    foreach ($relatedEntity as $relatedItem) {
                        if (!$this->contains($relatedItem)) {
                            $this->persist($relatedItem);
                        }

                        // Set the inverse foreign key on the related entity
                        $this->setInverseForeignKeyValue($entity, $relatedItem, $association);
                    }
                }
            } elseif ($association->getType() === 'ManyToMany') {
                // For ManyToMany, persist all entities in the collection
                if (is_array($relatedEntity) || $relatedEntity instanceof \Traversable) {
                    foreach ($relatedEntity as $relatedItem) {
                        if (!$this->contains($relatedItem)) {
                            $this->persist($relatedItem);
                        }
                    }

                    // Schedule Many-to-Many association updates
                    $this->scheduleManyToManyUpdates($entity, $association, $relatedEntity);
                }
            }
        }
    }

    /**
     * Schedule Many-to-Many association updates
     */
    private function scheduleManyToManyUpdates(object $entity, \Fduarte42\Aurum\Metadata\AssociationMapping $association, $relatedEntities): void
    {
        if (!$association->isOwningSide()) {
            return; // Only process owning side
        }

        $className = $this->proxyFactory->getRealClass($entity);
        $metadata = $this->metadataFactory->getMetadataFor($className);
        $entityId = $metadata->getIdentifierValue($entity);

        $joinTable = $association->getJoinTable();
        $tableName = $joinTable ? $joinTable->getName() : $this->generateJunctionTableName($metadata, $association);

        $key = $tableName . '_' . $entityId . '_' . $association->getFieldName();

        // Store current associations for comparison during flush
        $currentAssociations = [];
        if (is_array($relatedEntities) || $relatedEntities instanceof \Traversable) {
            foreach ($relatedEntities as $relatedEntity) {
                $relatedClassName = $this->proxyFactory->getRealClass($relatedEntity);
                $relatedMetadata = $this->metadataFactory->getMetadataFor($relatedClassName);
                $relatedId = $relatedMetadata->getIdentifierValue($relatedEntity);

                $currentAssociations[] = [
                    'entity' => $entity,
                    'relatedEntity' => $relatedEntity,
                    'entityId' => $entityId,
                    'relatedId' => $relatedId,
                    'tableName' => $tableName,
                    'association' => $association
                ];
            }
        }

        $this->manyToManyInsertions[$key] = $currentAssociations;
    }

    /**
     * Generate junction table name for Many-to-Many relationships
     */
    private function generateJunctionTableName(\Fduarte42\Aurum\Metadata\EntityMetadataInterface $metadata, \Fduarte42\Aurum\Metadata\AssociationMapping $association): string
    {
        $sourceTable = $metadata->getTableName();
        $targetMetadata = $this->metadataFactory->getMetadataFor($association->getTargetEntity());
        $targetTable = $targetMetadata->getTableName();

        return $sourceTable . '_' . $targetTable;
    }

    /**
     * Set foreign key value for ManyToOne relationships
     */
    private function setForeignKeyValue(
        object $entity,
        \Fduarte42\Aurum\Metadata\EntityMetadataInterface $metadata,
        \Fduarte42\Aurum\Metadata\AssociationMapping $association,
        object $relatedEntity
    ): void {
        $joinColumn = $association->getJoinColumn();
        $referencedColumn = $association->getReferencedColumnName();

        // Get the related entity's metadata to extract the referenced value
        $relatedClassName = $this->proxyFactory->getRealClass($relatedEntity);
        $relatedMetadata = $this->metadataFactory->getMetadataFor($relatedClassName);

        // Get the value from the referenced column (usually the ID)
        if ($referencedColumn === 'id' || $referencedColumn === $relatedMetadata->getIdentifierColumnName()) {
            $referencedValue = $relatedMetadata->getIdentifierValue($relatedEntity);
        } else {
            // For non-ID references, we need to find the field mapping
            foreach ($relatedMetadata->getFieldMappings() as $fieldMapping) {
                if ($fieldMapping->getColumnName() === $referencedColumn) {
                    $referencedValue = $relatedMetadata->getFieldValue($relatedEntity, $fieldMapping->getFieldName());
                    break;
                }
            }
        }

        // Set the foreign key value on the entity
        foreach ($metadata->getFieldMappings() as $fieldMapping) {
            if ($fieldMapping->getColumnName() === $joinColumn) {
                $metadata->setFieldValue($entity, $fieldMapping->getFieldName(), $referencedValue);
                break;
            }
        }
    }

    /**
     * Set inverse foreign key value for OneToMany relationships
     */
    private function setInverseForeignKeyValue(
        object $entity,
        object $relatedEntity,
        \Fduarte42\Aurum\Metadata\AssociationMapping $association
    ): void {
        $mappedBy = $association->getMappedBy();
        if (!$mappedBy) {
            return; // No inverse mapping
        }

        $relatedClassName = $this->proxyFactory->getRealClass($relatedEntity);
        $relatedMetadata = $this->metadataFactory->getMetadataFor($relatedClassName);

        // Find the inverse association
        foreach ($relatedMetadata->getAssociationMappings() as $inverseAssociation) {
            if ($inverseAssociation->getFieldName() === $mappedBy) {
                // Set the entity on the related entity
                $relatedMetadata->setFieldValue($relatedEntity, $mappedBy, $entity);

                // Also set the foreign key value
                $this->setForeignKeyValue($relatedEntity, $relatedMetadata, $inverseAssociation, $entity);
                break;
            }
        }
    }

    /**
     * Sort entities for insertion by their dependencies (referenced entities first)
     */
    private function sortInsertionsByDependencies(): array
    {
        $entities = [];
        $dependencies = new \SplObjectStorage();

        // Build dependency graph
        foreach ($this->entityInsertions as $entity) {
            $entities[] = $entity;
            $dependencies[$entity] = $this->getEntityDependencies($entity);
        }

        // Topological sort
        $sorted = [];
        $visited = new \SplObjectStorage();
        $visiting = new \SplObjectStorage();

        foreach ($entities as $entity) {
            if (!$visited->contains($entity)) {
                $this->topologicalSortVisit($entity, $dependencies, $visited, $visiting, $sorted);
            }
        }

        return $sorted;
    }

    /**
     * Get entities that this entity depends on (ManyToOne relationships)
     */
    private function getEntityDependencies(object $entity): array
    {
        $className = $this->proxyFactory->getRealClass($entity);
        $metadata = $this->metadataFactory->getMetadataFor($className);
        $dependencies = [];

        foreach ($metadata->getAssociationMappings() as $association) {
            if ($association->getType() === 'ManyToOne' && $association->isOwningSide()) {
                $fieldName = $association->getFieldName();
                $relatedEntity = $metadata->getFieldValue($entity, $fieldName);

                if ($relatedEntity !== null && $this->entityInsertions->contains($relatedEntity)) {
                    $dependencies[] = $relatedEntity;
                }
            }
        }

        return $dependencies;
    }

    /**
     * Topological sort visit for dependency resolution
     */
    private function topologicalSortVisit(
        object $entity,
        \SplObjectStorage $dependencies,
        \SplObjectStorage $visited,
        \SplObjectStorage $visiting,
        array &$sorted
    ): void {
        if ($visiting->contains($entity)) {
            // Circular dependency detected - this is okay for auto-persist
            return;
        }

        if ($visited->contains($entity)) {
            return;
        }

        $visiting->attach($entity);

        foreach ($dependencies[$entity] as $dependency) {
            $this->topologicalSortVisit($dependency, $dependencies, $visited, $visiting, $sorted);
        }

        $visiting->detach($entity);
        $visited->attach($entity);
        $sorted[] = $entity;
    }

    /**
     * Process Many-to-Many associations
     */
    private function processManyToManyAssociations(): void
    {
        // Process Many-to-Many deletions first
        foreach ($this->manyToManyDeletions as $associations) {
            foreach ($associations as $association) {
                $this->deleteManyToManyAssociation($association);
            }
        }

        // Process Many-to-Many insertions
        foreach ($this->manyToManyInsertions as $associations) {
            foreach ($associations as $association) {
                $this->insertManyToManyAssociation($association);
            }
        }
    }

    /**
     * Insert a Many-to-Many association
     */
    private function insertManyToManyAssociation(array $association): void
    {
        $tableName = $association['tableName'];
        $entityId = $association['entityId'];
        $relatedId = $association['relatedId'];
        $mapping = $association['association'];

        $joinTable = $mapping->getJoinTable();

        // Get column names
        $sourceColumn = $this->getJunctionColumnName($joinTable, 'join', 'entity_id');
        $targetColumn = $this->getJunctionColumnName($joinTable, 'inverse', 'related_id');

        // Check if association already exists
        $existsQuery = "SELECT COUNT(*) as count FROM {$tableName} WHERE {$sourceColumn} = ? AND {$targetColumn} = ?";
        $exists = $this->connection->fetchOne($existsQuery, [$entityId, $relatedId]);

        if (!$exists || $exists['count'] == 0) {
            // Insert the association
            $insertQuery = "INSERT INTO {$tableName} ({$sourceColumn}, {$targetColumn}) VALUES (?, ?)";
            $this->connection->execute($insertQuery, [$entityId, $relatedId]);
        }
    }

    /**
     * Delete a Many-to-Many association
     */
    private function deleteManyToManyAssociation(array $association): void
    {
        $tableName = $association['tableName'];
        $entityId = $association['entityId'];
        $relatedId = $association['relatedId'];
        $mapping = $association['association'];

        $joinTable = $mapping->getJoinTable();

        // Get column names
        $sourceColumn = $this->getJunctionColumnName($joinTable, 'join', 'entity_id');
        $targetColumn = $this->getJunctionColumnName($joinTable, 'inverse', 'related_id');

        // Delete the association
        $deleteQuery = "DELETE FROM {$tableName} WHERE {$sourceColumn} = ? AND {$targetColumn} = ?";
        $this->connection->execute($deleteQuery, [$entityId, $relatedId]);
    }

    /**
     * Get junction table column name
     */
    private function getJunctionColumnName($joinTable, string $side, string $default): string
    {
        if (!$joinTable) {
            return $default;
        }

        $columns = $side === 'join' ? $joinTable->getJoinColumns() : $joinTable->getInverseJoinColumns();

        if (!empty($columns) && isset($columns[0])) {
            $column = $columns[0];
            return is_object($column) ? $column->getName() : $column['name'];
        }

        return $default;
    }
}
