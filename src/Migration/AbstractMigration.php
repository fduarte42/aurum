<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Migration;

use Fduarte42\Aurum\Connection\ConnectionInterface;
use Fduarte42\Aurum\Migration\Schema\SchemaBuilderInterface;
use Fduarte42\Aurum\Migration\Schema\SchemaBuilderFactory;

/**
 * Abstract base class for migrations providing common functionality
 */
abstract class AbstractMigration implements MigrationInterface
{
    protected ConnectionInterface $connection;
    protected SchemaBuilderInterface $schemaBuilder;

    public function __construct()
    {
        // Connection and schema builder will be injected during execution
    }

    /**
     * Set the connection and initialize schema builder
     * This is called by the migration executor before running the migration
     */
    public function setConnection(ConnectionInterface $connection): void
    {
        $this->connection = $connection;
        $this->schemaBuilder = SchemaBuilderFactory::create($connection);
    }

    /**
     * {@inheritdoc}
     */
    public function isTransactional(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getDependencies(): array
    {
        return [];
    }

    /**
     * Execute a raw SQL statement
     */
    protected function addSql(string $sql, array $parameters = []): void
    {
        $this->connection->execute($sql, $parameters);
    }

    /**
     * Check if a table exists
     */
    protected function tableExists(string $tableName): bool
    {
        $platform = $this->connection->getPlatform();
        
        if ($platform === 'sqlite') {
            $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name = ?";
        } else {
            // MariaDB/MySQL
            $sql = "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?";
        }
        
        $result = $this->connection->fetchOne($sql, [$tableName]);
        return $result !== null;
    }

    /**
     * Check if a column exists in a table
     */
    protected function columnExists(string $tableName, string $columnName): bool
    {
        $platform = $this->connection->getPlatform();
        
        if ($platform === 'sqlite') {
            $sql = "PRAGMA table_info({$tableName})";
            $columns = $this->connection->fetchAll($sql);
            foreach ($columns as $column) {
                if ($column['name'] === $columnName) {
                    return true;
                }
            }
            return false;
        } else {
            // MariaDB/MySQL
            $sql = "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
            $result = $this->connection->fetchOne($sql, [$tableName, $columnName]);
            return $result !== null;
        }
    }

    /**
     * Check if an index exists
     */
    protected function indexExists(string $tableName, string $indexName): bool
    {
        $platform = $this->connection->getPlatform();
        
        if ($platform === 'sqlite') {
            $sql = "SELECT name FROM sqlite_master WHERE type='index' AND tbl_name = ? AND name = ?";
        } else {
            // MariaDB/MySQL
            $sql = "SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?";
        }
        
        $result = $this->connection->fetchOne($sql, [$tableName, $indexName]);
        return $result !== null;
    }

    /**
     * Write a message to output (can be overridden for custom output handling)
     */
    protected function write(string $message): void
    {
        // Default implementation - can be overridden by migration executor
        echo $message . PHP_EOL;
    }

    /**
     * Abort the migration with an error message
     */
    protected function abortIf(bool $condition, string $message): void
    {
        if ($condition) {
            throw new MigrationException($message);
        }
    }

    /**
     * Skip this migration if condition is true
     */
    protected function skipIf(bool $condition, string $message): void
    {
        if ($condition) {
            throw new SkipMigrationException($message);
        }
    }

    /**
     * Warn about potential issues
     */
    protected function warnIf(bool $condition, string $message): void
    {
        if ($condition) {
            $this->write("WARNING: {$message}");
        }
    }
}
