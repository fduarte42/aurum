<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Hydration;

use Fduarte42\Aurum\Metadata\EntityMetadataInterface;
use Fduarte42\Aurum\UnitOfWork\UnitOfWorkInterface;

/**
 * Interface for entity hydration services
 */
interface EntityHydratorInterface
{
    /**
     * Hydrate a managed entity (tracked by UnitOfWork)
     *
     * @param array $data Raw data to hydrate from
     * @param string $entityClass Entity class name
     * @param UnitOfWorkInterface $unitOfWork UnitOfWork for entity tracking
     * @return object Hydrated managed entity
     */
    public function hydrateManaged(
        array $data, 
        string $entityClass, 
        UnitOfWorkInterface $unitOfWork
    ): object;

    /**
     * Hydrate a detached entity (not tracked by UnitOfWork)
     *
     * @param array $data Raw data to hydrate from
     * @param string $entityClass Entity class name
     * @return object Hydrated detached entity
     */
    public function hydrateDetached(array $data, string $entityClass): object;

    /**
     * Populate an existing entity with data
     *
     * @param object $entity Entity to populate
     * @param array $data Data to populate with
     * @param EntityMetadataInterface|null $metadata Optional metadata (will be resolved if null)
     */
    public function populateEntity(object $entity, array $data, ?EntityMetadataInterface $metadata = null): void;

    /**
     * Copy field values from source entity to target entity
     *
     * @param object $sourceEntity Source entity to copy from
     * @param object $targetEntity Target entity to copy to
     * @param bool $skipIdentifier Whether to skip identifier fields
     */
    public function mergeEntities(object $sourceEntity, object $targetEntity, bool $skipIdentifier = true): void;

    /**
     * Hydrate multiple entities from array data
     *
     * @param array $dataArray Array of data arrays
     * @param string $entityClass Entity class name
     * @param bool $managed Whether entities should be managed
     * @param UnitOfWorkInterface|null $unitOfWork UnitOfWork for managed entities
     * @return array Array of hydrated entities
     */
    public function hydrateMultiple(
        array $dataArray, 
        string $entityClass, 
        bool $managed = true,
        ?UnitOfWorkInterface $unitOfWork = null
    ): array;

    /**
     * Extract entity data as array (reverse of hydration)
     *
     * @param object $entity Entity to extract data from
     * @return array Entity data as associative array
     */
    public function extractEntityData(object $entity): array;
}
