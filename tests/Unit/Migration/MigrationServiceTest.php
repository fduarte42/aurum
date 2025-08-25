<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit\Migration;

use Fduarte42\Aurum\Connection\ConnectionFactory;
use Fduarte42\Aurum\Connection\ConnectionInterface;
use Fduarte42\Aurum\DependencyInjection\ContainerBuilder;
use Fduarte42\Aurum\Migration\MigrationConfiguration;
use Fduarte42\Aurum\Migration\MigrationService;
use PHPUnit\Framework\TestCase;

class MigrationServiceTest extends TestCase
{
    private ConnectionInterface $connection;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/aurum_migration_service_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        $this->connection = ConnectionFactory::createSqliteConnection(':memory:');
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    public function testConstructorWithConfiguration(): void
    {
        $configuration = new MigrationConfiguration($this->tempDir, 'TestMigrations');
        $service = new MigrationService($this->connection, $configuration);

        $migrationManager = $service->getMigrationManager();
        $this->assertSame($configuration, $migrationManager->getConfiguration());
    }

    public function testConstructorWithoutConfiguration(): void
    {
        $service = new MigrationService($this->connection);

        $migrationManager = $service->getMigrationManager();
        $configuration = $migrationManager->getConfiguration();

        $this->assertEquals('Migrations', $configuration->getMigrationsNamespace());
        $this->assertStringContainsString('migrations', $configuration->getMigrationsDirectory());
    }

    public function testGenerate(): void
    {
        $configuration = new MigrationConfiguration($this->tempDir, 'TestMigrations');
        $service = new MigrationService($this->connection, $configuration);

        $version = $service->generate('Test migration');

        $this->assertMatchesRegularExpression('/^\d{14}$/', $version);
        $this->assertFileExists($configuration->getMigrationFilePath($version));
    }

    public function testMigrate(): void
    {
        $configuration = new MigrationConfiguration($this->tempDir, 'TestMigrations');
        $service = new MigrationService($this->connection, $configuration);

        // Generate a migration with unique version
        $version = $this->generateUniqueVersion();
        $this->createMigrationFile($configuration, $version, 'Create test table');

        $service->migrate();

        // Verify migration was executed
        $status = $service->status();
        $this->assertEquals(1, $status['executed_migrations']);
    }

    public function testRollback(): void
    {
        $configuration = new MigrationConfiguration($this->tempDir, 'TestMigrations');
        $service = new MigrationService($this->connection, $configuration);

        // Generate and execute a migration with unique version
        $version = $this->generateUniqueVersion();
        $this->createMigrationFile($configuration, $version, 'Create test table');
        $service->migrate();

        $service->rollback();

        // Verify migration was rolled back
        $status = $service->status();
        $this->assertEquals(0, $status['executed_migrations']);
    }

    public function testStatus(): void
    {
        $configuration = new MigrationConfiguration($this->tempDir, 'TestMigrations');
        $service = new MigrationService($this->connection, $configuration);

        $status = $service->status();

        $this->assertArrayHasKey('current_version', $status);
        $this->assertArrayHasKey('pending_migrations', $status);
        $this->assertArrayHasKey('executed_migrations', $status);
        $this->assertArrayHasKey('total_migrations', $status);
    }

    public function testList(): void
    {
        $configuration = new MigrationConfiguration($this->tempDir, 'TestMigrations');
        $service = new MigrationService($this->connection, $configuration);

        // Generate a migration with unique version
        $version = $this->generateUniqueVersion();
        $this->createMigrationFile($configuration, $version, 'Test migration');

        $migrations = $service->list();

        $this->assertCount(1, $migrations);
        $this->assertEquals($version, $migrations[0]['version']);
        $this->assertEquals('Test migration', $migrations[0]['description']);
        $this->assertFalse($migrations[0]['executed']);
    }

    public function testSetOutputWriter(): void
    {
        $configuration = new MigrationConfiguration($this->tempDir, 'TestMigrations');
        $service = new MigrationService($this->connection, $configuration);

        $messages = [];
        $service->setOutputWriter(function (string $message) use (&$messages) {
            $messages[] = $message;
        });

        // Generate and execute a migration with unique version
        $version = $this->generateUniqueVersion();
        $this->createMigrationFile($configuration, $version, 'Create test table');
        $service->migrate();

        $this->assertNotEmpty($messages);
        $this->assertStringContainsString('Executing migration', implode(' ', $messages));
    }

    public function testSetVerbose(): void
    {
        $configuration = new MigrationConfiguration($this->tempDir, 'TestMigrations');
        $service = new MigrationService($this->connection, $configuration);

        $result = $service->setVerbose(true);

        $this->assertSame($service, $result);
        $this->assertTrue($service->getMigrationManager()->getConfiguration()->isVerbose());
    }

