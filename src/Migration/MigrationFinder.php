<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Migration;

use DirectoryIterator;
use ReflectionClass;

/**
 * Finds and loads migration classes from the filesystem
 */
class MigrationFinder implements MigrationFinderInterface
{
    public function __construct(
        private readonly MigrationConfiguration $configuration,
        private readonly MigrationRepositoryInterface $repository
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function findVersions(): array
    {
        $versions = [];
        $directory = $this->configuration->getMigrationsDirectory();

        if (!is_dir($directory)) {
            return [];
        }

        $iterator = new DirectoryIterator($directory);
        foreach ($iterator as $file) {
            if ($file->isDot() || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $filename = $file->getBasename('.php');
            if (preg_match('/^Version(\d{14})$/', $filename, $matches)) {
                $versions[] = $matches[1];
            }
        }

        sort($versions);
        return $versions;
    }

    /**
     * {@inheritdoc}
     */
    public function findPendingVersions(): array
    {
        $allVersions = $this->findVersions();
        $executedVersions = $this->repository->getExecutedVersions();

        return array_diff($allVersions, $executedVersions);
    }

    /**
     * {@inheritdoc}
     */
    public function loadMigration(string $version): MigrationInterface
    {
        $filePath = $this->configuration->getMigrationFilePath($version);

        if (!file_exists($filePath)) {
            throw MigrationException::migrationNotFound($version);
        }

        require_once $filePath;

        // Find the actual class name in the file (it might have a unique suffix for tests)
        $className = $this->findMigrationClassInFile($filePath);

        if (!$className || !class_exists($className)) {
            throw MigrationException::migrationClassNotFound($className ?: 'Unknown');
        }

        $reflection = new ReflectionClass($className);
        if (!$reflection->implementsInterface(MigrationInterface::class)) {
            throw MigrationException::invalidMigrationClass($className);
        }

        $migration = $reflection->newInstance();
        if (!$migration instanceof MigrationInterface) {
            throw MigrationException::invalidMigrationClass($className);
        }

        return $migration;
    }

    /**
     * {@inheritdoc}
     */
    public function hasVersion(string $version): bool
    {
        $filePath = $this->configuration->getMigrationFilePath($version);
        return file_exists($filePath);
    }

    /**
     * {@inheritdoc}
     */
    public function getMigrationDetails(): array
    {
        $versions = $this->findVersions();
        $executedVersions = $this->repository->getExecutedVersions();
        $details = [];

        foreach ($versions as $version) {
            try {
                $migration = $this->loadMigration($version);
                $details[] = [
                    'version' => $version,
                    'class' => $this->configuration->getMigrationClassName($version),
                    'description' => $migration->getDescription(),
                    'executed' => in_array($version, $executedVersions, true)
                ];
            } catch (MigrationException $e) {
                $details[] = [
                    'version' => $version,
                    'class' => $this->configuration->getMigrationClassName($version),
                    'description' => 'Error loading migration: ' . $e->getMessage(),
                    'executed' => false
                ];
            }
        }

        return $details;
    }

    /**
     * Validate migration dependencies
     * 
     * @param array<string> $versions
     * @return array<string> Sorted versions respecting dependencies
     */
    public function sortVersionsByDependencies(array $versions): array
    {
        $sorted = [];
        $visiting = [];
        $visited = [];

        foreach ($versions as $version) {
            $this->visitVersion($version, $versions, $visiting, $visited, $sorted);
        }

        return $sorted;
    }

    /**
     * Depth-first search for dependency resolution
     */
    private function visitVersion(string $version, array $allVersions, array &$visiting, array &$visited, array &$sorted): void
    {
        if (in_array($version, $visited, true)) {
            return;
        }

        if (in_array($version, $visiting, true)) {
            throw MigrationException::circularDependency($version);
        }

        $visiting[] = $version;

        try {
            $migration = $this->loadMigration($version);
            $dependencies = $migration->getDependencies();

            foreach ($dependencies as $dependency) {
                if (!in_array($dependency, $allVersions, true)) {
                    throw MigrationException::dependencyNotMet($version, $dependency);
                }
                $this->visitVersion($dependency, $allVersions, $visiting, $visited, $sorted);
            }
        } catch (MigrationException $e) {
            // Re-throw dependency and circular dependency exceptions
            if (strpos($e->getMessage(), 'depends on') !== false ||
                strpos($e->getMessage(), 'Circular dependency') !== false) {
                throw $e;
            }
            // If we can't load the migration for other reasons, just add it to the sorted list
            // The error will be handled when trying to execute it
        }

        $visiting = array_diff($visiting, [$version]);
        $visited[] = $version;
        $sorted[] = $version;
    }

    /**
     * Find the migration class name in a file
     */
    private function findMigrationClassInFile(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        // Extract namespace
        $namespace = null;
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = trim($matches[1]);
        }

        // Extract class name - look for any class that starts with "Version"
        if (preg_match('/class\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*(?:extends\s+[a-zA-Z_][a-zA-Z0-9_\\\\]*)?/', $content, $matches)) {
            $className = trim($matches[1]);
            // Only return classes that start with "Version"
            if (strpos($className, 'Version') === 0) {
                return $namespace ? $namespace . '\\' . $className : $className;
            }
        }

        return null;
    }
}
