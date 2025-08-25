<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Migration;

/**
 * Interface for executing migrations
 */
interface MigrationExecutorInterface
{
    /**
     * Execute a single migration
     */
    public function execute(MigrationInterface $migration, string $direction = 'up'): void;

    /**
     * Execute multiple migrations
     * 
     * @param array<MigrationInterface> $migrations
     */
    public function executeMigrations(array $migrations, string $direction = 'up'): void;

    /**
     * Migrate to a specific version
     */
    public function migrateToVersion(string $version): void;

    /**
     * Migrate up to the latest version
     */
    public function migrateToLatest(): void;

    /**
     * Rollback to a specific version
     */
    public function rollbackToVersion(string $version): void;

    /**
     * Rollback the last migration
     */
    public function rollbackLast(): void;

    /**
     * Get migration status
     * 
     * @return array{current_version: ?string, pending_migrations: int, executed_migrations: int}
     */
    public function getStatus(): array;

    /**
     * Set output writer for migration messages
     */
    public function setOutputWriter(callable $writer): void;
}
