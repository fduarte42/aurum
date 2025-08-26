<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Schema;

use Fduarte42\Aurum\Connection\ConnectionInterface;

/**
 * Introspects database schema to read current structure
 */
class SchemaIntrospector
{
    public function __construct(
        private readonly ConnectionInterface $connection
    ) {
    }

    /**
     * Get all tables in the database
     */
    public function getTables(): array
    {
        $platform = $this->connection->getPlatform();
        
        if ($platform === 'sqlite') {
            return $this->getSqliteTables();
        } else {
            return $this->getMariaDbTables();
        }
    }

    /**
     * Get table structure including columns, indexes, and foreign keys
     */
    public function getTableStructure(string $tableName): array
    {
        return [
            'name' => $tableName,
            'columns' => $this->getTableColumns($tableName),
            'indexes' => $this->getTableIndexes($tableName),
            'foreign_keys' => $this->getTableForeignKeys($tableName)
        ];
    }

    /**
     * Get all columns for a table
     */
    public function getTableColumns(string $tableName): array
    {
        $platform = $this->connection->getPlatform();
        
        if ($platform === 'sqlite') {
            return $this->getSqliteTableColumns($tableName);
        } else {
            return $this->getMariaDbTableColumns($tableName);
        }
    }

    /**
     * Get all indexes for a table
     */
    public function getTableIndexes(string $tableName): array
    {
        $platform = $this->connection->getPlatform();
        
        if ($platform === 'sqlite') {
            return $this->getSqliteTableIndexes($tableName);
        } else {
            return $this->getMariaDbTableIndexes($tableName);
        }
    }

    /**
     * Get all foreign keys for a table
     */
    public function getTableForeignKeys(string $tableName): array
    {
        $platform = $this->connection->getPlatform();
        
        if ($platform === 'sqlite') {
            return $this->getSqliteTableForeignKeys($tableName);
        } else {
            return $this->getMariaDbTableForeignKeys($tableName);
        }
    }

    /**
     * Get SQLite tables
     */
    private function getSqliteTables(): array
    {
        $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'";
        $result = $this->connection->fetchAll($sql);
        
        return array_column($result, 'name');
    }

    /**
     * Get MariaDB tables
     */
    private function getMariaDbTables(): array
    {
        $sql = "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()";
        $result = $this->connection->fetchAll($sql);
        
        return array_column($result, 'TABLE_NAME');
    }

    /**
     * Get SQLite table columns
     */
    private function getSqliteTableColumns(string $tableName): array
    {
        $sql = "PRAGMA table_info({$tableName})";
        $result = $this->connection->fetchAll($sql);
        
        $columns = [];
        foreach ($result as $row) {
            $columns[] = [
                'name' => $row['name'],
                'type' => $this->normalizeSqliteType($row['type']),
                'nullable' => !$row['notnull'],
                'default' => $row['dflt_value'],
                'primary_key' => (bool)$row['pk'],
                'auto_increment' => $this->isSqliteAutoIncrement($tableName, $row['name']),
                'length' => $this->extractSqliteLength($row['type']),
                'precision' => $this->extractSqlitePrecision($row['type']),
                'scale' => $this->extractSqliteScale($row['type'])
            ];
        }
        
        return $columns;
    }

    /**
     * Get MariaDB table columns
     */
    private function getMariaDbTableColumns(string $tableName): array
    {
        $sql = "SELECT 
                    COLUMN_NAME,
                    DATA_TYPE,
                    IS_NULLABLE,
                    COLUMN_DEFAULT,
                    COLUMN_KEY,
                    EXTRA,
                    CHARACTER_MAXIMUM_LENGTH,
                    NUMERIC_PRECISION,
                    NUMERIC_SCALE
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                ORDER BY ORDINAL_POSITION";
        
        $result = $this->connection->fetchAll($sql, [$tableName]);
        
        $columns = [];
        foreach ($result as $row) {
            $columns[] = [
                'name' => $row['COLUMN_NAME'],
                'type' => $this->normalizeMariaDbType($row['DATA_TYPE']),
                'nullable' => $row['IS_NULLABLE'] === 'YES',
                'default' => $row['COLUMN_DEFAULT'],
                'primary_key' => $row['COLUMN_KEY'] === 'PRI',
                'auto_increment' => strpos($row['EXTRA'], 'auto_increment') !== false,
                'length' => $row['CHARACTER_MAXIMUM_LENGTH'],
                'precision' => $row['NUMERIC_PRECISION'],
                'scale' => $row['NUMERIC_SCALE']
            ];
        }
        
        return $columns;
    }

    /**
     * Get SQLite table indexes
     */
    private function getSqliteTableIndexes(string $tableName): array
    {
        $sql = "PRAGMA index_list({$tableName})";
        $result = $this->connection->fetchAll($sql);
        
        $indexes = [];
        foreach ($result as $row) {
            // Skip auto-generated primary key indexes
            if (strpos($row['name'], 'sqlite_autoindex') === 0) {
                continue;
            }
            
            $indexInfo = $this->connection->fetchAll("PRAGMA index_info({$row['name']})");
            $columns = array_column($indexInfo, 'name');
            
            $indexes[] = [
                'name' => $row['name'],
                'columns' => $columns,
                'unique' => (bool)$row['unique'],
                'primary' => false // SQLite primary keys are handled separately
            ];
        }
        
        return $indexes;
    }

