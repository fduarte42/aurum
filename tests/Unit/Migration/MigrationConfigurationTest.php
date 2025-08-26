<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit\Migration;

use Fduarte42\Aurum\Migration\MigrationConfiguration;
use Fduarte42\Aurum\Migration\MigrationException;
use PHPUnit\Framework\TestCase;

class MigrationConfigurationTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/aurum_migration_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    public function testConstructor(): void
    {
        $config = new MigrationConfiguration($this->tempDir, 'TestMigrations');

        $this->assertEquals($this->tempDir, $config->getMigrationsDirectory());
        $this->assertEquals('TestMigrations', $config->getMigrationsNamespace());
        $this->assertEquals('aurum_migrations', $config->getMigrationTableName());
        $this->assertFalse($config->isDryRun());
        $this->assertFalse($config->isVerbose());
        $this->assertNull($config->getMigrationTemplate());
    }

    public function testSettersAndGetters(): void
    {
        $config = new MigrationConfiguration($this->tempDir);

        $newDir = $this->tempDir . '/new';
        mkdir($newDir);

        $config->setMigrationsDirectory($newDir)
               ->setMigrationsNamespace('NewNamespace')
               ->setMigrationTableName('custom_migrations')
               ->setDryRun(true)
               ->setVerbose(true)
               ->setMigrationTemplate('/path/to/template.php');

        $this->assertEquals($newDir, $config->getMigrationsDirectory());
        $this->assertEquals('NewNamespace', $config->getMigrationsNamespace());
        $this->assertEquals('custom_migrations', $config->getMigrationTableName());
        $this->assertTrue($config->isDryRun());
        $this->assertTrue($config->isVerbose());
        $this->assertEquals('/path/to/template.php', $config->getMigrationTemplate());
    }

    public function testGetMigrationClassName(): void
    {
        $config = new MigrationConfiguration($this->tempDir, 'TestMigrations');

        $className = $config->getMigrationClassName('20231201120000');
        $this->assertEquals('TestMigrations\\Version20231201120000', $className);
    }

    public function testGetMigrationFilePath(): void
    {
        $config = new MigrationConfiguration($this->tempDir, 'TestMigrations');

        $filePath = $config->getMigrationFilePath('20231201120000');
        $this->assertEquals($this->tempDir . '/Version20231201120000.php', $filePath);
    }

    public function testValidateSuccess(): void
    {
        $config = new MigrationConfiguration($this->tempDir, 'TestMigrations');

        $this->expectNotToPerformAssertions();
        $config->validate();
    }

    public function testValidateDirectoryNotFound(): void
    {
        // Use a path that cannot be created (e.g., under a read-only directory)
        $readOnlyDir = $this->tempDir . '/readonly';
        mkdir($readOnlyDir, 0444); // Read-only directory
        $nonExistentDir = $readOnlyDir . '/nonexistent';

        $config = new MigrationConfiguration($nonExistentDir, 'TestMigrations');

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('Migration directory not found');

        try {
            $config->validate();
        } finally {
            // Clean up: make directory writable again for cleanup
            chmod($readOnlyDir, 0755);
        }
    }

    public function testValidateDirectoryNotWritable(): void
    {
        // Make directory read-only
        chmod($this->tempDir, 0444);

        $config = new MigrationConfiguration($this->tempDir, 'TestMigrations');

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('Migration directory is not writable');
        $config->validate();

        // Restore permissions for cleanup
        chmod($this->tempDir, 0755);
    }

    public function testValidateEmptyNamespace(): void
    {
        $config = new MigrationConfiguration($this->tempDir, '');

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('Migrations namespace cannot be empty');
        $config->validate();
    }

    public function testValidateEmptyTableName(): void
    {
        $config = new MigrationConfiguration($this->tempDir, 'TestMigrations');
        $config->setMigrationTableName('');

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('Migration table name cannot be empty');
        $config->validate();
    }

    public function testCreateDefault(): void
    {
        $projectRoot = $this->tempDir;
        $config = MigrationConfiguration::createDefault($projectRoot);

        $expectedDir = $projectRoot . '/migrations';
        $this->assertEquals($expectedDir, $config->getMigrationsDirectory());
        $this->assertEquals('Migrations', $config->getMigrationsNamespace());
        $this->assertTrue(is_dir($expectedDir));
    }

    public function testDirectoryPathNormalization(): void
    {
        $dirWithTrailingSlash = $this->tempDir . '/';
        $config = new MigrationConfiguration($dirWithTrailingSlash);

        $this->assertEquals($this->tempDir, $config->getMigrationsDirectory());
    }

    public function testNamespaceNormalization(): void
    {
        $namespaceWithSlashes = '\\TestMigrations\\';
        $config = new MigrationConfiguration($this->tempDir, $namespaceWithSlashes);

        $this->assertEquals('TestMigrations', $config->getMigrationsNamespace());
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
