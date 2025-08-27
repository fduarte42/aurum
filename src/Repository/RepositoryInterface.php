<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Repository;

use Fduarte42\Aurum\Query\QueryBuilderInterface;

/**
 * Repository interface for entity data access
 *
 * @template T of object
 */
interface RepositoryInterface
{
    /**
     * Find an entity by its identifier
     *
     * @param mixed $id
     * @return T|null
     */
    public function find(mixed $id): ?object;

    /**
     * Find all entities
     *
     * @return \Iterator<T>
     */
    public function findAll(): \Iterator;

    /**
     * Find entities by criteria
     *
     * @param array<string, mixed> $criteria
     * @param array<string, string>|null $orderBy
     * @param int|null $limit
     * @param int|null $offset
     * @return \Iterator<T>
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): \Iterator;

    /**
     * Find one entity by criteria
     *
     * @param array<string, mixed> $criteria
     * @param array<string, string>|null $orderBy
     * @return T|null
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?object;

    /**
     * Count entities by criteria
     *
     * @param array<string, mixed> $criteria
     */
    public function count(array $criteria = []): int;

    /**
     * Create a query builder for this repository
     */
    public function createQueryBuilder(string $alias): QueryBuilderInterface;

    /**
     * Get the entity class name
     *
     * @return class-string<T>
     */
    public function getClassName(): string;

    /**
     * Execute a custom SQL query
     *
     * @param string $sql
     * @param array<string, mixed> $parameters
     * @return \Iterator<T>
     */
    public function findBySql(string $sql, array $parameters = []): \Iterator;

    /**
     * Execute a custom SQL query and return one result
     *
     * @param string $sql
     * @param array<string, mixed> $parameters
     * @return T|null
     */
    public function findOneBySql(string $sql, array $parameters = []): ?object;

    /**
     * Find entities with LIKE condition
     *
     * @param string $field
     * @param string $pattern
     * @return \Iterator<T>
     */
    public function findByLike(string $field, string $pattern): \Iterator;

    /**
     * Find entities within a range
     *
     * @param string $field
     * @param mixed $min
     * @param mixed $max
     * @return \Iterator<T>
     */
    public function findByRange(string $field, mixed $min, mixed $max): \Iterator;

    /**
     * Find all entities as array (convenience method for backward compatibility)
     *
     * @return array<T>
     */
    public function findAllAsArray(): array;

    /**
     * Find entities by criteria as array (convenience method for backward compatibility)
     *
     * @param array<string, mixed> $criteria
     * @param array<string, string>|null $orderBy
     * @param int|null $limit
     * @param int|null $offset
     * @return array<T>
     */
    public function findByAsArray(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;

    /**
     * Execute a custom SQL query and return results as array (convenience method for backward compatibility)
     *
     * @param string $sql
     * @param array<string, mixed> $parameters
     * @return array<T>
     */
    public function findBySqlAsArray(string $sql, array $parameters = []): array;
}
