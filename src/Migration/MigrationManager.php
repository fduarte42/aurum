<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Migration;

use Fduarte42\Aurum\Connection\ConnectionInterface;

/**
 * Main migration manager that coordinates all migration operations
 */
class MigrationManager implements MigrationManagerInterface
{
    private MigrationRepositoryInterface $repository;
    private MigrationFinderInterface $finder;
    private MigrationExecutorInterface $executor;
    private MigrationGeneratorInterface $generator;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly MigrationConfiguration $configuration
    ) {
        $this->repository = new MigrationRepository($connection);
        $this->finder = new MigrationFinder($configuration, $this->repository);
        $this->executor = new MigrationExecutor($connection, $this->repository, $this->finder, $configuration);
        $this->generator = new MigrationGenerator($configuration);
    }

    /**
     * {@inheritdoc}
     */
    public function generate(string $description): string
    {
        return $this->generator->generate($description);
    }

    /**
     * {@inheritdoc}
     */
    public function migrate(): void
    {
        $this->repository->ensureMigrationTableExists();
        $this->executor->migrateToLatest();
    }

    /**
     * {@inheritdoc}
     */
    public function migrateToVersion(string $version): void
    {
        $this->repository->ensureMigrationTableExists();
        $this->executor->migrateToVersion($version);
    }

    /**
     * {@inheritdoc}
     */
    public function rollback(): void
    {
        $this->repository->ensureMigrationTableExists();
        $this->executor->rollbackLast();
    }

    /**
     * {@inheritdoc}
     */
    public function rollbackToVersion(string $version): void
    {
        $this->repository->ensureMigrationTableExists();
        $this->executor->rollbackToVersion($version);
    }

    /**
     * {@inheritdoc}
     */
    public function status(): array
    {
        $this->repository->ensureMigrationTableExists();
        return $this->executor->getStatus();
    }

    /**
     * {@inheritdoc}
     */
    public function list(): array
    {
        $this->repository->ensureMigrationTableExists();
        return $this->finder->getMigrationDetails();
    }

    /**
     * {@inheritdoc}
     */
    public function reset(): void
    {
        $this->repository->ensureMigrationTableExists();
        $this->repository->clearMigrationTable();
    }

    /**
     * {@inheritdoc}
     */
    public function setDryRun(bool $dryRun): self
    {
        $this->configuration->setDryRun($dryRun);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setVerbose(bool $verbose): self
    {
        $this->configuration->setVerbose($verbose);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setOutputWriter(callable $writer): self
    {
        $this->executor->setOutputWriter($writer);
        return $this;
    }

    /**
     * Get the migration repository
     */
    public function getRepository(): MigrationRepositoryInterface
    {
        return $this->repository;
    }

    /**
     * Get the migration finder
     */
    public function getFinder(): MigrationFinderInterface
    {
        return $this->finder;
    }

    /**
     * Get the migration executor
     */
    public function getExecutor(): MigrationExecutorInterface
    {
        return $this->executor;
    }

    /**
     * Get the migration generator
     */
    public function getGenerator(): MigrationGeneratorInterface
    {
        return $this->generator;
    }

    /**
     * Get the migration configuration
     */
    public function getConfiguration(): MigrationConfiguration
    {
        return $this->configuration;
    }

    /**
     * Create a migration manager with default configuration
     */
    public static function create(ConnectionInterface $connection, string $projectRoot): self
    {
        $configuration = MigrationConfiguration::createDefault($projectRoot);
        return new self($connection, $configuration);
    }
}
