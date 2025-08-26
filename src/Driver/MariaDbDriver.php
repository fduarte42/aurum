<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Driver;

use PDO;

/**
 * MariaDB/MySQL database driver implementation
 * 
 * Handles MariaDB-specific functionality including:
 * - MariaDB/MySQL-specific SQL syntax and features
 * - Connection parameters and charset handling
 * - Storage engine support
 * - Advanced MariaDB features
 */
class MariaDbDriver extends AbstractDatabaseDriver
{
    public function getPlatform(): string
    {
        return 'mariadb';
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    public function supportsSavepoints(): bool
    {
        return true;
    }

    public function getTableExistsSQL(): string
    {
        return "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?";
    }

    public function getIndexExistsSQL(): string
    {
        return "SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?";
    }

    public function getDropIndexSQL(string $tableName, string $indexName): string
    {
        return "DROP INDEX " . $this->quoteIdentifier($indexName) . " ON " . $this->quoteIdentifier($tableName);
    }

    public function getSQLType(string $genericType, array $options = []): string
    {
        $length = $options['length'] ?? null;
        $precision = $options['precision'] ?? null;
        $scale = $options['scale'] ?? null;
        $unsigned = $options['unsigned'] ?? false;

        $type = match (strtoupper($genericType)) {
            'BOOLEAN' => 'TINYINT(1)',
            'TINYINT' => 'TINYINT' . ($length ? "({$length})" : ''),
            'SMALLINT' => 'SMALLINT' . ($length ? "({$length})" : ''),
            'MEDIUMINT' => 'MEDIUMINT' . ($length ? "({$length})" : ''),
            'INT', 'INTEGER' => 'INT' . ($length ? "({$length})" : ''),
            'BIGINT' => 'BIGINT' . ($length ? "({$length})" : ''),
            'DECIMAL', 'NUMERIC' => $this->getDecimalType($precision, $scale),
            'FLOAT' => 'FLOAT' . ($precision && $scale ? "({$precision},{$scale})" : ''),
            'DOUBLE', 'REAL' => 'DOUBLE' . ($precision && $scale ? "({$precision},{$scale})" : ''),
            'CHAR' => 'CHAR' . ($length ? "({$length})" : '(255)'),
            'VARCHAR' => 'VARCHAR' . ($length ? "({$length})" : '(255)'),
            'TINYTEXT' => 'TINYTEXT',
            'TEXT' => 'TEXT',
            'MEDIUMTEXT' => 'MEDIUMTEXT',
            'LONGTEXT' => 'LONGTEXT',
            'BINARY' => 'BINARY' . ($length ? "({$length})" : '(255)'),
            'VARBINARY' => 'VARBINARY' . ($length ? "({$length})" : '(255)'),
            'TINYBLOB' => 'TINYBLOB',
            'BLOB' => 'BLOB',
            'MEDIUMBLOB' => 'MEDIUMBLOB',
            'LONGBLOB' => 'LONGBLOB',
            'DATE' => 'DATE',
            'TIME' => 'TIME',
            'DATETIME' => 'DATETIME',
            'TIMESTAMP' => 'TIMESTAMP',
            'YEAR' => 'YEAR',
            'JSON' => 'JSON',
            'UUID' => 'CHAR(36)',
            default => 'VARCHAR(255)',
        };

        // Add UNSIGNED modifier for numeric types
        if ($unsigned && in_array(strtoupper($genericType), [
            'TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'INTEGER', 'BIGINT',
            'DECIMAL', 'NUMERIC', 'FLOAT', 'DOUBLE', 'REAL'
        ])) {
            $type .= ' UNSIGNED';
        }

        return $type;
    }

    public function getConnectionInitializationSQL(): array
    {
        return [
            // Set charset and collation
            "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            // Set SQL mode for strict behavior
            "SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'",
            // Set timezone to UTC
            "SET time_zone = '+00:00'",
            // Disable autocommit for explicit transaction control
            "SET autocommit = 0",
        ];
    }

    public function getDefaultPDOOptions(): array
    {
        return array_merge(parent::getDefaultPDOOptions(), [
            // MariaDB/MySQL-specific options
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            PDO::MYSQL_ATTR_FOUND_ROWS => true,
        ]);
    }

    /**
     * Get MariaDB/MySQL version information
     */
    public function getVersion(): string
    {
        $stmt = $this->pdo->query("SELECT VERSION()");
        return $stmt->fetchColumn();
    }

    /**
     * Check if this is MariaDB (vs MySQL)
     */
    public function isMariaDB(): bool
    {
        return str_contains(strtolower($this->getVersion()), 'mariadb');
    }

    /**
     * Get current database name
     */
    public function getCurrentDatabase(): ?string
    {
        $stmt = $this->pdo->query("SELECT DATABASE()");
        return $stmt->fetchColumn() ?: null;
    }

    /**
     * Get current charset and collation
     */
    public function getCharsetInfo(): array
    {
        $stmt = $this->pdo->query("SELECT @@character_set_database, @@collation_database");
        $result = $stmt->fetch();
        
        return [
            'charset' => $result['@@character_set_database'],
            'collation' => $result['@@collation_database'],
        ];
    }

    /**
     * Get storage engines information
     */
    public function getStorageEngines(): array
    {
        $stmt = $this->pdo->query("SHOW ENGINES");
        return $stmt->fetchAll();
    }

    /**
     * Get table status information
     */
    public function getTableStatus(?string $tableName = null): array
    {
        $sql = "SHOW TABLE STATUS";
        if ($tableName) {
            $sql .= " LIKE " . $this->quote($tableName);
        }
        
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get column information for a table
     */
    public function getColumnInfo(string $tableName): array
    {
        $sql = "SHOW FULL COLUMNS FROM " . $this->quoteIdentifier($tableName);
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get index information for a table
     */
    public function getIndexInfo(string $tableName): array
    {
        $sql = "SHOW INDEX FROM " . $this->quoteIdentifier($tableName);
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get foreign key information for a table
     */
    public function getForeignKeyInfo(string $tableName): array
    {
        $sql = "
            SELECT 
                CONSTRAINT_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME,
                UPDATE_RULE,
                DELETE_RULE
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = ? 
                AND REFERENCED_TABLE_NAME IS NOT NULL
        ";
        
        $stmt = $this->execute($sql, [$tableName]);
        return $stmt->fetchAll();
    }

    /**
     * Optimize table(s)
     */
    public function optimizeTable(string ...$tableNames): array
    {
        $tables = array_map([$this, 'quoteIdentifier'], $tableNames);
        $sql = "OPTIMIZE TABLE " . implode(', ', $tables);
        
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Analyze table(s)
     */
    public function analyzeTable(string ...$tableNames): array
    {
        $tables = array_map([$this, 'quoteIdentifier'], $tableNames);
        $sql = "ANALYZE TABLE " . implode(', ', $tables);
        
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Check table(s)
     */
    public function checkTable(string ...$tableNames): array
    {
        $tables = array_map([$this, 'quoteIdentifier'], $tableNames);
        $sql = "CHECK TABLE " . implode(', ', $tables);
        
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get decimal type with precision and scale
     */
    private function getDecimalType(?int $precision, ?int $scale): string
    {
        $precision = $precision ?? 10;
        $scale = $scale ?? 2;

        return "DECIMAL({$precision},{$scale})";
    }
}
