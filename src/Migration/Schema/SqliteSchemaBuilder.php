<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Migration\Schema;

/**
 * SQLite-specific schema builder
 */
class SqliteSchemaBuilder extends AbstractSchemaBuilder
{
    /**
     * {@inheritdoc}
     */
    protected function createTableBuilder(string $tableName, string $operation): TableBuilderInterface
    {
        return new SqliteTableBuilder($this->connection, $tableName, $operation);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTableExistsSQL(): string
    {
        return "SELECT name FROM sqlite_master WHERE type='table' AND name = ?";
    }

    /**
     * {@inheritdoc}
     */
    protected function getIndexExistsSQL(): string
    {
        return "SELECT name FROM sqlite_master WHERE type='index' AND tbl_name = ? AND name = ?";
    }

    /**
     * {@inheritdoc}
     */
    protected function getDropIndexSQL(string $tableName, string $indexName): string
    {
        return "DROP INDEX " . $this->quoteIdentifier($indexName);
    }

    /**
     * {@inheritdoc}
     */
    public function dropForeignKey(string $tableName, string $constraintName): void
    {
        // SQLite doesn't support dropping foreign keys directly
        // This would require recreating the table, which is complex
        throw new \RuntimeException('SQLite does not support dropping foreign key constraints. You need to recreate the table.');
    }

    /**
     * {@inheritdoc}
     */
    public function addForeignKey(string $tableName, array $columns, string $referencedTable, array $referencedColumns, array $options = []): void
    {
        // SQLite doesn't support adding foreign keys to existing tables
        // Foreign keys must be defined during table creation
        throw new \RuntimeException('SQLite does not support adding foreign key constraints to existing tables. Define them during table creation.');
    }
}
