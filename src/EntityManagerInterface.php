<?php

declare(strict_types=1);

namespace Fduarte42\Aurum;

use Fduarte42\Aurum\Connection\ConnectionInterface;
use Fduarte42\Aurum\Repository\RepositoryInterface;
use Fduarte42\Aurum\UnitOfWork\UnitOfWorkInterface;

/**
 * Entity Manager interface for managing entities and unit of works
 */
interface EntityManagerInterface
{
    /**
     * Get the database connection
     */
    public function getConnection(): ConnectionInterface;

    /**
     * Get the current unit of work
     */
    public function getUnitOfWork(): UnitOfWorkInterface;

    /**
     * Create a new unit of work
     */
    public function createUnitOfWork(): UnitOfWorkInterface;

    /**
     * Switch to a different unit of work
     */
    public function setUnitOfWork(UnitOfWorkInterface $unitOfWork): void;

    /**
     * Find an entity by its identifier
     *
     * @template T of object
     * @param class-string<T> $className
     * @param mixed $id
     * @return T|null
     */
    public function find(string $className, mixed $id): ?object;

    /**
     * Persist an entity
     */
    public function persist(object $entity): void;

    /**
     * Remove an entity
     */
    public function remove(object $entity): void;

    /**
     * Refresh an entity from the database
     */
    public function refresh(object $entity): void;

    /**
     * Detach an entity from the current unit of work
     */
    public function detach(object $entity): void;

    /**
     * Check if an entity is managed
     */
    public function contains(object $entity): bool;

    /**
     * Flush all pending changes in the current unit of work
     */
    public function flush(): void;

    /**
     * Clear the current unit of work
     */
    public function clear(): void;

    /**
     * Get a repository for the given entity class
     *
     * @template T of object
     * @param class-string<T> $className
     * @return RepositoryInterface<T>
     */
    public function getRepository(string $className): RepositoryInterface;

    /**
     * Begin a transaction (creates surrounding transaction)
     */
    public function beginTransaction(): void;

    /**
     * Commit the surrounding transaction
     */
    public function commit(): void;

    /**
     * Rollback the surrounding transaction
     */
    public function rollback(): void;

    /**
     * Execute a function within a transaction
     *
     * @template T
     * @param callable(): T $func
     * @return T
     */
    public function transactional(callable $func): mixed;

    /**
     * Get all active unit of works
     *
     * @return array<UnitOfWorkInterface>
     */
    public function getUnitOfWorks(): array;

    /**
     * Get the migration manager
     */
    public function getMigrationManager(): \Fduarte42\Aurum\Migration\MigrationManagerInterface;
}
