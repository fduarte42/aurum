<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit\Migration;

use Fduarte42\Aurum\Connection\ConnectionFactory;
use Fduarte42\Aurum\Migration\AbstractMigration;
use Fduarte42\Aurum\Migration\MigrationConfiguration;
use Fduarte42\Aurum\Migration\MigrationException;
use Fduarte42\Aurum\Migration\MigrationFinder;
use Fduarte42\Aurum\Migration\MigrationInterface;
use Fduarte42\Aurum\Migration\MigrationRepository;
use Fduarte42\Aurum\Connection\ConnectionInterface;
use PHPUnit\Framework\TestCase;

class MigrationFinderTest extends TestCase
{
    private string $tempDir;
    private MigrationConfiguration $configuration;
    private MigrationRepository $repository;
    private MigrationFinder $finder;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/aurum_migration_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        $this->configuration = new MigrationConfiguration($this->tempDir, 'TestMigrations');
        $connection = ConnectionFactory::createSqliteConnection(':memory:');
        $this->repository = new MigrationRepository($connection);
        $this->finder = new MigrationFinder($this->configuration, $this->repository);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    public function testFindVersionsEmpty(): void
    {
        $versions = $this->finder->findVersions();
        $this->assertEmpty($versions);
    }

    public function testFindVersions(): void
    {
        $this->createMigrationFile('20231201120000', 'First migration');
        $this->createMigrationFile('20231201130000', 'Second migration');
        $this->createMigrationFile('20231201110000', 'Third migration');

        $versions = $this->finder->findVersions();

        $this->assertCount(3, $versions);
        $this->assertEquals(['20231201110000', '20231201120000', '20231201130000'], $versions);
    }

    public function testFindVersionsIgnoresInvalidFiles(): void
    {
        $this->createMigrationFile('20231201120000', 'Valid migration');
        
        // Create invalid files that should be ignored
        file_put_contents($this->tempDir . '/InvalidFile.php', '<?php // invalid');
        file_put_contents($this->tempDir . '/Version123.php', '<?php // too short version');
        file_put_contents($this->tempDir . '/NotAMigration.txt', 'text file');

        $versions = $this->finder->findVersions();

        $this->assertCount(1, $versions);
        $this->assertEquals(['20231201120000'], $versions);
    }

    public function testFindPendingVersions(): void
    {
        $this->createMigrationFile('20231201120000', 'First migration');
        $this->createMigrationFile('20231201130000', 'Second migration');
        $this->createMigrationFile('20231201140000', 'Third migration');

        // Mark one as executed
        $this->repository->ensureMigrationTableExists();
        $this->repository->markVersionAsExecuted('20231201120000', 'First migration');

        $pendingVersions = $this->finder->findPendingVersions();

        $this->assertCount(2, $pendingVersions);
        $this->assertEquals(['20231201130000', '20231201140000'], array_values($pendingVersions));
    }

    public function testLoadMigration(): void
    {
        $version = '20231201120000';
        $this->createMigrationFile($version, 'Test migration');

        $migration = $this->finder->loadMigration($version);

        $this->assertInstanceOf(MigrationInterface::class, $migration);
        $this->assertEquals($version, $migration->getVersion());
        $this->assertEquals('Test migration', $migration->getDescription());
    }

    public function testLoadMigrationFileNotFound(): void
    {
        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('Migration 20231201120000 not found');
        $this->finder->loadMigration('20231201120000');
    }

    public function testLoadMigrationClassNotFound(): void
    {
        $version = '20231201120000';

        // Create file without the expected class
        $content = '<?php
namespace TestMigrations;
// No class defined
';
        file_put_contents($this->configuration->getMigrationFilePath($version), $content);

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('Migration class not found');
        $this->finder->loadMigration($version);
    }

    public function testLoadMigrationInvalidClass(): void
    {
        $version = '20231201120000';

        // Create file with class that doesn't implement MigrationInterface
        $uniqueId = substr(md5($this->tempDir), 0, 8);
        $className = 'Version' . $version . '_' . $uniqueId;

        $content = '<?php
namespace TestMigrations;

class ' . $className . ' {
    // Does not implement MigrationInterface
    public function getVersion(): string { return "' . $version . '"; }
}
';
        file_put_contents($this->configuration->getMigrationFilePath($version), $content);

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('Invalid migration class');
        $this->finder->loadMigration($version);
    }

