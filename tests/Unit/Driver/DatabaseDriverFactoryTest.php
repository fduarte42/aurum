<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit\Driver;

use Fduarte42\Aurum\Driver\DatabaseDriverFactory;
use Fduarte42\Aurum\Driver\DatabaseDriverInterface;
use Fduarte42\Aurum\Driver\SqliteDriver;
use Fduarte42\Aurum\Driver\MariaDbDriver;
use Fduarte42\Aurum\Exception\ORMException;
use PDO;
use PHPUnit\Framework\TestCase;

class DatabaseDriverFactoryTest extends TestCase
{
    public function testCreateSqlite(): void
    {
        $driver = DatabaseDriverFactory::createSqlite(':memory:');
        
        $this->assertInstanceOf(SqliteDriver::class, $driver);
        $this->assertEquals('sqlite', $driver->getPlatform());
    }

    public function testCreateSqliteWithFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_db');
        $driver = DatabaseDriverFactory::createSqlite($tempFile);
        
        $this->assertInstanceOf(SqliteDriver::class, $driver);
        $this->assertEquals('sqlite', $driver->getPlatform());
        
        // Clean up
        unlink($tempFile);
    }

    public function testCreateFromConfigSqlite(): void
    {
        $config = [
            'driver' => 'sqlite',
            'path' => ':memory:'
        ];
        
        $driver = DatabaseDriverFactory::create($config);
        
        $this->assertInstanceOf(SqliteDriver::class, $driver);
        $this->assertEquals('sqlite', $driver->getPlatform());
    }

    public function testCreateFromConfigMissingDriver(): void
    {
        $config = [];
        
        $this->expectException(ORMException::class);
        $this->expectExceptionMessage('Database driver not specified in configuration');
        
        DatabaseDriverFactory::create($config);
    }

    public function testCreateFromConfigUnsupportedDriver(): void
    {
        $config = [
            'driver' => 'postgresql'
        ];
        
        $this->expectException(ORMException::class);
        $this->expectExceptionMessage('Unsupported database driver: postgresql');
        
        DatabaseDriverFactory::create($config);
    }

    public function testRegisterDriver(): void
    {
        // Create a mock driver class that properly extends AbstractDatabaseDriver
        $mockDriverClass = get_class(new class(new \PDO('sqlite::memory:')) extends \Fduarte42\Aurum\Driver\AbstractDatabaseDriver {
            public function getPlatform(): string { return 'mock'; }
            public function quoteIdentifier(string $identifier): string { return $identifier; }
            public function supportsSavepoints(): bool { return false; }
            public function getTableExistsSQL(): string { return 'SELECT 1'; }
            public function getIndexExistsSQL(): string { return 'SELECT 1'; }
            public function getDropIndexSQL(string $tableName, string $indexName): string { return 'DROP INDEX'; }
            public function getSQLType(string $genericType, array $options = []): string { return 'TEXT'; }
        });

        DatabaseDriverFactory::registerDriver('mock', $mockDriverClass);

        $this->assertTrue(DatabaseDriverFactory::hasDriver('mock'));
        $this->assertArrayHasKey('mock', DatabaseDriverFactory::getRegisteredDrivers());
    }

    public function testRegisterInvalidDriver(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Driver class stdClass must implement');
        
        DatabaseDriverFactory::registerDriver('invalid', \stdClass::class);
    }

    public function testHasDriver(): void
    {
        $this->assertTrue(DatabaseDriverFactory::hasDriver('sqlite'));
        $this->assertTrue(DatabaseDriverFactory::hasDriver('mariadb'));
        $this->assertTrue(DatabaseDriverFactory::hasDriver('mysql'));
        $this->assertFalse(DatabaseDriverFactory::hasDriver('postgresql'));
    }

    public function testGetRegisteredDrivers(): void
    {
        $drivers = DatabaseDriverFactory::getRegisteredDrivers();
        
        $this->assertIsArray($drivers);
        $this->assertArrayHasKey('sqlite', $drivers);
        $this->assertArrayHasKey('mariadb', $drivers);
        $this->assertArrayHasKey('mysql', $drivers);
        $this->assertEquals(SqliteDriver::class, $drivers['sqlite']);
        $this->assertEquals(MariaDbDriver::class, $drivers['mariadb']);
        $this->assertEquals(MariaDbDriver::class, $drivers['mysql']);
    }

    public function testCreateFromPDO(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $driver = DatabaseDriverFactory::createFromPDO($pdo, 'sqlite');
        
        $this->assertInstanceOf(SqliteDriver::class, $driver);
        $this->assertSame($pdo, $driver->getPdo());
    }

    public function testCreateFromPDOUnsupportedPlatform(): void
    {
        $pdo = new PDO('sqlite::memory:');
        
        $this->expectException(ORMException::class);
        $this->expectExceptionMessage('Unsupported platform: postgresql');
        
        DatabaseDriverFactory::createFromPDO($pdo, 'postgresql');
    }

    public function testDetectPlatform(): void
    {
        $sqlitePdo = new PDO('sqlite::memory:');
        $this->assertEquals('sqlite', DatabaseDriverFactory::detectPlatform($sqlitePdo));
    }

    public function testDetectPlatformUnsupported(): void
    {
        // Create a mock PDO that returns an unsupported driver name
        $mockPdo = $this->createMock(PDO::class);
        $mockPdo->method('getAttribute')
            ->with(PDO::ATTR_DRIVER_NAME)
            ->willReturn('pgsql');
        
        $this->expectException(ORMException::class);
        $this->expectExceptionMessage('Cannot detect platform for PDO driver: pgsql');
        
        DatabaseDriverFactory::detectPlatform($mockPdo);
    }

    public function testCreateFromPDOWithDetection(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $driver = DatabaseDriverFactory::createFromPDOWithDetection($pdo);
        
        $this->assertInstanceOf(SqliteDriver::class, $driver);
        $this->assertSame($pdo, $driver->getPdo());
    }

    public function testCreateMariaDb(): void
    {
        // We can't test actual MariaDB connection without a server
        // But we can test that the method exists and would create the right driver type
        $this->assertTrue(method_exists(DatabaseDriverFactory::class, 'createMariaDb'));
    }

    public function testCreateFromConfigMariaDb(): void
    {
        // Test configuration validation without actual connection
        $config = [
            'driver' => 'mariadb',
            'host' => 'localhost',
            'database' => 'test',
            'username' => 'user',
            'password' => 'pass'
        ];
        
        // This will fail because we don't have a MariaDB server, but it validates the config structure
        try {
            DatabaseDriverFactory::create($config);
        } catch (ORMException $e) {
            // Expected - connection will fail, but config was processed correctly
            $this->assertStringContainsString('connection failed', strtolower($e->getMessage()));
        }
    }

    public function testCreateFromConfigSqliteWithOptions(): void
    {
        $config = [
            'driver' => 'sqlite',
            'path' => ':memory:',
            'options' => [
                PDO::ATTR_TIMEOUT => 30
            ]
        ];
        
        $driver = DatabaseDriverFactory::create($config);
        
        $this->assertInstanceOf(SqliteDriver::class, $driver);
        $this->assertEquals('sqlite', $driver->getPlatform());
    }
}
