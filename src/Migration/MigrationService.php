<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Migration;

use Fduarte42\Aurum\Connection\ConnectionInterface;

/**
 * Migration service for dependency injection
 */
class MigrationService
{
    private MigrationManagerInterface $migrationManager;

    public function __construct(
        ConnectionInterface $connection,
        ?MigrationConfiguration $configuration = null
    ) {
        if ($configuration === null) {
            // Create default configuration
            $projectRoot = $this->detectProjectRoot();
            $configuration = MigrationConfiguration::createDefault($projectRoot);
        }

        $this->migrationManager = new MigrationManager($connection, $configuration);
    }

    /**
     * Get the migration manager
     */
    public function getMigrationManager(): MigrationManagerInterface
    {
        return $this->migrationManager;
    }

    /**
     * Generate a new migration
     */
    public function generate(string $description): string
    {
        return $this->migrationManager->generate($description);
    }

    /**
     * Run all pending migrations
     */
    public function migrate(): void
    {
        $this->migrationManager->migrate();
    }

    /**
     * Rollback the last migration
     */
    public function rollback(): void
    {
        $this->migrationManager->rollback();
    }

    /**
     * Get migration status
     */
    public function status(): array
    {
        return $this->migrationManager->status();
    }

    /**
     * List all migrations
     */
    public function list(): array
    {
        return $this->migrationManager->list();
    }

    /**
     * Set output writer for migration messages
     */
    public function setOutputWriter(callable $writer): self
    {
        $this->migrationManager->setOutputWriter($writer);
        return $this;
    }

    /**
     * Set verbose mode
     */
    public function setVerbose(bool $verbose): self
    {
        $this->migrationManager->setVerbose($verbose);
        return $this;
    }

    /**
     * Set dry run mode
     */
    public function setDryRun(bool $dryRun): self
    {
        $this->migrationManager->setDryRun($dryRun);
        return $this;
    }

    /**
     * Detect the project root directory
     */
    private function detectProjectRoot(): string
    {
        // Try to find composer.json to determine project root
        $currentDir = __DIR__;
        $maxLevels = 10;
        
        for ($i = 0; $i < $maxLevels; $i++) {
            if (file_exists($currentDir . '/composer.json')) {
                return $currentDir;
            }
            
            $parentDir = dirname($currentDir);
            if ($parentDir === $currentDir) {
                // Reached filesystem root
                break;
            }
            $currentDir = $parentDir;
        }

        // Fallback to current working directory
        return getcwd() ?: __DIR__;
    }

    /**
     * Create a migration service with custom configuration
     */
    public static function create(
        ConnectionInterface $connection,
        string $migrationsDirectory,
        string $migrationsNamespace = 'Migrations'
    ): self {
        $configuration = new MigrationConfiguration($migrationsDirectory, $migrationsNamespace);
        return new self($connection, $configuration);
    }
}
