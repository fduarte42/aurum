<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit;

use Fduarte42\Aurum\Connection\Connection;
use Fduarte42\Aurum\Driver\SqliteDriver;
use Fduarte42\Aurum\Query\QueryBuilder;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * Test Iterator functionality in QueryBuilder
 */
class QueryBuilderPDOStatementTest extends TestCase
{
    private QueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $driver = new SqliteDriver($pdo);
        $connection = new Connection($driver);
        $this->queryBuilder = new QueryBuilder($connection);
    }

    public function testGetArrayResultReturnsIterator(): void
    {
        // Create test table and data
        $connection = $this->queryBuilder->getConnection();
        $connection->execute('CREATE TABLE test_table (id INTEGER, name TEXT, value REAL)');
        $connection->execute('INSERT INTO test_table VALUES (1, "first", 10.5)');
        $connection->execute('INSERT INTO test_table VALUES (2, "second", 20.7)');

        $iterator = $this->queryBuilder
            ->select('*')
            ->from('test_table', 't')
            ->orderBy('t.id')
            ->getArrayResult();

        $this->assertInstanceOf(\Iterator::class, $iterator);
    }

    public function testIteratorFunctionality(): void
    {
        // Create test table and data
        $connection = $this->queryBuilder->getConnection();
        $connection->execute('CREATE TABLE test_table (id INTEGER, name TEXT, value REAL)');
        $connection->execute('INSERT INTO test_table VALUES (1, "first", 10.5)');
        $connection->execute('INSERT INTO test_table VALUES (2, "second", 20.7)');
        $connection->execute('INSERT INTO test_table VALUES (3, "third", 30.9)');

        $iterator = $this->queryBuilder
            ->select('*')
            ->from('test_table', 't')
            ->orderBy('t.id')
            ->getArrayResult();

        // Test iteration over Iterator (fetch mode already set to ASSOC)
        $results = [];
        foreach ($iterator as $row) {
            $results[] = $row;
        }

        $this->assertCount(3, $results);
        $this->assertEquals(['id' => 1, 'name' => 'first', 'value' => 10.5], $results[0]);
        $this->assertEquals(['id' => 2, 'name' => 'second', 'value' => 20.7], $results[1]);
        $this->assertEquals(['id' => 3, 'name' => 'third', 'value' => 30.9], $results[2]);
    }

    public function testIteratorWithAssociativeArrays(): void
    {
        // Create test table and data
        $connection = $this->queryBuilder->getConnection();
        $connection->execute('CREATE TABLE test_table (id INTEGER, name TEXT)');
        $connection->execute('INSERT INTO test_table VALUES (1, "test")');

        $iterator = $this->queryBuilder
            ->select('*')
            ->from('test_table', 't')
            ->getArrayResult();

        // Test that iterator returns associative arrays (FETCH_ASSOC is set by default)
        foreach ($iterator as $row) {
            $this->assertEquals(['id' => 1, 'name' => 'test'], $row);
            $this->assertIsArray($row);
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('name', $row);
            break; // Only test first row
        }
    }

    public function testIteratorMemoryEfficiency(): void
    {
        // Create test table with more data
        $connection = $this->queryBuilder->getConnection();
        $connection->execute('CREATE TABLE large_table (id INTEGER, data TEXT)');

        // Insert multiple rows
        for ($i = 1; $i <= 100; $i++) {
            $connection->execute("INSERT INTO large_table VALUES ({$i}, 'data{$i}')");
        }

        $memoryBefore = memory_get_usage();

        $iterator = $this->queryBuilder
            ->select('*')
            ->from('large_table', 't')
            ->getArrayResult();

        $memoryAfter = memory_get_usage();

        // Memory usage should be minimal since we're not loading all data into an array
        $memoryDiff = $memoryAfter - $memoryBefore;

        // Should use much less memory than loading 100 rows into an array
        $this->assertLessThan(50000, $memoryDiff, 'Iterator should use minimal memory');
        
        // Verify we can still iterate over all results
        $count = 0;
        foreach ($iterator as $row) {
            $count++;
        }

        $this->assertEquals(100, $count);
    }

    public function testIteratorErrorHandling(): void
    {
        $this->expectException(\Fduarte42\Aurum\Exception\ORMException::class);
        $this->expectExceptionMessage('Query failed');

        // Test with invalid SQL
        $this->queryBuilder
            ->select('invalid_column')
            ->from('nonexistent_table', 't')
            ->getArrayResult();
    }

    public function testIteratorWithComplexQuery(): void
    {
        // Create test tables with relationships
        $connection = $this->queryBuilder->getConnection();
        $connection->execute('CREATE TABLE users (id INTEGER, name TEXT, email TEXT)');
        $connection->execute('CREATE TABLE posts (id INTEGER, user_id INTEGER, title TEXT)');

        $connection->execute('INSERT INTO users VALUES (1, "John", "john@example.com")');
        $connection->execute('INSERT INTO users VALUES (2, "Jane", "jane@example.com")');
        $connection->execute('INSERT INTO posts VALUES (1, 1, "Post 1")');
        $connection->execute('INSERT INTO posts VALUES (2, 1, "Post 2")');
        $connection->execute('INSERT INTO posts VALUES (3, 2, "Post 3")');

        $iterator = $this->queryBuilder
            ->select('COUNT(*) as post_count')
            ->from('users', 'u')
            ->innerJoin('posts', 'p', 'u.id = p.user_id')
            ->where('u.name = :name')
            ->setParameter('name', 'John')
            ->getArrayResult();

        $results = [];
        foreach ($iterator as $row) {
            $results[] = $row;
        }

        // Should get 1 result with count
        $this->assertCount(1, $results);
        $this->assertArrayHasKey('post_count', $results[0]);
        $this->assertEquals(2, $results[0]['post_count']);
    }
}
