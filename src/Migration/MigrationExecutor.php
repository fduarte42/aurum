<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Migration;

use Fduarte42\Aurum\Connection\ConnectionInterface;
use Throwable;

/**
 * Executes database migrations
 */
class MigrationExecutor implements MigrationExecutorInterface
{
    private $outputWriter = null;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly MigrationRepositoryInterface $repository,
        private readonly MigrationFinderInterface $finder,
        private readonly MigrationConfiguration $configuration
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function execute(MigrationInterface $migration, string $direction = 'up'): void
    {
        $version = $migration->getVersion();
        $description = $migration->getDescription();

        $this->write("Executing migration {$version} ({$direction}): {$description}");

        // Check if migration should be skipped
        if ($direction === 'up' && $this->repository->hasVersionBeenExecuted($version)) {
            $this->write("Migration {$version} has already been executed, skipping");
            return;
        }

        if ($direction === 'down' && !$this->repository->hasVersionBeenExecuted($version)) {
            $this->write("Migration {$version} has not been executed, skipping");
            return;
        }

        // Validate dependencies for up migrations
        if ($direction === 'up') {
            $this->validateDependencies($migration);
        }

        // Set connection on migration if it's an AbstractMigration
        if ($migration instanceof AbstractMigration) {
            $migration->setConnection($this->connection);
        }

        $startTime = microtime(true);
        $success = false;
        $skipped = false;

        try {
            if ($this->configuration->isDryRun()) {
                $this->write("DRY RUN: Would execute migration {$version} ({$direction})");
                $success = true;
            } else {
                $this->executeMigrationInTransaction($migration, $direction);
                $success = true;
            }
        } catch (SkipMigrationException $e) {
            $this->write("Migration {$version} skipped: " . $e->getMessage());
            $skipped = true;
        } catch (Throwable $e) {
            $this->write("Migration {$version} failed: " . $e->getMessage());
            throw MigrationException::migrationFailed($version, $e->getMessage());
        }

        if ($success && !$this->configuration->isDryRun()) {
            $executionTime = microtime(true) - $startTime;

            if ($direction === 'up') {
                $this->repository->markVersionAsExecuted($version, $description, $executionTime);
            } else {
                $this->repository->markVersionAsNotExecuted($version);
            }

            $this->write("Migration {$version} executed successfully in " . round($executionTime, 3) . "s");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function executeMigrations(array $migrations, string $direction = 'up'): void
    {
        if (empty($migrations)) {
            $this->write("No migrations to execute");
            return;
        }

        $this->write("Executing " . count($migrations) . " migration(s) ({$direction})");

        foreach ($migrations as $migration) {
            $this->execute($migration, $direction);
        }

        $this->write("All migrations executed successfully");
    }

    /**
     * {@inheritdoc}
     */
    public function migrateToVersion(string $version): void
    {
        $currentVersion = $this->repository->getLatestVersion();
        $allVersions = $this->finder->findVersions();

        if (!in_array($version, $allVersions, true)) {
            throw MigrationException::migrationNotFound($version);
        }

        if ($currentVersion === null) {
            // Migrate up from the beginning
            $versionsToExecute = array_filter($allVersions, fn($v) => $v <= $version);
            $migrations = array_map(fn($v) => $this->finder->loadMigration($v), $versionsToExecute);
            $this->executeMigrations($migrations, 'up');
        } elseif ($version > $currentVersion) {
            // Migrate up
            $versionsToExecute = array_filter($allVersions, fn($v) => $v > $currentVersion && $v <= $version);
            $migrations = array_map(fn($v) => $this->finder->loadMigration($v), $versionsToExecute);
            $this->executeMigrations($migrations, 'up');
        } elseif ($version < $currentVersion) {
            // Migrate down
            $executedVersions = $this->repository->getExecutedVersions();
            $versionsToRollback = array_filter($executedVersions, fn($v) => $v > $version);
            rsort($versionsToRollback); // Rollback in reverse order
            $migrations = array_map(fn($v) => $this->finder->loadMigration($v), $versionsToRollback);
            $this->executeMigrations($migrations, 'down');
        } else {
            $this->write("Already at version {$version}");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function migrateToLatest(): void
    {
        $pendingVersions = $this->finder->findPendingVersions();
        
        if (empty($pendingVersions)) {
            $this->write("No pending migrations to execute");
            return;
        }

        // Sort by dependencies
        $sortedVersions = $this->finder->sortVersionsByDependencies($pendingVersions);
        $migrations = array_map(fn($v) => $this->finder->loadMigration($v), $sortedVersions);
        
        $this->executeMigrations($migrations, 'up');
    }

    /**
     * {@inheritdoc}
     */
    public function rollbackToVersion(string $version): void
    {
        $this->migrateToVersion($version);
    }

    /**
     * {@inheritdoc}
     */
    public function rollbackLast(): void
    {
        $latestVersion = $this->repository->getLatestVersion();
        
        if ($latestVersion === null) {
            $this->write("No migrations to rollback");
            return;
        }

        $migration = $this->finder->loadMigration($latestVersion);
        $this->execute($migration, 'down');
    }

    /**
     * {@inheritdoc}
     */
    public function getStatus(): array
    {
        $currentVersion = $this->repository->getLatestVersion();
        $allVersions = $this->finder->findVersions();
        $executedVersions = $this->repository->getExecutedVersions();
        $pendingVersions = $this->finder->findPendingVersions();

        return [
            'current_version' => $currentVersion,
            'pending_migrations' => count($pendingVersions),
            'executed_migrations' => count($executedVersions),
            'total_migrations' => count($allVersions)
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function setOutputWriter(callable $writer): void
    {
        $this->outputWriter = $writer;
    }

    /**
     * Execute migration within a transaction if it's transactional
     */
    private function executeMigrationInTransaction(MigrationInterface $migration, string $direction): void
    {
        if ($migration->isTransactional()) {
            $this->connection->beginTransaction();
            try {
                if ($direction === 'up') {
                    $migration->up($this->connection);
                } else {
                    $migration->down($this->connection);
                }
                $this->connection->commit();
            } catch (Throwable $e) {
                $this->connection->rollback();
                throw $e;
            }
        } else {
            if ($direction === 'up') {
                $migration->up($this->connection);
            } else {
                $migration->down($this->connection);
            }
        }
    }

    /**
     * Validate migration dependencies
     */
    private function validateDependencies(MigrationInterface $migration): void
    {
        $dependencies = $migration->getDependencies();
        $executedVersions = $this->repository->getExecutedVersions();

        foreach ($dependencies as $dependency) {
            if (!in_array($dependency, $executedVersions, true)) {
                throw MigrationException::dependencyNotMet($migration->getVersion(), $dependency);
            }
        }
    }

    /**
     * Write output message
     */
    private function write(string $message): void
    {
        if ($this->outputWriter !== null) {
            ($this->outputWriter)($message);
        } elseif ($this->configuration->isVerbose()) {
            echo $message . PHP_EOL;
        }
    }
}