    public function testSetDryRun(): void
    {
        $configuration = new MigrationConfiguration($this->tempDir, 'TestMigrations');
        $service = new MigrationService($this->connection, $configuration);

        $result = $service->setDryRun(true);

        $this->assertSame($service, $result);
        $this->assertTrue($service->getMigrationManager()->getConfiguration()->isDryRun());
    }

    public function testCreateWithCustomConfiguration(): void
    {
        $service = MigrationService::create($this->connection, $this->tempDir, 'CustomMigrations');

        $configuration = $service->getMigrationManager()->getConfiguration();
        $this->assertEquals($this->tempDir, $configuration->getMigrationsDirectory());
        $this->assertEquals('CustomMigrations', $configuration->getMigrationsNamespace());
    }

    public function testDependencyInjectionIntegration(): void
    {
        $config = [
            'connection' => [
                'driver' => 'sqlite',
                'path' => ':memory:'
            ],
            'migrations' => [
                'directory' => $this->tempDir,
                'namespace' => 'TestMigrations',
                'table_name' => 'custom_migrations'
            ]
        ];

        $container = ContainerBuilder::createORM($config);

        $this->assertTrue($container->has(MigrationService::class));
        $this->assertTrue($container->has('migration.service'));

        $migrationService = $container->get(MigrationService::class);
        $this->assertInstanceOf(MigrationService::class, $migrationService);

        $configuration = $migrationService->getMigrationManager()->getConfiguration();
        $this->assertEquals($this->tempDir, $configuration->getMigrationsDirectory());
        $this->assertEquals('TestMigrations', $configuration->getMigrationsNamespace());
        $this->assertEquals('custom_migrations', $configuration->getMigrationTableName());
    }

    public function testDependencyInjectionWithDefaults(): void
    {
        $config = [
            'connection' => [
                'driver' => 'sqlite',
                'path' => ':memory:'
            ]
        ];

        $container = ContainerBuilder::createORM($config);
        $migrationService = $container->get(MigrationService::class);

        $configuration = $migrationService->getMigrationManager()->getConfiguration();
        $this->assertEquals('Migrations', $configuration->getMigrationsNamespace());
        $this->assertEquals('aurum_migrations', $configuration->getMigrationTableName());
    }

    public function testProjectRootDetection(): void
    {
        // Test that the service can detect a project root
        $service = new MigrationService($this->connection);
        $configuration = $service->getMigrationManager()->getConfiguration();

        // Should have a migrations directory
        $migrationsDir = $configuration->getMigrationsDirectory();
        $this->assertNotEmpty($migrationsDir);
        $this->assertStringContainsString('migrations', $migrationsDir);

        // Directory should exist (created by the service)
        $this->assertTrue(is_dir($migrationsDir));
    }

    private function generateUniqueVersion(): string
    {
        static $counter = 0;
        $counter++;
        return '2023120112' . str_pad((string)$counter, 4, '0', STR_PAD_LEFT);
    }

    private function createMigrationFile(MigrationConfiguration $configuration, string $version, string $description): void
    {
        // Make class name unique to avoid redeclaration errors
        $uniqueId = substr(md5($this->tempDir . $version), 0, 8);
        $className = 'Version' . $version . '_' . $uniqueId;

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

    public function up(ConnectionInterface $connection): void
    {
        $this->schemaBuilder->createTable(\'test_table\')
            ->id()
            ->string(\'name\')
            ->create();
    }

    public function down(ConnectionInterface $connection): void
    {
        $this->schemaBuilder->dropTable(\'test_table\');
    }
}
';

        file_put_contents($configuration->getMigrationFilePath($version), $content);
    }

    private function modifyMigrationFile(MigrationConfiguration $configuration, string $version): void
    {
        $filePath = $configuration->getMigrationFilePath($version);
        $content = file_get_contents($filePath);

        // Replace the up method to create a table
        $upMethod = "
    public function up(ConnectionInterface \$connection): void
    {
        \$this->schemaBuilder->createTable('test_table')
            ->id()
            ->string('name')
            ->create();
    }";

        // Replace the down method to drop the table
        $downMethod = "
    public function down(ConnectionInterface \$connection): void
    {
        \$this->schemaBuilder->dropTable('test_table');
    }";

        $content = preg_replace('/public function up\(.*?\{.*?\}/s', $upMethod, $content);
        $content = preg_replace('/public function down\(.*?\{.*?\}/s', $downMethod, $content);

        file_put_contents($filePath, $content);
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
