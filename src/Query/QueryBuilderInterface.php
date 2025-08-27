<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Query;

/**
 * Query Builder interface for constructing SQL queries with join support
 */
interface QueryBuilderInterface
{
    /**
     * Add a SELECT clause
     *
     * @param string|array<string> ...$select
     */
    public function select(string|array ...$select): self;

    /**
     * Add additional SELECT fields
     *
     * @param string|array<string> $select
     */
    public function addSelect(string|array $select): self;

    /**
     * Set the FROM clause
     */
    public function from(string $table, string $alias): self;

    /**
     * Add an INNER JOIN
     * If condition is null, it will be automatically resolved from entity metadata
     */
    public function innerJoin(string $join, string $alias, ?string $condition = null): self;

    /**
     * Add a LEFT JOIN
     * If condition is null, it will be automatically resolved from entity metadata
     */
    public function leftJoin(string $join, string $alias, ?string $condition = null): self;

    /**
     * Add a RIGHT JOIN
     * If condition is null, it will be automatically resolved from entity metadata
     */
    public function rightJoin(string $join, string $alias, ?string $condition = null): self;

    /**
     * Add a WHERE condition
     */
    public function where(string $condition): self;

    /**
     * Add an AND WHERE condition
     */
    public function andWhere(string $condition): self;

    /**
     * Add an OR WHERE condition
     */
    public function orWhere(string $condition): self;

    /**
     * Add a GROUP BY clause
     *
     * @param string|array<string> $groupBy
     */
    public function groupBy(string|array $groupBy): self;

    /**
     * Add additional GROUP BY fields
     *
     * @param string|array<string> $groupBy
     */
    public function addGroupBy(string|array $groupBy): self;

    /**
     * Add a HAVING condition
     */
    public function having(string $condition): self;

    /**
     * Add an AND HAVING condition
     */
    public function andHaving(string $condition): self;

    /**
     * Add an OR HAVING condition
     */
    public function orHaving(string $condition): self;

    /**
     * Add an ORDER BY clause
     *
     * @param string|array<string> $orderBy
     * @param string $order ASC or DESC
     */
    public function orderBy(string|array $orderBy, string $order = 'ASC'): self;

    /**
     * Add additional ORDER BY fields
     *
     * @param string|array<string> $orderBy
     * @param string $order ASC or DESC
     */
    public function addOrderBy(string|array $orderBy, string $order = 'ASC'): self;

    /**
     * Set the LIMIT
     */
    public function setMaxResults(int $maxResults): self;

    /**
     * Set the OFFSET
     */
    public function setFirstResult(int $firstResult): self;

    /**
     * Set a parameter value
     */
    public function setParameter(string $key, mixed $value): self;

    /**
     * Set multiple parameters
     *
     * @param array<string, mixed> $parameters
     */
    public function setParameters(array $parameters): self;

    /**
     * Get the generated SQL
     */
    public function getSQL(): string;

    /**
     * Get the parameters
     *
     * @return array<string, mixed>
     */
    public function getParameters(): array;

    /**
     * Execute the query and return results as a PDOStatement iterator
     *
     * @return \PDOStatement
     */
    public function getArrayResult(): \PDOStatement;

    /**
     * Execute the query and return hydrated entity objects (unmanaged/detached)
     *
     * @return array<object>
     */
    public function getResult(): array;

    /**
     * Execute the query and return one result
     *
     * @return array<string, mixed>|null
     */
    public function getOneOrNullResult(): ?array;

    /**
     * Execute the query and return a single scalar value
     */
    public function getSingleScalarResult(): mixed;
}
