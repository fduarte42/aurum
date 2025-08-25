<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Migration\Schema;

/**
 * Interface for database schema builder
 */
interface SchemaBuilderInterface
{
    /**
     * Create a new table
     */
    public function createTable(string $tableName): TableBuilderInterface;

    /**
     * Alter an existing table
     */
    public function alterTable(string $tableName): TableBuilderInterface;

    /**
     * Drop a table
     */
    public function dropTable(string $tableName): void;

    /**
     * Rename a table
     */
    public function renameTable(string $oldName, string $newName): void;

    /**
     * Check if a table exists
     */
    public function hasTable(string $tableName): bool;

    /**
     * Create an index
     */
    public function createIndex(string $tableName, array $columns, string $indexName = null, array $options = []): void;

    /**
     * Drop an index
     */
    public function dropIndex(string $tableName, string $indexName): void;

    /**
     * Check if an index exists
     */
    public function hasIndex(string $tableName, string $indexName): bool;

    /**
     * Add a foreign key constraint
     */
    public function addForeignKey(string $tableName, array $columns, string $referencedTable, array $referencedColumns, array $options = []): void;

    /**
     * Drop a foreign key constraint
     */
    public function dropForeignKey(string $tableName, string $constraintName): void;

    /**
     * Execute raw SQL
     */
    public function execute(string $sql, array $parameters = []): void;

    /**
     * Get the database platform
     */
    public function getPlatform(): string;
}
