<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit\Migration;

use Fduarte42\Aurum\Connection\ConnectionFactory;
use Fduarte42\Aurum\Connection\ConnectionInterface;
use Fduarte42\Aurum\Migration\AbstractMigration;
use Fduarte42\Aurum\Migration\MigrationConfiguration;
use Fduarte42\Aurum\Migration\MigrationException;
use Fduarte42\Aurum\Migration\MigrationExecutor;
use Fduarte42\Aurum\Migration\MigrationFinder;
use Fduarte42\Aurum\Migration\MigrationInterface;
use Fduarte42\Aurum\Migration\MigrationRepository;
use Fduarte42\Aurum\Migration\SkipMigrationException;
use PHPUnit\Framework\TestCase;

class MigrationExecutorTest extends TestCase
{
    private ConnectionInterface $connection;
    private MigrationRepository $repository;
    private MigrationFinder $finder;
    private MigrationConfiguration $configuration;
    private MigrationExecutor $executor;
    private string $tempDir;
    private array $outputMessages = [];

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/aurum_migration_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        $this->connection = ConnectionFactory::createSqliteConnection(':memory:');
        $this->configuration = new MigrationConfiguration($this->tempDir, 'TestMigrations');
        $this->repository = new MigrationRepository($this->connection);
        $this->finder = new MigrationFinder($this->configuration, $this->repository);
        $this->executor = new MigrationExecutor($this->connection, $this->repository, $this->finder, $this->configuration);

        $this->outputMessages = [];
        $this->executor->setOutputWriter(function (string $message) {
            $this->outputMessages[] = $message;
        });
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    public function testExecuteUpMigration(): void
    {
        $migration = $this->createTestMigration('20231201120000', 'Test migration');

        $this->executor->execute($migration, 'up');

        $this->assertTrue($this->repository->hasVersionBeenExecuted('20231201120000'));
        $this->assertContains('Executing migration 20231201120000 (up): Test migration', $this->outputMessages);

        // Check for success message (it contains execution time)
        $successMessages = array_filter($this->outputMessages, function($msg) {
            return strpos($msg, 'Migration 20231201120000 executed successfully') !== false;
        });
        $this->assertNotEmpty($successMessages);
    }

    public function testExecuteDownMigration(): void
    {
        $migration = $this->createTestMigration('20231201120000', 'Test migration');

        // First execute up to create the table
        $this->executor->execute($migration, 'up');

        // Clear output messages from up migration
        $this->outputMessages = [];

        $this->executor->execute($migration, 'down');

        $this->assertFalse($this->repository->hasVersionBeenExecuted('20231201120000'));
        $this->assertContains('Executing migration 20231201120000 (down): Test migration', $this->outputMessages);
    }

    public function testExecuteUpAlreadyExecuted(): void
    {
        $migration = $this->createTestMigration('20231201120000', 'Test migration');

        $this->repository->ensureMigrationTableExists();
        $this->repository->markVersionAsExecuted('20231201120000', 'Test migration');

        $this->executor->execute($migration, 'up');

        $this->assertContains('Migration 20231201120000 has already been executed, skipping', $this->outputMessages);
    }

    public function testExecuteDownNotExecuted(): void
    {
        $migration = $this->createTestMigration('20231201120000', 'Test migration');

        $this->executor->execute($migration, 'down');

        $this->assertContains('Migration 20231201120000 has not been executed, skipping', $this->outputMessages);
    }

    public function testExecuteWithSkipException(): void
    {
        $migration = new class implements MigrationInterface {
            public function getVersion(): string { return '20231201120000'; }
            public function getDescription(): string { return 'Skipped migration'; }
            public function isTransactional(): bool { return true; }
            public function getDependencies(): array { return []; }

            public function up(ConnectionInterface $connection): void
            {
                throw new SkipMigrationException('Migration skipped due to condition');
            }

            public function down(ConnectionInterface $connection): void {}
        };

        $this->executor->execute($migration, 'up');

        $this->assertContains('Migration 20231201120000 skipped: Migration skipped due to condition', $this->outputMessages);

        // When a migration is skipped, it should NOT be marked as executed
        $this->repository->ensureMigrationTableExists();
        $this->assertFalse($this->repository->hasVersionBeenExecuted('20231201120000'));
    }

    public function testExecuteWithFailure(): void
    {
        $migration = new class implements MigrationInterface {
            public function getVersion(): string { return '20231201120000'; }
            public function getDescription(): string { return 'Failing migration'; }
            public function isTransactional(): bool { return true; }
            public function getDependencies(): array { return []; }

            public function up(ConnectionInterface $connection): void
            {
                throw new \RuntimeException('Migration failed');
            }

            public function down(ConnectionInterface $connection): void {}
        };

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('Migration 20231201120000 failed: Migration failed');

        $this->executor->execute($migration, 'up');
    }

