<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Connection;

use PDO;

/**
 * Database connection interface supporting transactions and savepoints
 */
interface ConnectionInterface
{
    /**
     * Get the underlying PDO connection
     */
    public function getPdo(): PDO;

    /**
     * Execute a SQL query with parameters
     *
     * @param string $sql
     * @param array<string, mixed> $parameters
     * @return \PDOStatement
     */
    public function execute(string $sql, array $parameters = []): \PDOStatement;

    /**
     * Execute a query and return the first result
     *
     * @param string $sql
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $parameters = []): ?array;

    /**
     * Execute a query and return all results
     *
     * @param string $sql
     * @param array<string, mixed> $parameters
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $parameters = []): array;

    /**
     * Begin a transaction
     */
    public function beginTransaction(): void;

    /**
     * Commit the current transaction
     */
    public function commit(): void;

    /**
     * Rollback the current transaction
     */
    public function rollback(): void;

    /**
     * Check if currently in a transaction
     */
    public function inTransaction(): bool;

    /**
     * Create a savepoint
     */
    public function createSavepoint(string $name): void;

    /**
     * Release a savepoint
     */
    public function releaseSavepoint(string $name): void;

    /**
     * Rollback to a savepoint
     */
    public function rollbackToSavepoint(string $name): void;

    /**
     * Get the last inserted ID
     */
    public function lastInsertId(): string;

    /**
     * Get the database platform name (sqlite, mysql, etc.)
     */
    public function getPlatform(): string;

    /**
     * Quote a value for safe SQL usage
     */
    public function quote(mixed $value): string;

    /**
     * Quote an identifier (table name, column name, etc.)
     */
    public function quoteIdentifier(string $identifier): string;
}
