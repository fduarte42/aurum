<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit;

use Fduarte42\Aurum\Connection\ConnectionFactory;
use Fduarte42\Aurum\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;

class QueryBuilderTest extends TestCase
{
    private QueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        $connection = ConnectionFactory::createSqliteConnection(':memory:');
        $this->queryBuilder = new QueryBuilder($connection);
    }

    public function testBasicSelect(): void
    {
        $sql = $this->queryBuilder
            ->select(['id', 'name'])
            ->from('users', 'u')
            ->getSQL();

        $this->assertEquals('SELECT id, name FROM "users" "u"', $sql);
    }

    public function testSelectWithWhere(): void
    {
        $sql = $this->queryBuilder
            ->select('*')
            ->from('users', 'u')
            ->where('u.active = :active')
            ->andWhere('u.age > :age')
            ->setParameter('active', true)
            ->setParameter('age', 18)
            ->getSQL();

        $expected = 'SELECT * FROM "users" "u" WHERE u.active = :active AND u.age > :age';
        $this->assertEquals($expected, $sql);
        
        $parameters = $this->queryBuilder->getParameters();
        $this->assertEquals(['active' => true, 'age' => 18], $parameters);
    }

    public function testSelectWithJoins(): void
    {
        $sql = $this->queryBuilder
            ->select(['u.name', 't.title'])
            ->from('users', 'u')
            ->innerJoin('todos', 't', 'u.id = t.user_id')
            ->leftJoin('categories', 'c', 't.category_id = c.id')
            ->getSQL();

        $expected = 'SELECT u.name, t.title FROM "users" "u" INNER JOIN "todos" "t" ON u.id = t.user_id LEFT JOIN "categories" "c" ON t.category_id = c.id';
        $this->assertEquals($expected, $sql);
    }

    public function testSelectWithGroupByAndHaving(): void
    {
        $sql = $this->queryBuilder
            ->select(['u.department', 'COUNT(*) as count'])
            ->from('users', 'u')
            ->groupBy('u.department')
            ->having('COUNT(*) > :minCount')
            ->setParameter('minCount', 5)
            ->getSQL();

        $expected = 'SELECT u.department, COUNT(*) as count FROM "users" "u" GROUP BY u.department HAVING COUNT(*) > :minCount';
        $this->assertEquals($expected, $sql);
    }

    public function testSelectWithOrderBy(): void
    {
        $sql = $this->queryBuilder
            ->select('*')
            ->from('users', 'u')
            ->orderBy('u.name', 'ASC')
            ->addOrderBy('u.created_at', 'DESC')
            ->getSQL();

        $expected = 'SELECT * FROM "users" "u" ORDER BY u.name ASC, u.created_at DESC';
        $this->assertEquals($expected, $sql);
    }

    public function testSelectWithLimitAndOffset(): void
    {
        $sql = $this->queryBuilder
            ->select('*')
            ->from('users', 'u')
            ->setMaxResults(10)
            ->setFirstResult(20)
            ->getSQL();

        $expected = 'SELECT * FROM "users" "u" LIMIT 10 OFFSET 20';
        $this->assertEquals($expected, $sql);
    }

    public function testOrWhere(): void
    {
        $sql = $this->queryBuilder
            ->select('*')
            ->from('users', 'u')
            ->where('u.active = :active')
            ->orWhere('u.admin = :admin')
            ->getSQL();

        $expected = 'SELECT * FROM "users" "u" WHERE (u.active = :active) OR (u.admin = :admin)';
        $this->assertEquals($expected, $sql);
    }

    public function testComplexQuery(): void
    {
        $sql = $this->queryBuilder
            ->select(['u.name', 'COUNT(t.id) as todo_count'])
            ->from('users', 'u')
            ->leftJoin('todos', 't', 'u.id = t.user_id AND t.completed = 0')
            ->where('u.active = :active')
            ->andWhere('u.created_at > :since')
            ->groupBy('u.id')
            ->having('COUNT(t.id) > :minTodos')
            ->orderBy('todo_count', 'DESC')
            ->setMaxResults(5)
            ->setParameter('active', true)
            ->setParameter('since', '2023-01-01')
            ->setParameter('minTodos', 2)
            ->getSQL();

        $expected = 'SELECT u.name, COUNT(t.id) as todo_count FROM "users" "u" LEFT JOIN "todos" "t" ON u.id = t.user_id AND t.completed = 0 WHERE u.active = :active AND u.created_at > :since GROUP BY u.id HAVING COUNT(t.id) > :minTodos ORDER BY todo_count DESC LIMIT 5';
        $this->assertEquals($expected, $sql);
    }

    public function testReset(): void
    {
        $this->queryBuilder
            ->select('*')
            ->from('users', 'u')
            ->where('u.active = :active')
            ->setParameter('active', true);

        $this->queryBuilder->reset();

        $this->expectException(\InvalidArgumentException::class);
        $this->queryBuilder->getSQL(); // Should throw because FROM is required
    }

    public function testCreateSubquery(): void
    {
        $subquery = $this->queryBuilder->createSubquery();
        $this->assertInstanceOf(\Fduarte42\Aurum\Query\QueryBuilder::class, $subquery);
        $this->assertNotSame($this->queryBuilder, $subquery);
    }

    public function testWhereIn(): void
    {
        $subquery = $this->queryBuilder->createSubquery()
            ->select('id')
            ->from('active_users', 'au')
            ->setParameter('status', 'active');

        $sql = $this->queryBuilder
            ->select('*')
            ->from('users', 'u')
            ->whereIn('u.id', $subquery)
            ->getSQL();

        $expected = 'SELECT * FROM "users" "u" WHERE u.id IN (SELECT id FROM "active_users" "au")';
        $this->assertEquals($expected, $sql);

        $parameters = $this->queryBuilder->getParameters();
        $this->assertEquals(['status' => 'active'], $parameters);
    }

    public function testWhereExists(): void
    {
        $subquery = $this->queryBuilder->createSubquery()
            ->select('1')
            ->from('todos', 't')
            ->where('t.user_id = u.id')
            ->setParameter('completed', false);

        $sql = $this->queryBuilder
            ->select('*')
            ->from('users', 'u')
            ->whereExists($subquery)
            ->getSQL();

        $expected = 'SELECT * FROM "users" "u" WHERE EXISTS (SELECT 1 FROM "todos" "t" WHERE t.user_id = u.id)';
        $this->assertEquals($expected, $sql);

        $parameters = $this->queryBuilder->getParameters();
        $this->assertEquals(['completed' => false], $parameters);
    }

    public function testWhereNotExists(): void
    {
        $subquery = $this->queryBuilder->createSubquery()
            ->select('1')
            ->from('todos', 't')
            ->where('t.user_id = u.id');

        $sql = $this->queryBuilder
            ->select('*')
            ->from('users', 'u')
            ->whereNotExists($subquery)
            ->getSQL();

        $expected = 'SELECT * FROM "users" "u" WHERE NOT EXISTS (SELECT 1 FROM "todos" "t" WHERE t.user_id = u.id)';
        $this->assertEquals($expected, $sql);
    }

    public function testGetOneOrNullResult(): void
    {
        // Create test table and data
        $connection = $this->queryBuilder->getConnection();
        $connection->execute('CREATE TABLE test_table (id INTEGER, name TEXT)');
        $connection->execute('INSERT INTO test_table VALUES (1, "test")');

        $result = $this->queryBuilder
            ->select('*')
            ->from('test_table', 't')
            ->where('t.id = :id')
            ->setParameter('id', 1)
            ->getOneOrNullResult();

        $this->assertEquals(['id' => 1, 'name' => 'test'], $result);

        $nullResult = $this->queryBuilder
            ->reset()
            ->select('*')
            ->from('test_table', 't')
            ->where('t.id = :id')
            ->setParameter('id', 999)
            ->getOneOrNullResult();

        $this->assertNull($nullResult);
    }

    public function testGetSingleScalarResult(): void
    {
        // Create test table and data
        $connection = $this->queryBuilder->getConnection();
        $connection->execute('CREATE TABLE test_table (id INTEGER, name TEXT)');
        $connection->execute('INSERT INTO test_table VALUES (1, "test")');

        $result = $this->queryBuilder
            ->select('COUNT(*)')
            ->from('test_table', 't')
            ->getSingleScalarResult();

        $this->assertEquals(1, $result);
    }

    public function testGetSingleScalarResultWithNoResult(): void
    {
        // Create empty test table
        $connection = $this->queryBuilder->getConnection();
        $connection->execute('CREATE TABLE empty_table (id INTEGER)');

        $this->expectException(\Fduarte42\Aurum\Exception\ORMException::class);
        $this->expectExceptionMessage('Entity of type "scalar" with identifier "result" not found');

        $this->queryBuilder
            ->select('*')
            ->from('empty_table', 't')
            ->getSingleScalarResult();
    }

    public function testGetArrayResult(): void
    {
        // Create test table and data
        $connection = $this->queryBuilder->getConnection();
        $connection->execute('CREATE TABLE test_table (id INTEGER, name TEXT)');
        $connection->execute('INSERT INTO test_table VALUES (1, "test1")');
        $connection->execute('INSERT INTO test_table VALUES (2, "test2")');

        $statement = $this->queryBuilder
            ->select('*')
            ->from('test_table', 't')
            ->orderBy('t.id')
            ->getArrayResult();

        // Test that we get a PDOStatement
        $this->assertInstanceOf(\PDOStatement::class, $statement);

        // Test iteration over the statement (fetch mode already set to ASSOC)
        $results = [];
        foreach ($statement as $row) {
            $results[] = $row;
        }

        $this->assertCount(2, $results);
        $this->assertEquals(['id' => 1, 'name' => 'test1'], $results[0]);
        $this->assertEquals(['id' => 2, 'name' => 'test2'], $results[1]);
    }

    public function testGetResultWithoutEntityClass(): void
    {
        // Create test table and data
        $connection = $this->queryBuilder->getConnection();
        $connection->execute('CREATE TABLE test_table (id INTEGER, name TEXT)');
        $connection->execute('INSERT INTO test_table VALUES (1, "test1")');

        $this->expectException(\Fduarte42\Aurum\Exception\ORMException::class);
        $this->expectExceptionMessage('Cannot hydrate entities: root entity class and metadata factory must be set');

        $this->queryBuilder
            ->select('*')
            ->from('test_table', 't')
            ->getResult();
    }

    public function testMultipleGroupBy(): void
    {
        $sql = $this->queryBuilder
            ->select(['u.department', 'u.role', 'COUNT(*)'])
            ->from('users', 'u')
            ->groupBy(['u.department', 'u.role'])
            ->getSQL();

        $expected = 'SELECT u.department, u.role, COUNT(*) FROM "users" "u" GROUP BY u.department, u.role';
        $this->assertEquals($expected, $sql);
    }

    public function testMultipleOrderBy(): void
    {
        $sql = $this->queryBuilder
            ->select('*')
            ->from('users', 'u')
            ->orderBy(['u.name', 'u.email'], 'DESC')
            ->getSQL();

        $expected = 'SELECT * FROM "users" "u" ORDER BY u.name DESC, u.email DESC';
        $this->assertEquals($expected, $sql);
    }

    public function testAddSelect(): void
    {
        $sql = $this->queryBuilder
            ->select('id')
            ->addSelect('name')
            ->addSelect(['email', 'created_at'])
            ->from('users', 'u')
            ->getSQL();

        $expected = 'SELECT id, name, email, created_at FROM "users" "u"';
        $this->assertEquals($expected, $sql);
    }

    public function testRightJoin(): void
    {
        $sql = $this->queryBuilder
            ->select('*')
            ->from('users', 'u')
            ->rightJoin('profiles', 'p', 'u.id = p.user_id')
            ->getSQL();

        $expected = 'SELECT * FROM "users" "u" RIGHT JOIN "profiles" "p" ON u.id = p.user_id';
        $this->assertEquals($expected, $sql);
    }

    public function testAddGroupBy(): void
    {
        $sql = $this->queryBuilder
            ->select(['department', 'role', 'COUNT(*)'])
            ->from('users', 'u')
            ->groupBy('department')
            ->addGroupBy('role')
            ->getSQL();

        $expected = 'SELECT department, role, COUNT(*) FROM "users" "u" GROUP BY department, role';
        $this->assertEquals($expected, $sql);
    }

    public function testAndHaving(): void
    {
        $sql = $this->queryBuilder
            ->select(['department', 'COUNT(*) as count'])
            ->from('users', 'u')
            ->groupBy('department')
            ->having('COUNT(*) > 5')
            ->andHaving('department != "IT"')
            ->getSQL();

        $expected = 'SELECT department, COUNT(*) as count FROM "users" "u" GROUP BY department HAVING COUNT(*) > 5 AND department != "IT"';
        $this->assertEquals($expected, $sql);
    }

    public function testSetParameters(): void
    {
        $this->queryBuilder
            ->select('*')
            ->from('users', 'u')
            ->where('u.age > :age AND u.active = :active')
            ->setParameters(['age' => 18, 'active' => true]);

        $parameters = $this->queryBuilder->getParameters();
        $this->assertEquals(['age' => 18, 'active' => true], $parameters);
    }

    public function testSetParametersOverwrite(): void
    {
        $this->queryBuilder
            ->setParameter('test', 'old')
            ->setParameters(['test' => 'new', 'other' => 'value']);

        $parameters = $this->queryBuilder->getParameters();
        $this->assertEquals(['test' => 'new', 'other' => 'value'], $parameters);
    }

    public function testEmptySelectClause(): void
    {
        $sql = $this->queryBuilder
            ->from('users', 'u')
            ->getSQL();

        $expected = 'SELECT * FROM "users" "u"';
        $this->assertEquals($expected, $sql);
    }

    public function testFromWithoutAlias(): void
    {
        // Test the case where fromAlias is null
        $reflection = new \ReflectionClass($this->queryBuilder);
        $fromAliasProperty = $reflection->getProperty('fromAlias');
        $fromAliasProperty->setAccessible(true);

        $this->queryBuilder->from('users', 'u');
        $fromAliasProperty->setValue($this->queryBuilder, null);

        $sql = $this->queryBuilder->select('*')->getSQL();
        $expected = 'SELECT * FROM "users"';
        $this->assertEquals($expected, $sql);
    }

    public function testLimitWithoutOffset(): void
    {
        $sql = $this->queryBuilder
            ->select('*')
            ->from('users', 'u')
            ->setMaxResults(10)
            ->getSQL();

        $expected = 'SELECT * FROM "users" "u" LIMIT 10';
        $this->assertEquals($expected, $sql);
    }

    public function testOrWhereWithEmptyWhere(): void
    {
        $sql = $this->queryBuilder
            ->select('*')
            ->from('users', 'u')
            ->orWhere('u.active = true')
            ->getSQL();

        $expected = 'SELECT * FROM "users" "u" WHERE u.active = true';
        $this->assertEquals($expected, $sql);
    }

    public function testOrHaving(): void
    {
        $sql = $this->queryBuilder
            ->select('*')
            ->from('users', 'u')
            ->groupBy('u.department')
            ->having('COUNT(*) > 5')
            ->orHaving('AVG(u.salary) > 50000')
            ->getSQL();

        $this->assertStringContainsString('GROUP BY', $sql);
        $this->assertStringContainsString('HAVING', $sql);
        $this->assertStringContainsString('OR', $sql);
        $this->assertStringContainsString('COUNT(*) > 5', $sql);
        $this->assertStringContainsString('AVG(u.salary) > 50000', $sql);
    }

    public function testSelectWithMultipleArguments(): void
    {
        $sql = $this->queryBuilder
            ->select('id', 'name', 'email')
            ->from('users', 'u')
            ->getSQL();

        $this->assertEquals(
            'SELECT id, name, email FROM "users" "u"',
            $sql
        );
    }

    public function testSelectWithArrayAndMultipleArguments(): void
    {
        $sql = $this->queryBuilder
            ->select(['id', 'name'], 'email', 'COUNT(*)')
            ->from('users', 'u')
            ->getSQL();

        $this->assertEquals(
            'SELECT id, name, email, COUNT(*) FROM "users" "u"',
            $sql
        );
    }
}
