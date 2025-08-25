<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Migration;

/**
 * Interface for the main migration manager
 */
interface MigrationManagerInterface
{
    /**
     * Generate a new migration
     */
    public function generate(string $description): string;

    /**
     * Migrate to the latest version
     */
    public function migrate(): void;

    /**
     * Migrate to a specific version
     */
    public function migrateToVersion(string $version): void;

    /**
     * Rollback the last migration
     */
    public function rollback(): void;

    /**
     * Rollback to a specific version
     */
    public function rollbackToVersion(string $version): void;

    /**
     * Get migration status
     */
    public function status(): array;

    /**
     * List all migrations with their status
     */
    public function list(): array;

    /**
     * Reset all migrations (for testing)
     */
    public function reset(): void;

    /**
     * Set dry run mode
     */
    public function setDryRun(bool $dryRun): self;

    /**
     * Set verbose mode
     */
    public function setVerbose(bool $verbose): self;

    /**
     * Set output writer
     */
    public function setOutputWriter(callable $writer): self;
}
