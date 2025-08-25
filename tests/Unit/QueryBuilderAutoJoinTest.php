<?php

declare(strict_types=1);

namespace Tests\Unit;

use Fduarte42\Aurum\Attribute\{Entity, Id, Column, ManyToOne, OneToMany};
use Fduarte42\Aurum\Connection\ConnectionInterface;
use Fduarte42\Aurum\Exception\ORMException;
use Fduarte42\Aurum\Metadata\MetadataFactory;
use Fduarte42\Aurum\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\UuidInterface;

// Test entities for auto-join functionality
#[Entity(table: 'users')]
class TestUser
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?UuidInterface $id = null;

    #[Column(type: 'string')]
    private string $name;

    #[OneToMany(targetEntity: TestPost::class, mappedBy: 'user')]
    private array $posts = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getId(): ?UuidInterface { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getPosts(): array { return $this->posts; }
}

#[Entity(table: 'posts')]
class TestPost
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?UuidInterface $id = null;

    #[Column(type: 'string')]
    private string $title;

    #[ManyToOne(targetEntity: TestUser::class, inversedBy: 'posts')]
    private ?TestUser $user = null;

    #[ManyToOne(targetEntity: TestCategory::class)]
    private ?TestCategory $category = null;

    public function __construct(string $title)
    {
        $this->title = $title;
    }

    public function getId(): ?UuidInterface { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function getUser(): ?TestUser { return $this->user; }
    public function getCategory(): ?TestCategory { return $this->category; }
    public function setUser(?TestUser $user): void { $this->user = $user; }
    public function setCategory(?TestCategory $category): void { $this->category = $category; }
}

#[Entity(table: 'categories')]
class TestCategory
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?UuidInterface $id = null;

    #[Column(type: 'string')]
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getId(): ?UuidInterface { return $this->id; }
    public function getName(): string { return $this->name; }
}

class QueryBuilderAutoJoinTest extends TestCase
{
    private ConnectionInterface $connection;
    private MetadataFactory $metadataFactory;
    private QueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);

        // Mock the quoteIdentifier method to return quoted identifiers
        $this->connection->method('quoteIdentifier')
            ->willReturnCallback(function (string $identifier) {
                return '"' . $identifier . '"';
            });

        $this->metadataFactory = new MetadataFactory();
        $this->queryBuilder = new QueryBuilder($this->connection, $this->metadataFactory);
    }

    public function testAutoJoinManyToOneRelationship(): void
    {
        // Set up query builder for Post entity
        $this->queryBuilder
            ->setRootEntityClass(TestPost::class)
            ->select('p.*')
            ->from('posts', 'p')
            ->innerJoin('user', 'u'); // Should auto-resolve to p.user_id = u.id

        $sql = $this->queryBuilder->getSQL();

        $this->assertStringContainsString('INNER JOIN "users" "u" ON p.user_id = u.id', $sql);
    }

    public function testAutoJoinOneToManyRelationship(): void
    {
        // Set up query builder for User entity
        $this->queryBuilder
            ->setRootEntityClass(TestUser::class)
            ->select('u.*')
            ->from('users', 'u')
            ->leftJoin('posts', 'p'); // Should auto-resolve to u.id = p.user_id

        $sql = $this->queryBuilder->getSQL();
        
        $this->assertStringContainsString('LEFT JOIN "posts" "p" ON u.id = p.user_id', $sql);
    }

    public function testAutoJoinWithMultipleRelationships(): void
    {
        // Test joining multiple relationships
        $this->queryBuilder
            ->setRootEntityClass(TestPost::class)
            ->select('p.*, u.name, c.name as category_name')
            ->from('posts', 'p')
            ->innerJoin('user', 'u')
            ->leftJoin('category', 'c');

        $sql = $this->queryBuilder->getSQL();
        
        $this->assertStringContainsString('INNER JOIN "users" "u" ON p.user_id = u.id', $sql);
        $this->assertStringContainsString('LEFT JOIN "categories" "c" ON p.category_id = c.id', $sql);
    }

    public function testExplicitJoinConditionStillWorks(): void
    {
        // Explicit condition should override auto-resolution
        $this->queryBuilder
            ->setRootEntityClass(TestPost::class)
            ->select('p.*')
            ->from('posts', 'p')
            ->innerJoin('users', 'u', 'p.custom_user_id = u.id');

        $sql = $this->queryBuilder->getSQL();
        
        $this->assertStringContainsString('INNER JOIN "users" "u" ON p.custom_user_id = u.id', $sql);
    }

    public function testAutoJoinWithoutMetadataFactoryThrowsException(): void
    {
        $queryBuilderWithoutMetadata = new QueryBuilder($this->connection);
        
        $this->expectException(ORMException::class);
        $this->expectExceptionMessage('Cannot resolve join condition: MetadataFactory or root entity class not set');
        
        $queryBuilderWithoutMetadata
            ->select('p.*')
            ->from('posts', 'p')
            ->innerJoin('user', 'u');
    }

    public function testAutoJoinWithInvalidPropertyThrowsException(): void
    {
        $this->expectException(ORMException::class);
        $this->expectExceptionMessage("Cannot resolve join condition for property 'nonexistent'");
        
        $this->queryBuilder
            ->setRootEntityClass(TestPost::class)
            ->select('p.*')
            ->from('posts', 'p')
            ->innerJoin('nonexistent', 'n');
    }

    public function testAutoJoinWithEntityClassAsJoinTarget(): void
    {
        // When using entity class name, explicit condition is required since
        // there could be multiple relationships to the same entity
        $this->expectException(ORMException::class);
        $this->expectExceptionMessage("Cannot resolve join condition for property 'Tests\Unit\TestUser'");

        $this->queryBuilder
            ->setRootEntityClass(TestPost::class)
            ->select('p.*')
            ->from('posts', 'p')
            ->innerJoin(TestUser::class, 'u'); // Using class name without explicit condition
    }

    public function testRightJoinAutoResolution(): void
    {
        $this->queryBuilder
            ->setRootEntityClass(TestPost::class)
            ->select('p.*')
            ->from('posts', 'p')
            ->rightJoin('user', 'u');

        $sql = $this->queryBuilder->getSQL();
        
        $this->assertStringContainsString('RIGHT JOIN "users" "u" ON p.user_id = u.id', $sql);
    }
}
