<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Migration\Schema;

/**
 * MariaDB/MySQL-specific table builder
 */
class MariaDbTableBuilder extends AbstractTableBuilder
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
            $this->execute("DROP INDEX " . $this->quoteIdentifier($indexName) . " ON " . $this->quoteIdentifier($this->tableName));
        }

        foreach ($this->indexes as $name => $index) {
            $this->createIndex($name, $index);
        }

        // Handle foreign keys
        foreach ($this->dropForeignKeys as $constraintName) {
            $this->execute("ALTER TABLE " . $this->quoteIdentifier($this->tableName) . " DROP FOREIGN KEY " . $this->quoteIdentifier($constraintName));
        }

        foreach ($this->foreignKeys as $name => $foreignKey) {
            $this->addForeignKeyConstraint($name, $foreignKey);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function buildCreateTableSQL(): string
    {
        $sql = "CREATE TABLE " . $this->quoteIdentifier($this->tableName) . " (\n";
        
        $columnDefinitions = [];
        $primaryKeys = [];

        foreach ($this->columns as $name => $column) {
            $columnDefinitions[] = "    " . $this->buildColumnDefinition($name, $column);
            if ($column['options']['primary_key'] ?? false) {
                $primaryKeys[] = $name;
            }
        }

        // Add primary key constraint if multiple columns
        if (count($primaryKeys) > 1) {
            $columnDefinitions[] = "    PRIMARY KEY (" . implode(', ', array_map([$this, 'quoteIdentifier'], $primaryKeys)) . ")";
        }

        // Add unique constraints
        foreach ($this->indexes as $name => $index) {
            if ($index['options']['unique'] ?? false) {
                $columns = implode(', ', array_map([$this, 'quoteIdentifier'], $index['columns']));
                $columnDefinitions[] = "    UNIQUE KEY " . $this->quoteIdentifier($name) . " ({$columns})";
            }
        }

        // Add foreign key constraints
        foreach ($this->foreignKeys as $name => $foreignKey) {
            $columnDefinitions[] = "    " . $this->buildForeignKeyDefinition($name, $foreignKey);
        }

        $sql .= implode(",\n", $columnDefinitions);
        $sql .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

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

        // Drop columns
        foreach ($this->dropColumns as $columnName) {
            $statements[] = "ALTER TABLE " . $this->quoteIdentifier($this->tableName) 
                          . " DROP COLUMN " . $this->quoteIdentifier($columnName);
        }

        // Rename columns
        foreach ($this->renameColumns as $oldName => $newName) {
            // MariaDB 10.5.2+ supports RENAME COLUMN, older versions need CHANGE
            $statements[] = "ALTER TABLE " . $this->quoteIdentifier($this->tableName) 
                          . " RENAME COLUMN " . $this->quoteIdentifier($oldName) 
                          . " TO " . $this->quoteIdentifier($newName);
        }

        // Change columns
        foreach ($this->changeColumns as $name => $column) {
            $statements[] = "ALTER TABLE " . $this->quoteIdentifier($this->tableName) 
                          . " MODIFY COLUMN " . $this->buildColumnDefinition($name, $column);
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

        if ($options['not_null'] ?? false) {
            $definition .= " NOT NULL";
        } else {
            $definition .= " NULL";
        }

        if ($options['auto_increment'] ?? false) {
            $definition .= " AUTO_INCREMENT";
        }

        if (isset($options['default'])) {
            $definition .= " DEFAULT " . $this->formatDefaultValue($options['default']);
        }

        if (($options['primary_key'] ?? false) && count($this->columns) === 1) {
            $definition .= " PRIMARY KEY";
        }

        return $definition;
    }

    /**
     * {@inheritdoc}
     */
    protected function getSqlType(string $type, array $options): string
    {
        return match ($type) {
            'integer' => 'INT',
            'string' => 'VARCHAR(' . ($options['length'] ?? 255) . ')',
            'text' => 'TEXT',
            'boolean' => 'TINYINT(1)',
            'decimal' => 'DECIMAL(' . ($options['precision'] ?? 10) . ',' . ($options['scale'] ?? 2) . ')',
            'datetime' => 'DATETIME',
            'uuid' => 'CHAR(36)',
            default => 'TEXT'
        };
    }

    /**
     * Build foreign key constraint definition
     */
    private function buildForeignKeyDefinition(string $name, array $foreignKey): string
    {
        $columns = implode(', ', array_map([$this, 'quoteIdentifier'], $foreignKey['columns']));
        $referencedColumns = implode(', ', array_map([$this, 'quoteIdentifier'], $foreignKey['referenced_columns']));
        
        $definition = "CONSTRAINT " . $this->quoteIdentifier($name) 
                    . " FOREIGN KEY ({$columns}) REFERENCES " 
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
        if ($index['options']['unique'] ?? false) {
            // Unique indexes are handled in CREATE TABLE
            return;
        }

        $columns = implode(', ', array_map([$this, 'quoteIdentifier'], $index['columns']));
        
        $sql = 'CREATE INDEX ' . $this->quoteIdentifier($name)
             . ' ON ' . $this->quoteIdentifier($this->tableName)
             . ' (' . $columns . ')';

        $this->execute($sql);
    }

    /**
     * Add a foreign key constraint
     */
    private function addForeignKeyConstraint(string $name, array $foreignKey): void
    {
        $columns = implode(', ', array_map([$this, 'quoteIdentifier'], $foreignKey['columns']));
        $referencedColumns = implode(', ', array_map([$this, 'quoteIdentifier'], $foreignKey['referenced_columns']));
        
        $sql = "ALTER TABLE " . $this->quoteIdentifier($this->tableName)
             . " ADD CONSTRAINT " . $this->quoteIdentifier($name)
             . " FOREIGN KEY ({$columns}) REFERENCES " 
             . $this->quoteIdentifier($foreignKey['referenced_table']) 
             . " ({$referencedColumns})";

        $options = $foreignKey['options'];
        if (isset($options['on_delete'])) {
            $sql .= " ON DELETE " . $options['on_delete'];
        }
        if (isset($options['on_update'])) {
            $sql .= " ON UPDATE " . $options['on_update'];
        }

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
