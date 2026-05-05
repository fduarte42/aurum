<?php
/**
 * Aurum ORM
 *
 * PHP version 8.4
 *
 * @category CLI
 * @package  Aurum
 * @author   Fduarte42 <fduarte42@example.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/fduarte42/aurum
 */

declare(strict_types=1);

namespace Fduarte42\Aurum\Cli\Command;

use Fduarte42\Aurum\Cli\AbstractCommand;
use Fduarte42\Aurum\Migration\MigrationService;
use Throwable;

/**
 * Migration migrate command
 *
 * @category CLI
 * @package  Aurum
 * @author   Fduarte42 <fduarte42@example.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/fduarte42/aurum
 */
class MigrationMigrateCommand extends AbstractCommand
{
    /**
     * The migration service
     *
     * @var MigrationService
     */
    private MigrationService $_migrationService;

    /**
     * Initialize services
     *
     * @return void
     */
    protected function initializeServices(): void
    {
        parent::initializeServices();
        
        $this->_migrationService = new MigrationService($this->connection);
    }

    /**
     * Get command name
     *
     * @return string
     */
    public function getName(): string
    {
        return 'migration:migrate';
    }

    /**
     * Get command description
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Execute all pending database migrations';
    }

    /**
     * Get help text
     *
     * @return string
     */
    public function getHelp(): string
    {
        return "🚀 Aurum Migration Executor
==========================

Executes all pending migrations to bring the database schema up to date.

USAGE:
  php bin/aurum-cli.php migration migrate [options]

OPTIONS:
  --version=<version>   Migrate to a specific version
  --dry-run             Show what would be executed without applying changes
  --debug               Show detailed error information

EXAMPLES:
  # Execute all pending migrations
  php bin/aurum-cli.php migration migrate

  # Migrate to a specific version
  php bin/aurum-cli.php migration migrate --version=20250825201246

  # Preview migrations without executing them
  php bin/aurum-cli.php migration migrate --dry-run
";
    }

    /**
     * Execute the command
     *
     * @param array<string, mixed> $options Command line options
     *
     * @return int
     */
    public function execute(array $options): int
    {
        $this->initializeServices();

        // Setup output writer to show progress
        $this->_migrationService->setOutputWriter(
            function (string $message) {
                $this->info($message);
            }
        );

        if (isset($options['dry-run'])) {
            $this->_migrationService->setDryRun(true);
            $msg = "🔍 DRY RUN MODE - No changes will be applied to the database\n";
            $this->info($msg);
        }

        try {
            if (isset($options['version'])) {
                $version = $options['version'];
                if (!is_string($version)) {
                    throw new \InvalidArgumentException('Version must be a string');
                }
                $this->info("🚀 Migrating database to version {$version}...");
                $manager = $this->_migrationService->getMigrationManager();
                $manager->migrateToVersion($version);
            } else {
                $this->info("🚀 Executing all pending migrations...");
                $this->_migrationService->migrate();
            }

            $this->success("\n✅ Database migrations completed successfully!");
            return 0;
        } catch (Throwable $e) {
            $this->error("\n❌ Error during migration: " . $e->getMessage());
            if (isset($options['debug'])) {
                $this->error($e->getTraceAsString());
            }
            return 1;
        }
    }
}
