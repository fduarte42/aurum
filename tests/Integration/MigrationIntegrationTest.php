<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Integration;

use Fduarte42\Aurum\Connection\ConnectionFactory;
use Fduarte42\Aurum\Connection\ConnectionInterface;
use Fduarte42\Aurum\EntityManager;
use Fduarte42\Aurum\Migration\MigrationConfiguration;
use Fduarte42\Aurum\Migration\MigrationManager;
use PHPUnit\Framework\TestCase;

class MigrationIntegrationTest extends TestCase
{
    private ConnectionInterface $connection;
    private MigrationManager $migrationManager;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/aurum_migration_integration_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        $this->connection = ConnectionFactory::createSqliteConnection(':memory:');
        
        $configuration = new MigrationConfiguration($this->tempDir, 'TestMigrations');
        $this->migrationManager = new MigrationManager($this->connection, $configuration);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    public function testFullMigrationWorkflow(): void
    {
        // 1. Generate first migration with unique version
        $version1 = $this->generateUniqueVersion();
        $this->createMigrationFile($version1, 'Create users table', 'users');

        // 2. Generate second migration with unique version
        $version2 = $this->generateUniqueVersion();
        $this->createMigrationFile($version2, 'Create posts table', 'posts');

        // 3. Check status before migration
        $status = $this->migrationManager->status();
        $this->assertNull($status['current_version']);
        $this->assertEquals(2, $status['pending_migrations']);
        $this->assertEquals(0, $status['executed_migrations']);

        // 4. Run migrations
        $this->migrationManager->migrate();

        // 5. Verify tables were created
        $this->assertTrue($this->tableExists('users'));
        $this->assertTrue($this->tableExists('posts'));

        // 6. Check status after migration
        $status = $this->migrationManager->status();
        $this->assertEquals($version2, $status['current_version']);
        $this->assertEquals(0, $status['pending_migrations']);
        $this->assertEquals(2, $status['executed_migrations']);

        // 7. List migrations
        $migrations = $this->migrationManager->list();
        $this->assertCount(2, $migrations);
        $this->assertTrue($migrations[0]['executed']);
        $this->assertTrue($migrations[1]['executed']);

        // 8. Rollback last migration
        $this->migrationManager->rollback();

        // 9. Verify rollback
        $this->assertTrue($this->tableExists('users'));
        $this->assertFalse($this->tableExists('posts'));

        $status = $this->migrationManager->status();
        $this->assertEquals($version1, $status['current_version']);
        $this->assertEquals(1, $status['pending_migrations']);
        $this->assertEquals(1, $status['executed_migrations']);
    }

    public function testMigrationWithEntityManager(): void
    {
        // Create a container with EntityHydrator
        $container = new \Fduarte42\Aurum\DependencyInjection\SimpleContainer();
        $metadataFactory = new \Fduarte42\Aurum\Metadata\MetadataFactory();
        $entityHydrator = new \Fduarte42\Aurum\Hydration\EntityHydrator($metadataFactory);
        $container->set(\Fduarte42\Aurum\Hydration\EntityHydratorInterface::class, $entityHydrator);

        // Create EntityManager with migration support
        $entityManager = new EntityManager(
            $this->connection,
            $metadataFactory,
            new \Fduarte42\Aurum\Proxy\LazyGhostProxyFactory(),
            $container
        );

        // Use the same migration manager that was set up in setUp
        // This ensures it uses the same temporary directory
        $migrationManager = $this->migrationManager;

        // Generate and run a migration with unique version
        $version = $this->generateUniqueVersion();
        $this->createMigrationFile($version, 'Create test table', 'test_table');

        $migrationManager->migrate();

        // Verify table was created
        $this->assertTrue($this->tableExists('test_table'));

        // Verify migration was tracked
        $status = $migrationManager->status();
        $this->assertEquals($version, $status['current_version']);
        $this->assertEquals(1, $status['executed_migrations']);
    }

    public function testDryRunMode(): void
    {
        $version = $this->generateUniqueVersion();
        $this->createMigrationFile($version, 'Create dry run table', 'dry_run_table');

        // Enable dry run mode
        $this->migrationManager->setDryRun(true);

        $outputMessages = [];
        $this->migrationManager->setOutputWriter(function (string $message) use (&$outputMessages) {
            $outputMessages[] = $message;
        });

        $this->migrationManager->migrate();

        // Verify table was NOT created
        $this->assertFalse($this->tableExists('dry_run_table'));

        // Verify dry run messages
        $this->assertContains("DRY RUN: Would execute migration {$version} (up)", $outputMessages);

        // Verify migration was NOT tracked
        $status = $this->migrationManager->status();
        $this->assertNull($status['current_version']);
        $this->assertEquals(0, $status['executed_migrations']);
    }

    public function testMigrationWithDependencies(): void
    {
        // Create migrations with dependencies
        $version1 = $this->generateUniqueVersion();
        $version2 = $this->generateUniqueVersion();

        $this->createMigrationFileWithDependencies($version1, 'Create base table', 'base_table', []);
        $this->createMigrationFileWithDependencies($version2, 'Create dependent table', 'dependent_table', [$version1]);

        $this->migrationManager->migrate();

        // Verify both tables were created
        $this->assertTrue($this->tableExists('base_table'));
        $this->assertTrue($this->tableExists('dependent_table'));

        // Verify execution order
        $details = $this->migrationManager->getRepository()->getExecutionDetails();
        $this->assertEquals($version1, $details[0]['version']);
        $this->assertEquals($version2, $details[1]['version']);
    }

