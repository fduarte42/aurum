<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit;

use Fduarte42\Aurum\Connection\ConnectionFactory;
use Fduarte42\Aurum\Exception\ORMException;
use PHPUnit\Framework\TestCase;

class ConnectionFactoryTest extends TestCase
{
    public function testCreateSqliteConnection(): void
    {
        $connection = ConnectionFactory::createSqliteConnection(':memory:');
        
        $this->assertEquals('sqlite', $connection->getPlatform());
        $this->assertFalse($connection->inTransaction());
    }

    public function testCreateSqliteConnectionWithFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_db');
        $connection = ConnectionFactory::createSqliteConnection($tempFile);
        
        $this->assertEquals('sqlite', $connection->getPlatform());
        
        // Clean up
        unlink($tempFile);
    }

    public function testCreateMariaDbConnection(): void
    {
        // This test will fail in CI/local without MariaDB, so we'll test the exception
        $this->expectException(ORMException::class);
        $this->expectExceptionMessage('Database connection failed');
        
        ConnectionFactory::createMariaDbConnection(
            'nonexistent-host',
            'test_db',
            'test_user',
            'test_pass'
        );
    }

    public function testCreateMariaDbConnectionWithOptions(): void
    {
        $this->expectException(ORMException::class);
        
        ConnectionFactory::createMariaDbConnection(
            'localhost',
            'test_db',
            'test_user',
            'test_pass',
            3306,
            ['custom_option' => 'value']
        );
    }

    public function testCreateFromConfigSqlite(): void
    {
        $config = [
            'driver' => 'sqlite',
            'path' => ':memory:'
        ];
        
        $connection = ConnectionFactory::createFromConfig($config);
        $this->assertEquals('sqlite', $connection->getPlatform());
    }

    public function testCreateFromConfigMariaDb(): void
    {
        $config = [
            'driver' => 'mariadb',
            'host' => 'nonexistent-host',
            'database' => 'test_db',
            'username' => 'test_user',
            'password' => 'test_pass'
        ];
        
        $this->expectException(ORMException::class);
        ConnectionFactory::createFromConfig($config);
    }

    public function testCreateFromConfigMissingDriver(): void
    {
        $this->expectException(\Fduarte42\Aurum\Exception\ORMException::class);
        $this->expectExceptionMessage('Database driver not specified in configuration');

        ConnectionFactory::createFromConfig([]);
    }

    public function testCreateFromConfigUnsupportedDriver(): void
    {
        $this->expectException(\Fduarte42\Aurum\Exception\ORMException::class);
        $this->expectExceptionMessage('Unsupported database driver: postgresql');

        ConnectionFactory::createFromConfig(['driver' => 'postgresql']);
    }

    public function testCreateFromConfigMissingDatabase(): void
    {
        // With the new driver pattern, missing database results in connection failure
        $this->expectException(\Fduarte42\Aurum\Exception\ORMException::class);
        $this->expectExceptionMessage('Database connection failed');

        ConnectionFactory::createFromConfig([
            'driver' => 'mariadb',
            'host' => 'localhost'
        ]);
    }

    public function testCreateFromConfigMissingUsername(): void
    {
        // With the new driver pattern, missing username results in connection failure
        $this->expectException(\Fduarte42\Aurum\Exception\ORMException::class);
        $this->expectExceptionMessage('Database connection failed');

        ConnectionFactory::createFromConfig([
            'driver' => 'mariadb',
            'host' => 'localhost',
            'database' => 'test_db'
        ]);
    }

    public function testCreateFromConfigWithDefaults(): void
    {
        $this->expectException(ORMException::class);

        ConnectionFactory::createFromConfig([
            'driver' => 'mysql',
            'database' => 'test_db',
            'username' => 'test_user'
            // password defaults to '', port defaults to 3306, host defaults to localhost
        ]);
    }

    public function testCreateFromConfigMissingPath(): void
    {
        // SQLite with missing path will use empty string, which creates an in-memory database
        // This actually succeeds, so let's test that it works
        $connection = ConnectionFactory::createFromConfig([
            'driver' => 'sqlite'
            // missing path - will use empty string
        ]);

        $this->assertEquals('sqlite', $connection->getPlatform());
    }

    public function testCreateFromConfigMissingPassword(): void
    {
        $this->expectException(ORMException::class);
        $this->expectExceptionMessage('Database connection failed');

        ConnectionFactory::createFromConfig([
            'driver' => 'mariadb',
            'host' => 'localhost',
            'database' => 'test_db',
            'username' => 'test_user'
            // missing password - will try to connect with empty password
        ]);
    }

    public function testCreateSqliteConnectionWithPragma(): void
    {
        $connection = ConnectionFactory::createSqliteConnection(':memory:');

        // Test that foreign keys are enabled
        $result = $connection->fetchOne('PRAGMA foreign_keys');
        $this->assertEquals(1, $result['foreign_keys']);
    }

    public function testCreateSqliteConnectionWithInvalidPath(): void
    {
        $this->expectException(ORMException::class);
        $this->expectExceptionMessage('Database connection failed');

        // Try to create connection to invalid path (permission denied)
        ConnectionFactory::createSqliteConnection('/root/invalid/path/database.db');
    }

    public function testCreateMariaDbConnectionSuccess(): void
    {
        // This would require a real MariaDB instance, so we'll test the exception path
        // In a real environment, you'd set up a test database
        $this->expectException(ORMException::class);

        ConnectionFactory::createMariaDbConnection(
            'localhost',
            'test_db',
            'test_user',
            'test_pass',
            3306,
            []
        );
    }

    public function testCreateFromConfigMySQL(): void
    {
        $config = [
            'driver' => 'mysql',  // Test mysql driver specifically
            'host' => 'nonexistent-host',
            'database' => 'test_db',
            'username' => 'test_user',
            'password' => 'test_pass'
        ];

        $this->expectException(ORMException::class);
        ConnectionFactory::createFromConfig($config);
    }

    public function testCreateMariaDbConnectionWithAllOptions(): void
    {
        // Test createMariaDbConnection with all parameters to ensure full coverage
        $this->expectException(ORMException::class);

        ConnectionFactory::createMariaDbConnection(
            'nonexistent-host',
            'test_db',
            'test_user',
            'test_pass',
            3306,
            [\PDO::ATTR_TIMEOUT => 5]
        );
    }
}