    public function testHasVersion(): void
    {
        $version = '20231201120000';
        
        $this->assertFalse($this->finder->hasVersion($version));
        
        $this->createMigrationFile($version, 'Test migration');
        
        $this->assertTrue($this->finder->hasVersion($version));
    }

    public function testGetMigrationDetails(): void
    {
        $this->createMigrationFile('20231201120000', 'First migration');
        $this->createMigrationFile('20231201130000', 'Second migration');

        // Mark one as executed
        $this->repository->ensureMigrationTableExists();
        $this->repository->markVersionAsExecuted('20231201120000', 'First migration');

        $details = $this->finder->getMigrationDetails();

        $this->assertCount(2, $details);
        
        $this->assertEquals('20231201120000', $details[0]['version']);
        $this->assertEquals('TestMigrations\\Version20231201120000', $details[0]['class']);
        $this->assertEquals('First migration', $details[0]['description']);
        $this->assertTrue($details[0]['executed']);

        $this->assertEquals('20231201130000', $details[1]['version']);
        $this->assertEquals('TestMigrations\\Version20231201130000', $details[1]['class']);
        $this->assertEquals('Second migration', $details[1]['description']);
        $this->assertFalse($details[1]['executed']);
    }

    public function testGetMigrationDetailsWithError(): void
    {
        $version = '20231201120000';

        // Create invalid migration file with unique class name
        $uniqueId = substr(md5($this->tempDir), 0, 8);
        $className = 'Version' . $version . '_' . $uniqueId;

        $content = '<?php
namespace TestMigrations;
class ' . $className . ' {
    // Invalid class - doesn\'t implement MigrationInterface
}
';
        file_put_contents($this->configuration->getMigrationFilePath($version), $content);

        $details = $this->finder->getMigrationDetails();

        $this->assertCount(1, $details);
        $this->assertEquals($version, $details[0]['version']);
        $this->assertStringContainsString('Error loading migration', $details[0]['description']);
        $this->assertFalse($details[0]['executed']);
    }

    public function testSortVersionsByDependencies(): void
    {
        $this->createMigrationFileWithDependencies('20231201120000', 'First migration', []);
        $this->createMigrationFileWithDependencies('20231201130000', 'Second migration', ['20231201120000']);
        $this->createMigrationFileWithDependencies('20231201140000', 'Third migration', ['20231201130000']);

        $versions = ['20231201140000', '20231201120000', '20231201130000']; // Unsorted
        $sortedVersions = $this->finder->sortVersionsByDependencies($versions);

        $this->assertEquals(['20231201120000', '20231201130000', '20231201140000'], $sortedVersions);
    }

    public function testSortVersionsByDependenciesCircular(): void
    {
        $this->createMigrationFileWithDependencies('20231201120000', 'First migration', ['20231201130000']);
        $this->createMigrationFileWithDependencies('20231201130000', 'Second migration', ['20231201120000']);

        $versions = ['20231201120000', '20231201130000'];

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('Circular dependency detected');
        $this->finder->sortVersionsByDependencies($versions);
    }

    public function testSortVersionsByDependenciesMissing(): void
    {
        $this->createMigrationFileWithDependencies('20231201130000', 'Second migration', ['20231201120000']);

        $versions = ['20231201130000']; // Missing dependency

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('depends on 20231201120000');
        $this->finder->sortVersionsByDependencies($versions);
    }

    private function createMigrationFile(string $version, string $description): void
    {
        $this->createMigrationFileWithDependencies($version, $description, []);
    }

    private function createMigrationFileWithDependencies(string $version, string $description, array $dependencies): void
    {
        $dependenciesCode = empty($dependencies)
            ? 'return [];'
            : 'return [' . implode(', ', array_map(fn($dep) => "'$dep'", $dependencies)) . '];';

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

    public function getDependencies(): array
    {
        ' . $dependenciesCode . '
    }

    public function up(ConnectionInterface $connection): void
    {
        // Migration up logic
    }

    public function down(ConnectionInterface $connection): void
    {
        // Migration down logic
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
