<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Migration;

/**
 * Interface for migration repository that tracks executed migrations
 */
interface MigrationRepositoryInterface
{
    /**
     * Check if the migration tracking table exists and create it if not
     */
    public function ensureMigrationTableExists(): void;

    /**
     * Check if a migration has been executed
     */
    public function hasVersionBeenExecuted(string $version): bool;

    /**
     * Mark a migration as executed
     */
    public function markVersionAsExecuted(string $version, string $description = '', float $executionTime = 0.0): void;

    /**
     * Mark a migration as not executed (for rollbacks)
     */
    public function markVersionAsNotExecuted(string $version): void;

    /**
     * Get all executed migration versions
     * 
     * @return array<string> Array of executed migration versions
     */
    public function getExecutedVersions(): array;

    /**
     * Get migration execution details
     * 
     * @return array<array{version: string, description: string, executed_at: string, execution_time: float}>
     */
    public function getExecutionDetails(): array;

    /**
     * Get the latest executed migration version
     */
    public function getLatestVersion(): ?string;

    /**
     * Clear all migration records (for testing purposes)
     */
    public function clearMigrationTable(): void;
}