    public function testVerboseMode(): void
    {
        $version = $this->generateUniqueVersion();
        $this->createMigrationFile($version, 'Create verbose table', 'verbose_table');

        $outputMessages = [];
        $this->migrationManager
            ->setVerbose(true)
            ->setOutputWriter(function (string $message) use (&$outputMessages) {
                $outputMessages[] = $message;
            });

        $this->migrationManager->migrate();

        $this->assertNotEmpty($outputMessages);
        $this->assertContains("Executing migration {$version} (up): Create verbose table", $outputMessages);
        $this->assertStringContainsString('executed successfully', implode(' ', $outputMessages));
    }

    public function testReset(): void
    {
        $version = $this->generateUniqueVersion();
        $this->createMigrationFile($version, 'Create reset table', 'reset_table');

        $this->migrationManager->migrate();

        // Verify migration was executed
        $status = $this->migrationManager->status();
        $this->assertEquals(1, $status['executed_migrations']);

        // Reset migrations
        $this->migrationManager->reset();

        // Verify migration tracking was cleared
        $status = $this->migrationManager->status();
        $this->assertEquals(0, $status['executed_migrations']);
        $this->assertNull($status['current_version']);

        // But table should still exist (reset only clears tracking)
        $this->assertTrue($this->tableExists('reset_table'));
    }

    private function generateUniqueVersion(): string
    {
        static $counter = 0;
        $counter++;
        return '2025082520' . str_pad((string)$counter, 4, '0', STR_PAD_LEFT);
    }

    private function createMigrationFile(string $version, string $description, string $tableName): void
    {
        $this->createMigrationFileWithDependencies($version, $description, $tableName, []);
    }

    private function modifyMigrationFile(string $version, string $tableName): void
    {
        $this->modifyMigrationFileWithDependencies($version, $tableName, []);
    }

    private function createMigrationFileWithDependencies(string $version, string $description, string $tableName, array $dependencies): void
    {
        // Make class name unique to avoid redeclaration errors
        $uniqueId = substr(md5($this->tempDir . $version), 0, 8);
        $className = 'Version' . $version . '_' . $uniqueId;

        $dependenciesCode = empty($dependencies)
            ? 'return [];'
            : 'return [' . implode(', ', array_map(fn($dep) => "'$dep'", $dependencies)) . '];';

        $content = '<?php
namespace TestMigrations;

use Fduarte42\Aurum\Migration\AbstractMigration;
use Fduarte42\Aurum\Connection\ConnectionInterface;

final class ' . $className . ' extends AbstractMigration
{
    public function getVersion(): string
    {
        return \'' . $version . '\';
    }

    public function getDescription(): string
    {
        return \'' . $description . '\';
    }

    public function getDependencies(): array
    {
        ' . $dependenciesCode . '
    }

    public function up(ConnectionInterface $connection): void
    {
        $this->schemaBuilder->createTable(\'' . $tableName . '\')
            ->id()
            ->string(\'name\')
            ->timestamps()
            ->create();
    }

    public function down(ConnectionInterface $connection): void
    {
        $this->schemaBuilder->dropTable(\'' . $tableName . '\');
    }
}
';

        file_put_contents($this->migrationManager->getConfiguration()->getMigrationFilePath($version), $content);
    }

    private function modifyMigrationFileWithDependencies(string $version, string $tableName, array $dependencies): void
    {
        $filePath = $this->migrationManager->getConfiguration()->getMigrationFilePath($version);
        $content = file_get_contents($filePath);

        $dependenciesCode = empty($dependencies) 
            ? 'return [];' 
            : 'return [' . implode(', ', array_map(fn($dep) => "'$dep'", $dependencies)) . '];';

        // Replace the up method
        $upMethod = "
    public function up(ConnectionInterface \$connection): void
    {
        \$this->schemaBuilder->createTable('{$tableName}')
            ->id()
            ->string('name')
            ->timestamps()
            ->create();
    }";

        // Replace the down method
        $downMethod = "
    public function down(ConnectionInterface \$connection): void
    {
        \$this->schemaBuilder->dropTable('{$tableName}');
    }";

        // Replace the dependencies method
        $dependenciesMethod = "
    public function getDependencies(): array
    {
        {$dependenciesCode}
    }";

        $content = preg_replace('/public function up\(.*?\{.*?\}/s', $upMethod, $content);
        $content = preg_replace('/public function down\(.*?\{.*?\}/s', $downMethod, $content);
        
        // Add dependencies method if it has dependencies
        if (!empty($dependencies)) {
            $content = preg_replace('/(\}\s*)$/', $dependenciesMethod . "\n$1", $content);
        }

        file_put_contents($filePath, $content);
    }

    private function tableExists(string $tableName): bool
    {
        $result = $this->connection->fetchOne(
            "SELECT name FROM sqlite_master WHERE type='table' AND name = ?",
            [$tableName]
        );
        return $result !== null;
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
