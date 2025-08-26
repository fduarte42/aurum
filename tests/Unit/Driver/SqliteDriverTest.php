<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit\Driver;

use Fduarte42\Aurum\Driver\SqliteDriver;
use Fduarte42\Aurum\Exception\ORMException;
use PDO;
use PHPUnit\Framework\TestCase;

class SqliteDriverTest extends TestCase
{
    private SqliteDriver $driver;
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->driver = new SqliteDriver($this->pdo);
    }

    public function testGetPlatform(): void
    {
        $this->assertEquals('sqlite', $this->driver->getPlatform());
    }

    public function testGetPdo(): void
    {
        $this->assertSame($this->pdo, $this->driver->getPdo());
    }

    public function testQuoteIdentifier(): void
    {
        $this->assertEquals('"table"', $this->driver->quoteIdentifier('table'));
        $this->assertEquals('"table""name"', $this->driver->quoteIdentifier('table"name'));
    }

    public function testSupportsSavepoints(): void
    {
        $this->assertTrue($this->driver->supportsSavepoints());
    }

    public function testGetTableExistsSQL(): void
    {
        $sql = $this->driver->getTableExistsSQL();
        $this->assertEquals("SELECT name FROM sqlite_master WHERE type='table' AND name = ?", $sql);
    }

    public function testGetIndexExistsSQL(): void
    {
        $sql = $this->driver->getIndexExistsSQL();
        $this->assertEquals("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name = ? AND name = ?", $sql);
    }

    public function testGetDropIndexSQL(): void
    {
        $sql = $this->driver->getDropIndexSQL('users', 'idx_email');
        $this->assertEquals('DROP INDEX "idx_email"', $sql);
    }

    public function testGetSQLType(): void
    {
        $this->assertEquals('INTEGER', $this->driver->getSQLType('INTEGER'));
        $this->assertEquals('TEXT', $this->driver->getSQLType('VARCHAR'));
        $this->assertEquals('REAL', $this->driver->getSQLType('DECIMAL'));
        $this->assertEquals('TEXT', $this->driver->getSQLType('DATETIME'));
        $this->assertEquals('TEXT', $this->driver->getSQLType('UUID'));
        $this->assertEquals('TEXT', $this->driver->getSQLType('JSON'));
        $this->assertEquals('BLOB', $this->driver->getSQLType('BLOB'));
    }

    public function testSupportsForeignKeys(): void
    {
        $this->assertTrue($this->driver->supportsForeignKeys());
    }

    public function testSupportsAddingForeignKeys(): void
    {
        $this->assertFalse($this->driver->supportsAddingForeignKeys());
    }

    public function testSupportsDroppingForeignKeys(): void
    {
        $this->assertFalse($this->driver->supportsDroppingForeignKeys());
    }

    public function testGetConnectionInitializationSQL(): void
    {
        $sql = $this->driver->getConnectionInitializationSQL();
        $this->assertContains('PRAGMA foreign_keys = ON', $sql);
        $this->assertContains('PRAGMA journal_mode = WAL', $sql);
        $this->assertContains('PRAGMA synchronous = NORMAL', $sql);
        $this->assertContains('PRAGMA recursive_triggers = ON', $sql);
    }

    public function testExecute(): void
    {
        $stmt = $this->driver->execute('SELECT 1 as test');
        $this->assertInstanceOf(\PDOStatement::class, $stmt);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals(['test' => 1], $result);
    }

    public function testExecuteWithParameters(): void
    {
        $this->driver->execute('CREATE TABLE test (id INTEGER, name TEXT)');
        $this->driver->execute('INSERT INTO test (id, name) VALUES (?, ?)', [1, 'test']);
        
        $result = $this->driver->fetchOne('SELECT * FROM test WHERE id = ?', [1]);
        $this->assertEquals(['id' => 1, 'name' => 'test'], $result);
    }

    public function testFetchOne(): void
    {
        $this->driver->execute('CREATE TABLE test (id INTEGER, name TEXT)');
        $this->driver->execute('INSERT INTO test (id, name) VALUES (1, "first"), (2, "second")');
        
        $result = $this->driver->fetchOne('SELECT * FROM test WHERE id = ?', [1]);
        $this->assertEquals(['id' => 1, 'name' => 'first'], $result);
        
        $result = $this->driver->fetchOne('SELECT * FROM test WHERE id = ?', [999]);
        $this->assertNull($result);
    }

    public function testFetchAll(): void
    {
        $this->driver->execute('CREATE TABLE test (id INTEGER, name TEXT)');
        $this->driver->execute('INSERT INTO test (id, name) VALUES (1, "first"), (2, "second")');
        
        $results = $this->driver->fetchAll('SELECT * FROM test ORDER BY id');
        $this->assertCount(2, $results);
        $this->assertEquals(['id' => 1, 'name' => 'first'], $results[0]);
        $this->assertEquals(['id' => 2, 'name' => 'second'], $results[1]);
    }

    public function testTransactions(): void
    {
        $this->driver->execute('CREATE TABLE test (id INTEGER, name TEXT)');
        
        $this->assertFalse($this->driver->inTransaction());
        
        $this->driver->beginTransaction();
        $this->assertTrue($this->driver->inTransaction());
        
        $this->driver->execute('INSERT INTO test (id, name) VALUES (1, "test")');
        $this->driver->commit();
        
        $this->assertFalse($this->driver->inTransaction());
        
        $result = $this->driver->fetchOne('SELECT COUNT(*) as count FROM test');
        $this->assertEquals(1, $result['count']);
    }

    public function testTransactionRollback(): void
    {
        $this->driver->execute('CREATE TABLE test (id INTEGER, name TEXT)');
        
        $this->driver->beginTransaction();
        $this->driver->execute('INSERT INTO test (id, name) VALUES (1, "test")');
        $this->driver->rollback();
        
        $result = $this->driver->fetchOne('SELECT COUNT(*) as count FROM test');
        $this->assertEquals(0, $result['count']);
    }

    public function testSavepoints(): void
    {
        $this->driver->execute('CREATE TABLE test (id INTEGER, name TEXT)');
        
        $this->driver->beginTransaction();
        $this->driver->execute('INSERT INTO test (id, name) VALUES (1, "first")');
        
        $this->driver->createSavepoint('sp1');
        $this->driver->execute('INSERT INTO test (id, name) VALUES (2, "second")');
        
        $this->driver->rollbackToSavepoint('sp1');
        $this->driver->commit();
        
        $results = $this->driver->fetchAll('SELECT * FROM test');
        $this->assertCount(1, $results);
        $this->assertEquals(['id' => 1, 'name' => 'first'], $results[0]);
    }

    public function testGenerateSavepointName(): void
    {
        $name1 = $this->driver->generateSavepointName();
        $name2 = $this->driver->generateSavepointName();
        
        $this->assertStringStartsWith('sp_', $name1);
        $this->assertStringStartsWith('sp_', $name2);
        $this->assertNotEquals($name1, $name2);
    }

    public function testQuote(): void
    {
        $this->assertEquals("'test'", $this->driver->quote('test'));
        $this->assertEquals('NULL', $this->driver->quote(null));
        $this->assertEquals("'123'", $this->driver->quote(123));
    }

    public function testLastInsertId(): void
    {
        $this->driver->execute('CREATE TABLE test (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $this->driver->execute('INSERT INTO test (name) VALUES (?)', ['test']);
        
        $lastId = $this->driver->lastInsertId();
        $this->assertEquals('1', $lastId);
    }

    public function testIsInMemory(): void
    {
        $this->assertTrue($this->driver->isInMemory());
    }

    public function testGetVersion(): void
    {
        $version = $this->driver->getVersion();
        $this->assertIsString($version);
        $this->assertNotEmpty($version);
    }

    public function testForeignKeyConstraints(): void
    {
        $this->driver->setForeignKeyConstraints(false);
        $this->assertFalse($this->driver->getForeignKeyConstraints());
        
        $this->driver->setForeignKeyConstraints(true);
        $this->assertTrue($this->driver->getForeignKeyConstraints());
    }

    public function testIntegrityCheck(): void
    {
        $results = $this->driver->integrityCheck();
        $this->assertIsArray($results);
        $this->assertContains('ok', $results);
    }

    public function testGetTableInfo(): void
    {
        $this->driver->execute('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        
        $info = $this->driver->getTableInfo('test');
        $this->assertIsArray($info);
        $this->assertCount(2, $info);
        
        $this->assertEquals('id', $info[0]['name']);
        $this->assertEquals('name', $info[1]['name']);
    }

    public function testExecuteFailure(): void
    {
        $this->expectException(ORMException::class);
        $this->driver->execute('INVALID SQL');
    }
}
