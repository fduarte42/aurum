<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Migration;

/**
 * Interface for finding migration classes
 */
interface MigrationFinderInterface
{
    /**
     * Find all available migration versions
     * 
     * @return array<string> Array of migration versions sorted by version
     */
    public function findVersions(): array;

    /**
     * Find migrations that have not been executed yet
     * 
     * @return array<string> Array of pending migration versions
     */
    public function findPendingVersions(): array;

    /**
     * Load a migration instance by version
     */
    public function loadMigration(string $version): MigrationInterface;

    /**
     * Check if a migration version exists
     */
    public function hasVersion(string $version): bool;

    /**
     * Get all available migrations with their details
     * 
     * @return array<array{version: string, class: string, description: string, executed: bool}>
     */
    public function getMigrationDetails(): array;
}
