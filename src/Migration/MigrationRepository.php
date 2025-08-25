<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Migration;

use Fduarte42\Aurum\Connection\ConnectionInterface;

/**
 * Repository for tracking executed migrations in the database
 */
class MigrationRepository implements MigrationRepositoryInterface
{
    private const TABLE_NAME = 'aurum_migrations';

    public function __construct(
        private readonly ConnectionInterface $connection
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function ensureMigrationTableExists(): void
    {
        if ($this->migrationTableExists()) {
            return;
        }

        $this->createMigrationTable();
    }

    /**
     * {@inheritdoc}
     */
    public function hasVersionBeenExecuted(string $version): bool
    {
        $this->ensureMigrationTableExists();

        $sql = "SELECT version FROM " . self::TABLE_NAME . " WHERE version = ?";
        $result = $this->connection->fetchOne($sql, [$version]);

        return $result !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function markVersionAsExecuted(string $version, string $description = '', float $executionTime = 0.0): void
    {
        $this->ensureMigrationTableExists();

        $sql = "INSERT INTO " . self::TABLE_NAME . " (version, description, executed_at, execution_time) VALUES (?, ?, ?, ?)";
        $this->connection->execute($sql, [
            $version,
            $description,
            date('Y-m-d H:i:s'),
            $executionTime
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function markVersionAsNotExecuted(string $version): void
    {
        $this->ensureMigrationTableExists();

        $sql = "DELETE FROM " . self::TABLE_NAME . " WHERE version = ?";
        $this->connection->execute($sql, [$version]);
    }

    /**
     * {@inheritdoc}
     */
    public function getExecutedVersions(): array
    {
        $this->ensureMigrationTableExists();

        $sql = "SELECT version FROM " . self::TABLE_NAME . " ORDER BY id ASC";
        $results = $this->connection->fetchAll($sql);

        return array_column($results, 'version');
    }

    /**
     * {@inheritdoc}
     */
    public function getExecutionDetails(): array
    {
        $this->ensureMigrationTableExists();

        $sql = "SELECT version, description, executed_at, execution_time FROM " . self::TABLE_NAME . " ORDER BY id ASC";
        return $this->connection->fetchAll($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function getLatestVersion(): ?string
    {
        $this->ensureMigrationTableExists();

        $sql = "SELECT version FROM " . self::TABLE_NAME . " ORDER BY id DESC LIMIT 1";
        $result = $this->connection->fetchOne($sql);

        return $result ? $result['version'] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function clearMigrationTable(): void
    {
        $this->ensureMigrationTableExists();

        $sql = "DELETE FROM " . self::TABLE_NAME;
        $this->connection->execute($sql);
    }

    /**
     * Check if the migration table exists
     */
    private function migrationTableExists(): bool
    {
        $platform = $this->connection->getPlatform();

        if ($platform === 'sqlite') {
            $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name = ?";
        } else {
            // MariaDB/MySQL
            $sql = "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?";
        }

        $result = $this->connection->fetchOne($sql, [self::TABLE_NAME]);
        return $result !== null;
    }

    /**
     * Create the migration tracking table
     */
    private function createMigrationTable(): void
    {
        $platform = $this->connection->getPlatform();

        if ($platform === 'sqlite') {
            $sql = "
                CREATE TABLE " . self::TABLE_NAME . " (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    version TEXT UNIQUE NOT NULL,
                    description TEXT NOT NULL DEFAULT '',
                    executed_at TEXT NOT NULL,
                    execution_time REAL NOT NULL DEFAULT 0.0
                )
            ";
        } else {
            // MariaDB/MySQL
            $sql = "
                CREATE TABLE " . self::TABLE_NAME . " (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    version VARCHAR(255) UNIQUE NOT NULL,
                    description TEXT NOT NULL DEFAULT '',
                    executed_at DATETIME NOT NULL,
                    execution_time DECIMAL(10,3) NOT NULL DEFAULT 0.000
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
        }

        $this->connection->execute($sql);
    }
}
