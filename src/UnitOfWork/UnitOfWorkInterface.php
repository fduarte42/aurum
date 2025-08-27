<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\UnitOfWork;

/**
 * Unit of Work interface for managing entity changes and transactions
 */
interface UnitOfWorkInterface
{
    /**
     * Register an entity for insertion
     */
    public function persist(object $entity): void;

    /**
     * Register an entity for deletion
     */
    public function remove(object $entity): void;

    /**
     * Refresh an entity from the database
     */
    public function refresh(object $entity): void;

    /**
     * Detach an entity from the unit of work
     */
    public function detach(object $entity): void;

    /**
     * Check if an entity is managed by this unit of work
     */
    public function contains(object $entity): bool;

    /**
     * Get an entity by its identifier
     *
     * @param class-string $className
     * @param mixed $id
     */
    public function find(string $className, mixed $id): ?object;

    /**
     * Flush all pending changes to the database
     */
    public function flush(): void;

    /**
     * Clear all managed entities
     */
    public function clear(): void;

    /**
     * Add an entity to the identity map without marking it as new
     */
    public function addToIdentityMap(string $identityKey, object $entity): void;

    /**
     * Set original entity data for change tracking
     */
    public function setOriginalEntityData(object $entity): void;

    /**
     * Get the current savepoint name for this unit of work
     */
    public function getSavepointName(): string;

    /**
     * Create a savepoint for this unit of work
     */
    public function createSavepoint(): void;

    /**
     * Release the savepoint for this unit of work
     */
    public function releaseSavepoint(): void;

    /**
     * Rollback to the savepoint for this unit of work
     */
    public function rollbackToSavepoint(): void;

    /**
     * Get all managed entities
     *
     * @return array<object>
     */
    public function getManagedEntities(): array;

    /**
     * Get entities scheduled for insertion
     *
     * @return array<object>
     */
    public function getScheduledInsertions(): array;

    /**
     * Get entities scheduled for update
     *
     * @return array<object>
     */
    public function getScheduledUpdates(): array;

    /**
     * Get entities scheduled for deletion
     *
     * @return array<object>
     */
    public function getScheduledDeletions(): array;
}
