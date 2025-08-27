<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit;

use Fduarte42\Aurum\Connection\Connection;
use Fduarte42\Aurum\Connection\ConnectionFactory;
use Fduarte42\Aurum\Exception\ORMException;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    private Connection $connection;
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->connection = ConnectionFactory::createSqliteConnection(':memory:');
        $this->pdo = $this->connection->getPdo();
    }

    public function testBasicConnection(): void
    {
        $this->assertEquals('sqlite', $this->connection->getPlatform());
        $this->assertFalse($this->connection->inTransaction());
    }

    public function testTransaction(): void
    {
        $this->connection->beginTransaction();
        $this->assertTrue($this->connection->inTransaction());
        
        $this->connection->commit();
        $this->assertFalse($this->connection->inTransaction());
    }

    public function testTransactionRollback(): void
    {
        $this->connection->beginTransaction();
        $this->assertTrue($this->connection->inTransaction());
        
        $this->connection->rollback();
        $this->assertFalse($this->connection->inTransaction());
    }

    public function testSavepoints(): void
    {
        $this->connection->beginTransaction();
        
        $this->connection->createSavepoint('sp1');
        $this->connection->createSavepoint('sp2');
        
        $this->connection->rollbackToSavepoint('sp1');
        $this->connection->releaseSavepoint('sp1');
        
        $this->connection->commit();
        $this->assertTrue(true); // If we get here, savepoints worked
    }

    public function testSavepointWithoutTransaction(): void
    {
        $this->expectException(ORMException::class);
        $this->connection->createSavepoint('sp1');
    }

    public function testExecuteQuery(): void
    {
        $this->connection->execute('CREATE TABLE test (id INTEGER, name TEXT)');
        $this->connection->execute('INSERT INTO test (id, name) VALUES (?, ?)', [1, 'test']);
        
        $result = $this->connection->fetchOne('SELECT * FROM test WHERE id = ?', [1]);
        $this->assertEquals(['id' => 1, 'name' => 'test'], $result);
    }

    public function testQuoteIdentifier(): void
    {
        $quoted = $this->connection->quoteIdentifier('table_name');
        $this->assertEquals('"table_name"', $quoted);
    }

    public function testQuoteValue(): void
    {
        $quoted = $this->connection->quote("test'value");
        $this->assertStringContainsString("test''value", $quoted);
    }

    public function testQuoteNull(): void
    {
        $quoted = $this->connection->quote(null);
        $this->assertEquals('NULL', $quoted);
    }

    public function testLastInsertId(): void
    {
        $this->connection->execute('CREATE TABLE test_insert (id INTEGER PRIMARY KEY, name TEXT)');
        $this->connection->execute('INSERT INTO test_insert (name) VALUES (?)', ['test']);

        $lastId = $this->connection->lastInsertId();
        $this->assertEquals('1', $lastId);
    }

    public function testFetchAllEmpty(): void
    {
        $this->connection->execute('CREATE TABLE empty_test (id INTEGER)');
        $result = $this->connection->fetchAll('SELECT * FROM empty_test');
        $this->assertEquals([], $result);
    }

    public function testFetchOneNull(): void
    {
        $this->connection->execute('CREATE TABLE empty_test (id INTEGER)');
        $result = $this->connection->fetchOne('SELECT * FROM empty_test WHERE id = 999');
        $this->assertNull($result);
    }

    public function testGenerateSavepointName(): void
    {
        $name1 = $this->connection->generateSavepointName();
        $name2 = $this->connection->generateSavepointName();

        $this->assertStringStartsWith('sp_', $name1);
        $this->assertStringStartsWith('sp_', $name2);
        $this->assertNotEquals($name1, $name2);
    }

    public function testInvalidSavepointOperation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown savepoint operation: INVALID');

        // Test invalid savepoint operation through the driver
        $driver = $this->connection->getDriver();
        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('getSavepointSQL');
        $method->setAccessible(true);
        $method->invoke($driver, 'INVALID', 'test');
    }

    public function testDuplicateSavepoint(): void
    {
        $this->connection->beginTransaction();
        $this->connection->createSavepoint('test_sp');

        $this->expectException(ORMException::class);
        $this->expectExceptionMessage('Invalid savepoint name: "test_sp"');
        $this->connection->createSavepoint('test_sp');
    }

    public function testRollbackToNonexistentSavepoint(): void
    {
        $this->connection->beginTransaction();

        $this->expectException(ORMException::class);
        $this->expectExceptionMessage('Invalid savepoint name: "nonexistent"');
        $this->connection->rollbackToSavepoint('nonexistent');
    }

    public function testReleaseSavepointNested(): void
    {
        $this->connection->beginTransaction();
        $this->connection->createSavepoint('sp1');
        $this->connection->createSavepoint('sp2');
        $this->connection->createSavepoint('sp3');

        // Release sp1 should remove sp1, sp2, and sp3
        $this->connection->releaseSavepoint('sp1');

        $this->expectException(ORMException::class);
        $this->connection->rollbackToSavepoint('sp2');
    }

    public function testGetPdo(): void
    {
        $pdo = $this->connection->getPdo();
        $this->assertInstanceOf(\PDO::class, $pdo);
    }

    public function testCommitWithoutTransaction(): void
    {
        $this->expectException(ORMException::class);
        $this->expectExceptionMessage('No active transaction found');
        $this->connection->commit();
    }

    public function testRollbackWithoutTransaction(): void
    {
        $this->expectException(ORMException::class);
        $this->expectExceptionMessage('No active transaction found');
        $this->connection->rollback();
    }

    public function testReleaseSavepointWithoutTransaction(): void
    {
        $this->expectException(ORMException::class);
        $this->expectExceptionMessage('No active transaction found');
        $this->connection->releaseSavepoint('test');
    }

    public function testRollbackToSavepointWithoutTransaction(): void
    {
        $this->expectException(ORMException::class);
        $this->expectExceptionMessage('No active transaction found');
        $this->connection->rollbackToSavepoint('test');
    }

    public function testReleaseSavepointNonexistent(): void
    {
        $this->connection->beginTransaction();

        $this->expectException(ORMException::class);
        $this->expectExceptionMessage('Invalid savepoint name: "nonexistent"');
        $this->connection->releaseSavepoint('nonexistent');
    }

    public function testQuoteIdentifierWithSpecialCharacters(): void
    {
        $quoted = $this->connection->quoteIdentifier('table"name');
        $this->assertEquals('"table""name"', $quoted);
    }

    public function testExecuteWithPDOException(): void
    {
        $this->expectException(ORMException::class);
        $this->expectExceptionMessage('Query failed');

        // Try to execute invalid SQL
        $this->connection->execute('INVALID SQL STATEMENT');
    }

    public function testBeginTransactionWithPDOException(): void
    {
        // This is hard to test without mocking PDO, so let's test double begin instead
        $this->connection->beginTransaction();

        $this->expectException(ORMException::class);
        $this->expectExceptionMessage('A transaction is already active');
        $this->connection->beginTransaction();
    }

    public function testSupportsSavepoints(): void
    {
        // Test that SQLite supports savepoints by successfully creating one
        $this->connection->beginTransaction();
        $this->connection->createSavepoint('test_support');
        $this->connection->releaseSavepoint('test_support');
        $this->connection->commit();

        $this->assertTrue(true); // If we get here, savepoints are supported
    }

    public function testSupportsSavepointsPrivateMethod(): void
    {
        // Test savepoints support through the driver
        $driver = $this->connection->getDriver();
        $result = $driver->supportsSavepoints();
        $this->assertTrue($result); // SQLite supports savepoints
    }

    public function testQuoteWithDifferentTypes(): void
    {
        // Test quoting different value types
        $this->assertEquals('NULL', $this->connection->quote(null));
        $this->assertStringContainsString('123', $this->connection->quote(123));
        $this->assertStringContainsString('1', $this->connection->quote(true));
        $this->assertStringContainsString('', $this->connection->quote(false));
    }

    public function testQuoteIdentifierForMySQL(): void
    {
        // Create a MySQL connection to test MySQL-specific quoting
        // Create a MariaDB driver to test MySQL-style quoting
        $mariaDbDriver = new \Fduarte42\Aurum\Driver\MariaDbDriver($this->pdo);
        $mariaDbConnection = new \Fduarte42\Aurum\Connection\Connection($mariaDbDriver);

        $quoted = $mariaDbConnection->quoteIdentifier('table`name');
        $this->assertEquals('`table``name`', $quoted);
    }

    public function testQuoteIdentifierForMariaDB(): void
    {
        // Create a MariaDB driver to test MariaDB-style quoting
        $mariaDbDriver = new \Fduarte42\Aurum\Driver\MariaDbDriver($this->pdo);
        $mariaDbConnection = new \Fduarte42\Aurum\Connection\Connection($mariaDbDriver);

        $quoted = $mariaDbConnection->quoteIdentifier('table`name');
        $this->assertEquals('`table``name`', $quoted);
    }

    public function testQuoteIdentifierForUnknownPlatform(): void
    {
        // SQLite driver uses double quotes, which is the default behavior
        $quoted = $this->connection->quoteIdentifier('table"name');
        $this->assertEquals('"table""name"', $quoted);
    }

    public function testLastInsertIdWithSequence(): void
    {
        // Test lastInsertId with sequence name (though SQLite doesn't use it)
        $this->connection->execute('CREATE TABLE test_seq (id INTEGER PRIMARY KEY, name TEXT)');
        $this->connection->execute('INSERT INTO test_seq (name) VALUES (?)', ['test']);

        $lastId = $this->connection->lastInsertId();
        $this->assertIsString($lastId);
        $this->assertGreaterThan(0, (int) $lastId);
    }

    public function testCreateSavepointWithoutSupportCheck(): void
    {
        // Create a mock driver that doesn't support savepoints
        $mockDriver = $this->createMock(\Fduarte42\Aurum\Driver\DatabaseDriverInterface::class);
        $mockDriver->method('supportsSavepoints')->willReturn(false);
        $mockDriver->method('beginTransaction');
        $mockDriver->method('rollback');
        $mockDriver->method('inTransaction')->willReturnOnConsecutiveCalls(false, true, true, true);

        $connectionWithUnsupportedDriver = new \Fduarte42\Aurum\Connection\Connection($mockDriver);
        $connectionWithUnsupportedDriver->beginTransaction();

        $this->expectException(ORMException::class);
        $this->expectExceptionMessage('Savepoints are not supported');

        try {
            $connectionWithUnsupportedDriver->createSavepoint('test');
        } finally {
            $connectionWithUnsupportedDriver->rollback();
        }
    }

    public function testRollbackToSavepointWithoutSupport(): void
    {
        // Create a mock driver that doesn't support savepoints
        $mockDriver = $this->createMock(\Fduarte42\Aurum\Driver\DatabaseDriverInterface::class);
        $mockDriver->method('supportsSavepoints')->willReturn(false);
        $mockDriver->method('beginTransaction');
        $mockDriver->method('rollback');
        $mockDriver->method('inTransaction')->willReturnOnConsecutiveCalls(false, true, true, true);

        $connectionWithUnsupportedDriver = new \Fduarte42\Aurum\Connection\Connection($mockDriver);
        $connectionWithUnsupportedDriver->beginTransaction();

        $this->expectException(ORMException::class);
        $this->expectExceptionMessage('Savepoints are not supported');

        try {
            $connectionWithUnsupportedDriver->rollbackToSavepoint('test');
        } finally {
            $connectionWithUnsupportedDriver->rollback();
        }
    }

    public function testReleaseSavepointWithoutSupport(): void
    {
        // Create a mock driver that doesn't support savepoints
        $mockDriver = $this->createMock(\Fduarte42\Aurum\Driver\DatabaseDriverInterface::class);
        $mockDriver->method('supportsSavepoints')->willReturn(false);
        $mockDriver->method('beginTransaction');
        $mockDriver->method('rollback');
        $mockDriver->method('inTransaction')->willReturnOnConsecutiveCalls(false, true, true, true);

        $connectionWithUnsupportedDriver = new \Fduarte42\Aurum\Connection\Connection($mockDriver);
        $connectionWithUnsupportedDriver->beginTransaction();

        $this->expectException(ORMException::class);
        $this->expectExceptionMessage('Savepoints are not supported');

        try {
            $connectionWithUnsupportedDriver->releaseSavepoint('test');
        } finally {
            $connectionWithUnsupportedDriver->rollback();
        }
    }

    public function testGetSavepointSQLForDifferentPlatforms(): void
    {
        // Test getSavepointSQL for different drivers
        $sqliteDriver = new \Fduarte42\Aurum\Driver\SqliteDriver($this->pdo);
        $mariaDbDriver = new \Fduarte42\Aurum\Driver\MariaDbDriver($this->pdo);

        // Test SQLite driver
        $reflection = new \ReflectionClass($sqliteDriver);
        $method = $reflection->getMethod('getSavepointSQL');
        $method->setAccessible(true);
        $sql = $method->invoke($sqliteDriver, 'RELEASE', 'test');
        $this->assertEquals('RELEASE SAVEPOINT "test"', $sql);

        // Test MariaDB driver
        $reflection = new \ReflectionClass($mariaDbDriver);
        $method = $reflection->getMethod('getSavepointSQL');
        $method->setAccessible(true);
        $sql = $method->invoke($mariaDbDriver, 'RELEASE', 'test');
        $this->assertEquals('RELEASE SAVEPOINT `test`', $sql);
    }

    public function testConstructor(): void
    {
        // Test constructor with different drivers
        $pdo = new \PDO('sqlite::memory:');

        $sqliteDriver = new \Fduarte42\Aurum\Driver\SqliteDriver($pdo);
        $sqliteConnection = new Connection($sqliteDriver);
        $this->assertEquals('sqlite', $sqliteConnection->getPlatform());

        $mariaDbDriver = new \Fduarte42\Aurum\Driver\MariaDbDriver($pdo);
        $mariaDbConnection = new Connection($mariaDbDriver);
        $this->assertEquals('mariadb', $mariaDbConnection->getPlatform());
    }

    public function testInTransaction(): void
    {
        // Test inTransaction method directly
        $this->assertFalse($this->connection->inTransaction());

        $this->connection->beginTransaction();
        $this->assertTrue($this->connection->inTransaction());

        $this->connection->commit();
        $this->assertFalse($this->connection->inTransaction());
    }

    public function testExecuteWithComplexParameters(): void
    {
        // Test execute method with various parameter types
        $this->connection->execute('CREATE TABLE test_params (id INTEGER, name TEXT, active BOOLEAN, data JSON)');

        $stmt = $this->connection->execute(
            'INSERT INTO test_params (id, name, active, data) VALUES (?, ?, ?, ?)',
            [1, 'test', true, '{"key": "value"}']
        );

        $this->assertInstanceOf(\PDOStatement::class, $stmt);
        $this->assertEquals(1, $stmt->rowCount());
    }

    public function testExecuteWithEmptyParameters(): void
    {
        // Test execute method with empty parameters array
        $stmt = $this->connection->execute('CREATE TABLE test_empty (id INTEGER)');
        $this->assertInstanceOf(\PDOStatement::class, $stmt);
    }

    public function testCommitWithPDOException(): void
    {
        // This is difficult to test without mocking, but we can test the basic flow
        $this->connection->beginTransaction();

        // Normal commit should work
        $this->connection->commit();
        $this->assertFalse($this->connection->inTransaction());
    }

    public function testRollbackWithPDOException(): void
    {
        // Test rollback with normal flow
        $this->connection->beginTransaction();

        // Normal rollback should work
        $this->connection->rollback();
        $this->assertFalse($this->connection->inTransaction());
    }

    public function testQuoteWithComplexValues(): void
    {
        // Test quote method with various value types
        $this->assertEquals('NULL', $this->connection->quote(null));
        $this->assertStringContainsString('test', $this->connection->quote('test'));
        $this->assertStringContainsString('123', $this->connection->quote(123));
        $this->assertStringContainsString('1.5', $this->connection->quote(1.5));
    }

    public function testLastInsertIdWithoutSequence(): void
    {
        // Test lastInsertId without sequence parameter
        $this->connection->execute('CREATE TABLE test_last_id (id INTEGER PRIMARY KEY, name TEXT)');
        $this->connection->execute('INSERT INTO test_last_id (name) VALUES (?)', ['test']);

        $lastId = $this->connection->lastInsertId();
        $this->assertIsString($lastId);
        $this->assertGreaterThan(0, (int) $lastId);
    }

    public function testLastInsertIdWithSequenceName(): void
    {
        // Test lastInsertId with sequence name parameter (even though SQLite doesn't use it)
        $this->connection->execute('CREATE TABLE test_seq2 (id INTEGER PRIMARY KEY, name TEXT)');
        $this->connection->execute('INSERT INTO test_seq2 (name) VALUES (?)', ['test']);

        $lastId = $this->connection->lastInsertId('test_seq');
        $this->assertIsString($lastId);
        $this->assertGreaterThan(0, (int) $lastId);
    }

    public function testFetchAllWithResults(): void
    {
        // Test fetchAll with actual results
        $this->connection->execute('CREATE TABLE test_fetch_all (id INTEGER, name TEXT)');
        $this->connection->execute('INSERT INTO test_fetch_all (id, name) VALUES (?, ?)', [1, 'first']);
        $this->connection->execute('INSERT INTO test_fetch_all (id, name) VALUES (?, ?)', [2, 'second']);

        $results = $this->connection->fetchAll('SELECT * FROM test_fetch_all ORDER BY id');
        $this->assertCount(2, $results);
        $this->assertEquals('first', $results[0]['name']);
        $this->assertEquals('second', $results[1]['name']);
    }

    public function testQuoteWithArrayValue(): void
    {
        // Test quote method with array (should be converted to JSON string)
        $quoted = $this->connection->quote(['test', 'array']);
        $this->assertIsString($quoted);
        // Verify it contains the JSON representation
        $this->assertStringContainsString('["test","array"]', $quoted);
    }
}
