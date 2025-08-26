<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Migration\Schema;

use Fduarte42\Aurum\Connection\ConnectionInterface;

/**
 * Abstract base class for schema builders
 */
abstract class AbstractSchemaBuilder implements SchemaBuilderInterface
{
    public function __construct(
        protected readonly ConnectionInterface $connection
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function createTable(string $tableName): TableBuilderInterface
    {
        return $this->createTableBuilder($tableName, 'create');
    }

    /**
     * {@inheritdoc}
     */
    public function alterTable(string $tableName): TableBuilderInterface
    {
        return $this->createTableBuilder($tableName, 'alter');
    }

    /**
     * {@inheritdoc}
     */
    public function dropTable(string $tableName): void
    {
        $sql = "DROP TABLE " . $this->quoteIdentifier($tableName);
        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function renameTable(string $oldName, string $newName): void
    {
        $sql = "ALTER TABLE " . $this->quoteIdentifier($oldName) . " RENAME TO " . $this->quoteIdentifier($newName);
        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function hasTable(string $tableName): bool
    {
        $sql = $this->getTableExistsSQL();
        $result = $this->connection->fetchOne($sql, [$tableName]);
        return $result !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function createIndex(string $tableName, array $columns, ?string $indexName = null, array $options = []): void
    {
        $indexName = $indexName ?: $this->generateIndexName($tableName, $columns);
        $unique = $options['unique'] ?? false;
        
        $sql = ($unique ? 'CREATE UNIQUE INDEX ' : 'CREATE INDEX ') 
             . $this->quoteIdentifier($indexName) 
             . ' ON ' . $this->quoteIdentifier($tableName) 
             . ' (' . implode(', ', array_map([$this, 'quoteIdentifier'], $columns)) . ')';
        
        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function dropIndex(string $tableName, string $indexName): void
    {
        $sql = $this->getDropIndexSQL($tableName, $indexName);
        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function hasIndex(string $tableName, string $indexName): bool
    {
        $sql = $this->getIndexExistsSQL();
        $result = $this->connection->fetchOne($sql, [$tableName, $indexName]);
        return $result !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function addForeignKey(string $tableName, array $columns, string $referencedTable, array $referencedColumns, array $options = []): void
    {
        $constraintName = $options['name'] ?? $this->generateForeignKeyName($tableName, $columns);
        $onDelete = $options['on_delete'] ?? 'RESTRICT';
        $onUpdate = $options['on_update'] ?? 'RESTRICT';

        $sql = "ALTER TABLE " . $this->quoteIdentifier($tableName) 
             . " ADD CONSTRAINT " . $this->quoteIdentifier($constraintName)
             . " FOREIGN KEY (" . implode(', ', array_map([$this, 'quoteIdentifier'], $columns)) . ")"
             . " REFERENCES " . $this->quoteIdentifier($referencedTable) 
             . " (" . implode(', ', array_map([$this, 'quoteIdentifier'], $referencedColumns)) . ")"
             . " ON DELETE {$onDelete} ON UPDATE {$onUpdate}";

        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function dropForeignKey(string $tableName, string $constraintName): void
    {
        $sql = "ALTER TABLE " . $this->quoteIdentifier($tableName) 
             . " DROP FOREIGN KEY " . $this->quoteIdentifier($constraintName);
        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function execute(string $sql, array $parameters = []): void
    {
        $this->connection->execute($sql, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function getPlatform(): string
    {
        return $this->connection->getPlatform();
    }

    /**
     * Quote an identifier
     */
    protected function quoteIdentifier(string $identifier): string
    {
        return $this->connection->quoteIdentifier($identifier);
    }

    /**
     * Generate an index name
     */
    protected function generateIndexName(string $tableName, array $columns): string
    {
        return 'idx_' . $tableName . '_' . implode('_', $columns);
    }

    /**
     * Generate a foreign key constraint name
     */
    protected function generateForeignKeyName(string $tableName, array $columns): string
    {
        return 'fk_' . $tableName . '_' . implode('_', $columns);
    }

    /**
     * Create a table builder instance
     */
    abstract protected function createTableBuilder(string $tableName, string $operation): TableBuilderInterface;

    /**
     * Get SQL to check if table exists
     */
    abstract protected function getTableExistsSQL(): string;

    /**
     * Get SQL to check if index exists
     */
    abstract protected function getIndexExistsSQL(): string;

    /**
     * Get SQL to drop an index
     */
    abstract protected function getDropIndexSQL(string $tableName, string $indexName): string;
}
