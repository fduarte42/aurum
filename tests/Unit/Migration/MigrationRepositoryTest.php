<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit\Migration;

use Fduarte42\Aurum\Connection\ConnectionFactory;
use Fduarte42\Aurum\Connection\ConnectionInterface;
use Fduarte42\Aurum\Migration\MigrationRepository;
use PHPUnit\Framework\TestCase;

class MigrationRepositoryTest extends TestCase
{
    private ConnectionInterface $connection;
    private MigrationRepository $repository;

    protected function setUp(): void
    {
        $this->connection = ConnectionFactory::createSqliteConnection(':memory:');
        $this->repository = new MigrationRepository($this->connection);
    }

    public function testEnsureMigrationTableExists(): void
    {
        $this->repository->ensureMigrationTableExists();

        // Check that table was created
        $result = $this->connection->fetchOne(
            "SELECT name FROM sqlite_master WHERE type='table' AND name = 'aurum_migrations'"
        );
        $this->assertNotNull($result);
    }

    public function testEnsureMigrationTableExistsIdempotent(): void
    {
        // Call twice to ensure it's idempotent
        $this->repository->ensureMigrationTableExists();
        $this->repository->ensureMigrationTableExists();

        $result = $this->connection->fetchOne(
            "SELECT name FROM sqlite_master WHERE type='table' AND name = 'aurum_migrations'"
        );
        $this->assertNotNull($result);
    }

    public function testHasVersionBeenExecuted(): void
    {
        $this->repository->ensureMigrationTableExists();

        $this->assertFalse($this->repository->hasVersionBeenExecuted('20231201120000'));

        $this->repository->markVersionAsExecuted('20231201120000', 'Test migration');

        $this->assertTrue($this->repository->hasVersionBeenExecuted('20231201120000'));
    }

    public function testMarkVersionAsExecuted(): void
    {
        $this->repository->ensureMigrationTableExists();

        $version = '20231201120000';
        $description = 'Test migration';
        $executionTime = 1.5;

        $this->repository->markVersionAsExecuted($version, $description, $executionTime);

        $result = $this->connection->fetchOne(
            "SELECT * FROM aurum_migrations WHERE version = ?",
            [$version]
        );

        $this->assertNotNull($result);
        $this->assertEquals($version, $result['version']);
        $this->assertEquals($description, $result['description']);
        $this->assertEquals($executionTime, (float) $result['execution_time']);
        $this->assertNotEmpty($result['executed_at']);
    }

    public function testMarkVersionAsNotExecuted(): void
    {
        $this->repository->ensureMigrationTableExists();

        $version = '20231201120000';
        $this->repository->markVersionAsExecuted($version, 'Test migration');

        $this->assertTrue($this->repository->hasVersionBeenExecuted($version));

        $this->repository->markVersionAsNotExecuted($version);

        $this->assertFalse($this->repository->hasVersionBeenExecuted($version));
    }

    public function testGetExecutedVersions(): void
    {
        $this->repository->ensureMigrationTableExists();

        $versions = ['20231201120000', '20231201130000', '20231201140000'];

        foreach ($versions as $version) {
            $this->repository->markVersionAsExecuted($version, "Migration {$version}");
        }

        $executedVersions = $this->repository->getExecutedVersions();

        $this->assertEquals($versions, $executedVersions);
    }

    public function testGetExecutedVersionsEmpty(): void
    {
        $this->repository->ensureMigrationTableExists();

        $executedVersions = $this->repository->getExecutedVersions();

        $this->assertEmpty($executedVersions);
    }

    public function testGetExecutionDetails(): void
    {
        $this->repository->ensureMigrationTableExists();

        $version = '20231201120000';
        $description = 'Test migration';
        $executionTime = 2.5;

        $this->repository->markVersionAsExecuted($version, $description, $executionTime);

        $details = $this->repository->getExecutionDetails();

        $this->assertCount(1, $details);
        $this->assertEquals($version, $details[0]['version']);
        $this->assertEquals($description, $details[0]['description']);
        $this->assertEquals($executionTime, (float) $details[0]['execution_time']);
        $this->assertNotEmpty($details[0]['executed_at']);
    }

    public function testGetLatestVersion(): void
    {
        $this->repository->ensureMigrationTableExists();

        $this->assertNull($this->repository->getLatestVersion());

        $versions = ['20231201120000', '20231201130000', '20231201140000'];

        // Execute with explicit timestamps to ensure order
        $baseTime = time();
        foreach ($versions as $i => $version) {
            $timestamp = date('Y-m-d H:i:s', $baseTime + $i);
            $this->connection->execute(
                "INSERT INTO aurum_migrations (version, description, executed_at, execution_time) VALUES (?, ?, ?, ?)",
                [$version, "Migration {$version}", $timestamp, 0.0]
            );
        }

        $latestVersion = $this->repository->getLatestVersion();
        $this->assertEquals('20231201140000', $latestVersion);
    }

    public function testClearMigrationTable(): void
    {
        $this->repository->ensureMigrationTableExists();

        $versions = ['20231201120000', '20231201130000'];
        foreach ($versions as $version) {
            $this->repository->markVersionAsExecuted($version, "Migration {$version}");
        }

        $this->assertCount(2, $this->repository->getExecutedVersions());

        $this->repository->clearMigrationTable();

        $this->assertEmpty($this->repository->getExecutedVersions());
    }

    public function testMarkVersionAsExecutedWithDefaults(): void
    {
        $this->repository->ensureMigrationTableExists();

        $version = '20231201120000';
        $this->repository->markVersionAsExecuted($version);

        $result = $this->connection->fetchOne(
            "SELECT * FROM aurum_migrations WHERE version = ?",
            [$version]
        );

        $this->assertNotNull($result);
        $this->assertEquals($version, $result['version']);
        $this->assertEquals('', $result['description']);
        $this->assertEquals(0.0, (float) $result['execution_time']);
    }

    public function testExecutionOrder(): void
    {
        $this->repository->ensureMigrationTableExists();

        // Execute migrations in non-chronological order
        $this->repository->markVersionAsExecuted('20231201140000', 'Third migration');
        usleep(10000); // 10ms delay
        $this->repository->markVersionAsExecuted('20231201120000', 'First migration');
        usleep(10000); // 10ms delay
        $this->repository->markVersionAsExecuted('20231201130000', 'Second migration');

        $executedVersions = $this->repository->getExecutedVersions();

        // Should be ordered by execution time, not version
        $this->assertEquals(['20231201140000', '20231201120000', '20231201130000'], $executedVersions);
    }
}