    public function testExecuteNonTransactional(): void
    {
        $migration = new class implements MigrationInterface {
            public function getVersion(): string { return '20231201120000'; }
            public function getDescription(): string { return 'Non-transactional migration'; }
            public function isTransactional(): bool { return false; }
            public function getDependencies(): array { return []; }

            public function up(ConnectionInterface $connection): void
            {
                $connection->execute('CREATE TABLE test_table (id INTEGER)');
            }

            public function down(ConnectionInterface $connection): void
            {
                $connection->execute('DROP TABLE test_table');
            }
        };

        $this->executor->execute($migration, 'up');

        $this->assertTrue($this->repository->hasVersionBeenExecuted('20231201120000'));
        
        // Verify table was created
        $result = $this->connection->fetchOne("SELECT name FROM sqlite_master WHERE type='table' AND name='test_table'");
        $this->assertNotNull($result);
    }

    public function testExecuteDryRun(): void
    {
        $this->configuration->setDryRun(true);

        $migration = $this->createTestMigration('20231201120000', 'Test migration');

        $this->executor->execute($migration, 'up');

        $this->assertFalse($this->repository->hasVersionBeenExecuted('20231201120000'));
        $this->assertContains('DRY RUN: Would execute migration 20231201120000 (up)', $this->outputMessages);
    }

    public function testExecuteMigrations(): void
    {
        $migrations = [
            $this->createTestMigration('20231201120000', 'First migration'),
            $this->createTestMigration('20231201130000', 'Second migration'),
        ];

        $this->executor->executeMigrations($migrations, 'up');

        $this->assertTrue($this->repository->hasVersionBeenExecuted('20231201120000'));
        $this->assertTrue($this->repository->hasVersionBeenExecuted('20231201130000'));
        $this->assertContains('Executing 2 migration(s) (up)', $this->outputMessages);
        $this->assertContains('All migrations executed successfully', $this->outputMessages);
    }

    public function testExecuteMigrationsEmpty(): void
    {
        $this->executor->executeMigrations([], 'up');

        $this->assertContains('No migrations to execute', $this->outputMessages);
    }

    public function testGetStatus(): void
    {
        $this->createMigrationFile('20231201120000', 'First migration');
        $this->createMigrationFile('20231201130000', 'Second migration');

        $this->repository->ensureMigrationTableExists();
        $this->repository->markVersionAsExecuted('20231201120000', 'First migration');

        $status = $this->executor->getStatus();

        $this->assertEquals('20231201120000', $status['current_version']);
        $this->assertEquals(1, $status['pending_migrations']);
        $this->assertEquals(1, $status['executed_migrations']);
        $this->assertEquals(2, $status['total_migrations']);
    }

    public function testMigrateToLatest(): void
    {
        $this->createMigrationFile('20231201120000', 'First migration');
        $this->createMigrationFile('20231201130000', 'Second migration');

        $this->executor->migrateToLatest();

        $this->assertTrue($this->repository->hasVersionBeenExecuted('20231201120000'));
        $this->assertTrue($this->repository->hasVersionBeenExecuted('20231201130000'));
    }

    public function testMigrateToLatestNoPending(): void
    {
        $this->createMigrationFile('20231201120000', 'First migration');

        $this->repository->ensureMigrationTableExists();
        $this->repository->markVersionAsExecuted('20231201120000', 'First migration');

        $this->executor->migrateToLatest();

        $this->assertContains('No pending migrations to execute', $this->outputMessages);
    }

    public function testRollbackLast(): void
    {
        $this->createMigrationFile('20231201120000', 'First migration');

        // First execute the migration to create the table
        $migration = $this->finder->loadMigration('20231201120000');
        $this->executor->execute($migration, 'up');

        $this->executor->rollbackLast();

        $this->assertFalse($this->repository->hasVersionBeenExecuted('20231201120000'));
    }

    public function testRollbackLastNoMigrations(): void
    {
        $this->executor->rollbackLast();

        $this->assertContains('No migrations to rollback', $this->outputMessages);
    }

    private function createTestMigration(string $version, string $description): MigrationInterface
    {
        return new class($version, $description) implements MigrationInterface {
            public function __construct(private string $version, private string $description) {}

            public function getVersion(): string { return $this->version; }
            public function getDescription(): string { return $this->description; }
            public function isTransactional(): bool { return true; }
            public function getDependencies(): array { return []; }

            public function up(ConnectionInterface $connection): void
            {
                $connection->execute('CREATE TABLE test_' . $this->version . ' (id INTEGER)');
            }

            public function down(ConnectionInterface $connection): void
            {
                $connection->execute('DROP TABLE test_' . $this->version);
            }
        };
    }

    private function createMigrationFile(string $version, string $description): void
    {
        // Make class name unique to avoid redeclaration errors
        $uniqueId = substr(md5($this->tempDir), 0, 8);
        $className = 'Version' . $version . '_' . $uniqueId;

        $content = '<?php
namespace TestMigrations;

use Fduarte42\Aurum\Migration\AbstractMigration;
use Fduarte42\Aurum\Connection\ConnectionInterface;

class ' . $className . ' extends AbstractMigration
{
    public function getVersion(): string
    {
        return \'' . $version . '\';
    }

    public function getDescription(): string
    {
        return \'' . $description . '\';
    }

    public function up(ConnectionInterface $connection): void
    {
        $connection->execute(\'CREATE TABLE test_' . $version . ' (id INTEGER)\');
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->execute(\'DROP TABLE test_' . $version . '\');
    }
}
';

        file_put_contents($this->configuration->getMigrationFilePath($version), $content);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
