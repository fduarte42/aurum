<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Migration\Schema;

/**
 * SQLite-specific table builder
 */
class SqliteTableBuilder extends AbstractTableBuilder
{
    /**
     * {@inheritdoc}
     */
    public function create(): void
    {
        $sql = $this->buildCreateTableSQL();
        $this->execute($sql);

        // Create indexes after table creation
        foreach ($this->indexes as $name => $index) {
            $this->createIndex($name, $index);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function alter(): void
    {
        $statements = $this->buildAlterTableSQL();
        foreach ($statements as $sql) {
            $this->execute($sql);
        }

        // Handle indexes
        foreach ($this->dropIndexes as $indexName) {
            $this->execute("DROP INDEX IF EXISTS " . $this->quoteIdentifier($indexName));
        }

        foreach ($this->indexes as $name => $index) {
            $this->createIndex($name, $index);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function buildCreateTableSQL(): string
    {
        $sql = "CREATE TABLE " . $this->quoteIdentifier($this->tableName) . " (\n";
        
        $columnDefinitions = [];
        foreach ($this->columns as $name => $column) {
            $columnDefinitions[] = "    " . $this->buildColumnDefinition($name, $column);
        }

        // Add foreign key constraints
        foreach ($this->foreignKeys as $name => $foreignKey) {
            $columnDefinitions[] = "    " . $this->buildForeignKeyDefinition($name, $foreignKey);
        }

        $sql .= implode(",\n", $columnDefinitions);
        $sql .= "\n)";

        return $sql;
    }

    /**
     * {@inheritdoc}
     */
    protected function buildAlterTableSQL(): array
    {
        $statements = [];

        // Add columns
        foreach ($this->columns as $name => $column) {
            $statements[] = "ALTER TABLE " . $this->quoteIdentifier($this->tableName) 
                          . " ADD COLUMN " . $this->buildColumnDefinition($name, $column);
        }

        // SQLite has limited ALTER TABLE support
        // For complex operations like dropping columns, renaming columns, etc.,
        // we would need to recreate the table, which is complex and risky
        if (!empty($this->dropColumns) || !empty($this->renameColumns) || !empty($this->changeColumns)) {
            throw new \RuntimeException('SQLite does not support dropping, renaming, or changing columns. You need to recreate the table.');
        }

        return $statements;
    }

    /**
     * {@inheritdoc}
     */
    protected function buildColumnDefinition(string $name, array $column): string
    {
        $definition = $this->quoteIdentifier($name) . " " . $this->getSqlType($column['type'], $column['options']);

        $options = $column['options'];

        if ($options['primary_key'] ?? false) {
            $definition .= " PRIMARY KEY";
        }

        if ($options['auto_increment'] ?? false) {
            $definition .= " AUTOINCREMENT";
        }

        if ($options['not_null'] ?? false) {
            $definition .= " NOT NULL";
        }

        if (isset($options['default'])) {
            $definition .= " DEFAULT " . $this->formatDefaultValue($options['default']);
        }

        if ($options['unique'] ?? false) {
            $definition .= " UNIQUE";
        }

        return $definition;
    }



    /**
     * Build foreign key constraint definition
     */
    private function buildForeignKeyDefinition(string $name, array $foreignKey): string
    {
        $columns = implode(', ', array_map([$this, 'quoteIdentifier'], $foreignKey['columns']));
        $referencedColumns = implode(', ', array_map([$this, 'quoteIdentifier'], $foreignKey['referenced_columns']));
        
        $definition = "FOREIGN KEY ({$columns}) REFERENCES " 
                    . $this->quoteIdentifier($foreignKey['referenced_table']) 
                    . " ({$referencedColumns})";

        $options = $foreignKey['options'];
        if (isset($options['on_delete'])) {
            $definition .= " ON DELETE " . $options['on_delete'];
        }
        if (isset($options['on_update'])) {
            $definition .= " ON UPDATE " . $options['on_update'];
        }

        return $definition;
    }

    /**
     * Create an index
     */
    private function createIndex(string $name, array $index): void
    {
        $unique = $index['options']['unique'] ?? false;
        $columns = implode(', ', array_map([$this, 'quoteIdentifier'], $index['columns']));
        
        $sql = ($unique ? 'CREATE UNIQUE INDEX ' : 'CREATE INDEX ')
             . $this->quoteIdentifier($name)
             . ' ON ' . $this->quoteIdentifier($this->tableName)
             . ' (' . $columns . ')';

        $this->execute($sql);
    }

    /**
     * Format a default value for SQL
     */
    private function formatDefaultValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_string($value)) {
            return "'" . str_replace("'", "''", $value) . "'";
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return (string) $value;
    }
}
