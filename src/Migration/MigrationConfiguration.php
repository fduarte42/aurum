<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Migration;

/**
 * Configuration for the migration system
 */
class MigrationConfiguration
{
    private string $migrationsDirectory;
    private string $migrationsNamespace;
    private string $migrationTableName = 'aurum_migrations';
    private bool $isDryRun = false;
    private bool $isVerbose = false;
    private ?string $migrationTemplate = null;

    public function __construct(
        string $migrationsDirectory,
        string $migrationsNamespace = 'Migrations'
    ) {
        $this->migrationsDirectory = rtrim($migrationsDirectory, '/\\');
        $this->migrationsNamespace = trim($migrationsNamespace, '\\');
    }

    /**
     * Get the migrations directory path
     */
    public function getMigrationsDirectory(): string
    {
        return $this->migrationsDirectory;
    }

    /**
     * Set the migrations directory path
     */
    public function setMigrationsDirectory(string $directory): self
    {
        $this->migrationsDirectory = rtrim($directory, '/\\');
        return $this;
    }

    /**
     * Get the migrations namespace
     */
    public function getMigrationsNamespace(): string
    {
        return $this->migrationsNamespace;
    }

    /**
     * Set the migrations namespace
     */
    public function setMigrationsNamespace(string $namespace): self
    {
        $this->migrationsNamespace = trim($namespace, '\\');
        return $this;
    }

    /**
     * Get the migration table name
     */
    public function getMigrationTableName(): string
    {
        return $this->migrationTableName;
    }

    /**
     * Set the migration table name
     */
    public function setMigrationTableName(string $tableName): self
    {
        $this->migrationTableName = $tableName;
        return $this;
    }

    /**
     * Check if this is a dry run
     */
    public function isDryRun(): bool
    {
        return $this->isDryRun;
    }

    /**
     * Set dry run mode
     */
    public function setDryRun(bool $dryRun): self
    {
        $this->isDryRun = $dryRun;
        return $this;
    }

    /**
     * Check if verbose output is enabled
     */
    public function isVerbose(): bool
    {
        return $this->isVerbose;
    }

    /**
     * Set verbose output mode
     */
    public function setVerbose(bool $verbose): self
    {
        $this->isVerbose = $verbose;
        return $this;
    }

    /**
     * Get the migration template path
     */
    public function getMigrationTemplate(): ?string
    {
        return $this->migrationTemplate;
    }

    /**
     * Set the migration template path
     */
    public function setMigrationTemplate(?string $template): self
    {
        $this->migrationTemplate = $template;
        return $this;
    }

    /**
     * Get the full class name for a migration version
     */
    public function getMigrationClassName(string $version): string
    {
        return $this->migrationsNamespace . '\\Version' . $version;
    }

    /**
     * Get the file path for a migration version
     */
    public function getMigrationFilePath(string $version): string
    {
        return $this->migrationsDirectory . '/Version' . $version . '.php';
    }

    /**
     * Validate the configuration
     */
    public function validate(): void
    {
        if (!is_dir($this->migrationsDirectory)) {
            throw MigrationException::migrationDirectoryNotFound($this->migrationsDirectory);
        }

        if (!is_writable($this->migrationsDirectory)) {
            throw MigrationException::migrationDirectoryNotWritable($this->migrationsDirectory);
        }

        if (empty($this->migrationsNamespace)) {
            throw new MigrationException('Migrations namespace cannot be empty');
        }

        if (empty($this->migrationTableName)) {
            throw new MigrationException('Migration table name cannot be empty');
        }
    }

    /**
     * Create a default configuration
     */
    public static function createDefault(string $projectRoot): self
    {
        $migrationsDir = $projectRoot . '/migrations';
        
        // Create migrations directory if it doesn't exist
        if (!is_dir($migrationsDir)) {
            mkdir($migrationsDir, 0755, true);
        }

        return new self($migrationsDir, 'Migrations');
    }
}
