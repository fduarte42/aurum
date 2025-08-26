<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Driver;

use PDO;
use PDOStatement;

/**
 * Database driver interface defining the contract for all database operations
 * 
 * This interface abstracts database-specific functionality to support multiple
 * database systems while maintaining a consistent API.
 */
interface DatabaseDriverInterface
{
    /**
     * Get the platform name (e.g., 'sqlite', 'mariadb', 'mysql')
     */
    public function getPlatform(): string;

    /**
     * Get the underlying PDO connection
     */
    public function getPdo(): PDO;

    /**
     * Execute a SQL query with parameters
     *
     * @param string $sql
     * @param array<string, mixed> $parameters
     * @return PDOStatement
     * @throws \Fduarte42\Aurum\Exception\ORMException
     */
    public function execute(string $sql, array $parameters = []): PDOStatement;

    /**
     * Execute a query and return the first result
     *
     * @param string $sql
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>|null
     * @throws \Fduarte42\Aurum\Exception\ORMException
     */
    public function fetchOne(string $sql, array $parameters = []): ?array;

    /**
     * Execute a query and return all results
     *
     * @param string $sql
     * @param array<string, mixed> $parameters
     * @return array<int, array<string, mixed>>
     * @throws \Fduarte42\Aurum\Exception\ORMException
     */
    public function fetchAll(string $sql, array $parameters = []): array;

    /**
     * Get the last inserted ID
     */
    public function lastInsertId(?string $name = null): string;

    /**
     * Quote a value for safe inclusion in SQL
     */
    public function quote(mixed $value): string;

    /**
     * Quote an identifier (table name, column name, etc.) for the specific database platform
     */
    public function quoteIdentifier(string $identifier): string;

    /**
     * Begin a database transaction
     * 
     * @throws \Fduarte42\Aurum\Exception\ORMException
     */
    public function beginTransaction(): void;

    /**
     * Commit the current transaction
     * 
     * @throws \Fduarte42\Aurum\Exception\ORMException
     */
    public function commit(): void;

    /**
     * Rollback the current transaction
     * 
     * @throws \Fduarte42\Aurum\Exception\ORMException
     */
    public function rollback(): void;

    /**
     * Check if currently in a transaction
     */
    public function inTransaction(): bool;

    /**
     * Create a savepoint with the given name
     * 
     * @throws \Fduarte42\Aurum\Exception\ORMException
     */
    public function createSavepoint(string $name): void;

    /**
     * Release a savepoint with the given name
     * 
     * @throws \Fduarte42\Aurum\Exception\ORMException
     */
    public function releaseSavepoint(string $name): void;

    /**
     * Rollback to a savepoint with the given name
     * 
     * @throws \Fduarte42\Aurum\Exception\ORMException
     */
    public function rollbackToSavepoint(string $name): void;

    /**
     * Check if the database supports savepoints
     */
    public function supportsSavepoints(): bool;

    /**
     * Generate a unique savepoint name
     */
    public function generateSavepointName(): string;

    /**
     * Get SQL for checking if a table exists
     */
    public function getTableExistsSQL(): string;

    /**
     * Get SQL for checking if an index exists
     */
    public function getIndexExistsSQL(): string;

    /**
     * Get SQL for dropping an index
     */
    public function getDropIndexSQL(string $tableName, string $indexName): string;

    /**
     * Get platform-specific SQL type for the given generic type and options
     * 
     * @param string $genericType The generic SQL type (e.g., 'VARCHAR', 'INTEGER')
     * @param array<string, mixed> $options Type-specific options (length, precision, etc.)
     */
    public function getSQLType(string $genericType, array $options = []): string;

    /**
     * Get platform-specific SQL for LIMIT/OFFSET clauses
     * 
     * @param int|null $limit Maximum number of results
     * @param int|null $offset Number of results to skip
     */
    public function getLimitOffsetSQL(?int $limit, ?int $offset = null): string;

    /**
     * Check if the database supports foreign key constraints
     */
    public function supportsForeignKeys(): bool;

    /**
     * Check if the database supports adding foreign keys to existing tables
     */
    public function supportsAddingForeignKeys(): bool;

    /**
     * Check if the database supports dropping foreign keys from existing tables
     */
    public function supportsDroppingForeignKeys(): bool;

    /**
     * Get platform-specific connection initialization SQL
     * 
     * @return array<string> Array of SQL statements to execute after connection
     */
    public function getConnectionInitializationSQL(): array;

    /**
     * Get platform-specific default PDO options
     * 
     * @return array<int, mixed> PDO options array
     */
    public function getDefaultPDOOptions(): array;
}