    /**
     * Get MariaDB table indexes
     */
    private function getMariaDbTableIndexes(string $tableName): array
    {
        $sql = "SELECT 
                    INDEX_NAME,
                    COLUMN_NAME,
                    NON_UNIQUE,
                    SEQ_IN_INDEX
                FROM information_schema.STATISTICS 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                ORDER BY INDEX_NAME, SEQ_IN_INDEX";
        
        $result = $this->connection->fetchAll($sql, [$tableName]);
        
        $indexes = [];
        $currentIndex = null;
        
        foreach ($result as $row) {
            // Skip primary key index
            if ($row['INDEX_NAME'] === 'PRIMARY') {
                continue;
            }
            
            if ($currentIndex !== $row['INDEX_NAME']) {
                if ($currentIndex !== null) {
                    $indexes[] = $indexData;
                }
                
                $currentIndex = $row['INDEX_NAME'];
                $indexData = [
                    'name' => $row['INDEX_NAME'],
                    'columns' => [],
                    'unique' => !$row['NON_UNIQUE'],
                    'primary' => false
                ];
            }
            
            $indexData['columns'][] = $row['COLUMN_NAME'];
        }
        
        if ($currentIndex !== null) {
            $indexes[] = $indexData;
        }
        
        return $indexes;
    }

    /**
     * Get SQLite table foreign keys
     */
    private function getSqliteTableForeignKeys(string $tableName): array
    {
        $sql = "PRAGMA foreign_key_list({$tableName})";
        $result = $this->connection->fetchAll($sql);
        
        $foreignKeys = [];
        foreach ($result as $row) {
            $foreignKeys[] = [
                'name' => "fk_{$tableName}_{$row['from']}",
                'columns' => [$row['from']],
                'referenced_table' => $row['table'],
                'referenced_columns' => [$row['to']],
                'on_delete' => $row['on_delete'],
                'on_update' => $row['on_update']
            ];
        }
        
        return $foreignKeys;
    }

    /**
     * Get MariaDB table foreign keys
     */
    private function getMariaDbTableForeignKeys(string $tableName): array
    {
        $sql = "SELECT 
                    CONSTRAINT_NAME,
                    COLUMN_NAME,
                    REFERENCED_TABLE_NAME,
                    REFERENCED_COLUMN_NAME,
                    DELETE_RULE,
                    UPDATE_RULE
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = ? 
                AND REFERENCED_TABLE_NAME IS NOT NULL";
        
        $result = $this->connection->fetchAll($sql, [$tableName]);
        
        $foreignKeys = [];
        foreach ($result as $row) {
            $foreignKeys[] = [
                'name' => $row['CONSTRAINT_NAME'],
                'columns' => [$row['COLUMN_NAME']],
                'referenced_table' => $row['REFERENCED_TABLE_NAME'],
                'referenced_columns' => [$row['REFERENCED_COLUMN_NAME']],
                'on_delete' => $row['DELETE_RULE'],
                'on_update' => $row['UPDATE_RULE']
            ];
        }
        
        return $foreignKeys;
    }

    /**
     * Normalize SQLite type to standard type
     */
    private function normalizeSqliteType(string $type): string
    {
        $type = strtoupper($type);
        
        return match (true) {
            str_contains($type, 'INT') => 'integer',
            str_contains($type, 'TEXT') || str_contains($type, 'CHAR') => 'string',
            str_contains($type, 'REAL') || str_contains($type, 'FLOAT') || str_contains($type, 'DOUBLE') => 'float',
            str_contains($type, 'DECIMAL') || str_contains($type, 'NUMERIC') => 'decimal',
            str_contains($type, 'BOOL') => 'boolean',
            default => 'string'
        };
    }

    /**
     * Normalize MariaDB type to standard type
     */
    private function normalizeMariaDbType(string $type): string
    {
        return match ($type) {
            'int', 'integer', 'bigint', 'smallint', 'tinyint', 'mediumint' => 'integer',
            'varchar', 'char' => 'string',
            'text', 'longtext', 'mediumtext', 'tinytext' => 'text',
            'float', 'double', 'real' => 'float',
            'decimal', 'numeric' => 'decimal',
            'tinyint' => 'boolean', // Assuming tinyint(1) is boolean
            'datetime', 'timestamp' => 'datetime',
            'date' => 'date',
            'time' => 'time',
            'json' => 'json',
            default => 'string'
        };
    }

    /**
     * Check if SQLite column is auto increment
     */
    private function isSqliteAutoIncrement(string $tableName, string $columnName): bool
    {
        $sql = "SELECT sql FROM sqlite_master WHERE type='table' AND name=?";
        $result = $this->connection->fetchOne($sql, [$tableName]);
        
        if ($result) {
            return strpos(strtoupper($result['sql']), 'AUTOINCREMENT') !== false;
        }
        
        return false;
    }

    /**
     * Extract length from SQLite type definition
     */
    private function extractSqliteLength(string $type): ?int
    {
        if (preg_match('/\((\d+)\)/', $type, $matches)) {
            return (int)$matches[1];
        }
        
        return null;
    }

    /**
     * Extract precision from SQLite type definition
     */
    private function extractSqlitePrecision(string $type): ?int
    {
        if (preg_match('/\((\d+),\d+\)/', $type, $matches)) {
            return (int)$matches[1];
        }
        
        return null;
    }

    /**
     * Extract scale from SQLite type definition
     */
    private function extractSqliteScale(string $type): ?int
    {
        if (preg_match('/\(\d+,(\d+)\)/', $type, $matches)) {
            return (int)$matches[1];
        }
        
        return null;
    }
}
