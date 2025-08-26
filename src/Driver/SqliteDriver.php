<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Driver;

use PDO;

/**
 * SQLite database driver implementation
 * 
 * Handles SQLite-specific functionality including:
 * - SQLite-specific SQL syntax and limitations
 * - File-based and in-memory database connections
 * - SQLite PRAGMA settings
 * - Foreign key constraint handling
 */
class SqliteDriver extends AbstractDatabaseDriver
{
    public function getPlatform(): string
    {
        return 'sqlite';
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    public function supportsSavepoints(): bool
    {
        return true;
    }

    public function getTableExistsSQL(): string
    {
        return "SELECT name FROM sqlite_master WHERE type='table' AND name = ?";
    }

    public function getIndexExistsSQL(): string
    {
        return "SELECT name FROM sqlite_master WHERE type='index' AND tbl_name = ? AND name = ?";
    }

    public function getDropIndexSQL(string $tableName, string $indexName): string
    {
        return "DROP INDEX " . $this->quoteIdentifier($indexName);
    }

    public function getSQLType(string $genericType, array $options = []): string
    {
        return match (strtoupper($genericType)) {
            'BOOLEAN' => 'INTEGER',
            'TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'INTEGER', 'BIGINT' => 'INTEGER',
            'DECIMAL', 'NUMERIC', 'FLOAT', 'DOUBLE', 'REAL' => 'REAL',
            'CHAR', 'VARCHAR', 'TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT' => 'TEXT',
            'BINARY', 'VARBINARY', 'TINYBLOB', 'BLOB', 'MEDIUMBLOB', 'LONGBLOB' => 'BLOB',
            'DATE', 'TIME', 'DATETIME', 'TIMESTAMP' => 'TEXT',
            'JSON' => 'TEXT',
            'UUID' => 'TEXT',
            default => 'TEXT',
        };
    }

    public function supportsForeignKeys(): bool
    {
        return true;
    }

    public function supportsAddingForeignKeys(): bool
    {
        // SQLite doesn't support adding foreign keys to existing tables
        return false;
    }

    public function supportsDroppingForeignKeys(): bool
    {
        // SQLite doesn't support dropping foreign keys from existing tables
        return false;
    }

    public function getConnectionInitializationSQL(): array
    {
        return [
            // Enable foreign key constraints
            'PRAGMA foreign_keys = ON',
            // Enable WAL mode for better concurrency (if not in-memory)
            // Note: This will be ignored for in-memory databases
            'PRAGMA journal_mode = WAL',
            // Set synchronous mode to NORMAL for better performance
            'PRAGMA synchronous = NORMAL',
            // Enable recursive triggers
            'PRAGMA recursive_triggers = ON',
        ];
    }

    public function getDefaultPDOOptions(): array
    {
        return array_merge(parent::getDefaultPDOOptions(), [
            // SQLite-specific options
            PDO::ATTR_TIMEOUT => 30,
        ]);
    }

    /**
     * Check if the database is in-memory
     */
    public function isInMemory(): bool
    {
        // Get the database file path from PDO
        $stmt = $this->pdo->query("PRAGMA database_list");
        $databases = $stmt->fetchAll();
        
        foreach ($databases as $db) {
            if ($db['name'] === 'main') {
                return $db['file'] === '' || $db['file'] === ':memory:';
            }
        }
        
        return false;
    }

    /**
     * Get SQLite version information
     */
    public function getVersion(): string
    {
        $stmt = $this->pdo->query("SELECT sqlite_version()");
        return $stmt->fetchColumn();
    }

    /**
     * Enable or disable foreign key constraints
     */
    public function setForeignKeyConstraints(bool $enabled): void
    {
        $value = $enabled ? 'ON' : 'OFF';
        $this->pdo->exec("PRAGMA foreign_keys = {$value}");
    }

    /**
     * Get current foreign key constraint setting
     */
    public function getForeignKeyConstraints(): bool
    {
        $stmt = $this->pdo->query("PRAGMA foreign_keys");
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Optimize the database by running VACUUM
     */
    public function vacuum(): void
    {
        if (!$this->isInMemory()) {
            $this->pdo->exec('VACUUM');
        }
    }

    /**
     * Analyze the database for query optimization
     */
    public function analyze(): void
    {
        $this->pdo->exec('ANALYZE');
    }

    /**
     * Get database integrity check results
     */
    public function integrityCheck(): array
    {
        $stmt = $this->pdo->query("PRAGMA integrity_check");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get foreign key check results
     */
    public function foreignKeyCheck(?string $tableName = null): array
    {
        $sql = $tableName 
            ? "PRAGMA foreign_key_check({$this->quoteIdentifier($tableName)})"
            : "PRAGMA foreign_key_check";
            
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get table information
     */
    public function getTableInfo(string $tableName): array
    {
        $sql = "PRAGMA table_info({$this->quoteIdentifier($tableName)})";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get index information for a table
     */
    public function getIndexList(string $tableName): array
    {
        $sql = "PRAGMA index_list({$this->quoteIdentifier($tableName)})";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get foreign key information for a table
     */
    public function getForeignKeyList(string $tableName): array
    {
        $sql = "PRAGMA foreign_key_list({$this->quoteIdentifier($tableName)})";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }
}
