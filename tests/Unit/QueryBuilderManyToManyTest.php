<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit;

use Fduarte42\Aurum\Attribute\{Entity, Id, Column, ManyToMany, JoinTable, JoinColumn};
use Fduarte42\Aurum\Connection\ConnectionInterface;
use Fduarte42\Aurum\Exception\ORMException;
use Fduarte42\Aurum\Metadata\MetadataFactory;
use Fduarte42\Aurum\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;

// Test entities for Many-to-Many QueryBuilder functionality
#[Entity(table: 'qb_users')]
class QBUser
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?string $id = null;

    #[Column(type: 'string', length: 255)]
    private string $name;

    #[ManyToMany(targetEntity: QBRole::class)]
    #[JoinTable(
        name: 'qb_user_roles',
        joinColumns: [new JoinColumn(name: 'user_id', referencedColumnName: 'id')],
        inverseJoinColumns: [new JoinColumn(name: 'role_id', referencedColumnName: 'id')]
    )]
    private array $roles = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getId(): ?string { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getRoles(): array { return $this->roles; }
}

#[Entity(table: 'qb_roles')]
class QBRole
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?string $id = null;

    #[Column(type: 'string', length: 100)]
    private string $name;

    #[ManyToMany(targetEntity: QBUser::class, mappedBy: 'roles')]
    private array $users = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getId(): ?string { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getUsers(): array { return $this->users; }
}

class QueryBuilderManyToManyTest extends TestCase
{
    private QueryBuilder $queryBuilder;
    private MetadataFactory $metadataFactory;
    private ConnectionInterface $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);

        // Configure the mock to return the identifier as-is for quoteIdentifier
        $this->connection->method('quoteIdentifier')
            ->willReturnCallback(function($identifier) {
                return $identifier;
            });

        $this->metadataFactory = new MetadataFactory();
        $this->queryBuilder = new QueryBuilder($this->connection, $this->metadataFactory);
    }

    public function testManyToManyJoinConditionResolution(): void
    {
        // Test if QueryBuilder can resolve Many-to-Many join conditions
        $qb = $this->queryBuilder
            ->select('u', 'r')
            ->from(QBUser::class, 'u')
            ->innerJoin('u.roles', 'r'); // This should now work!

        // Verify that the QueryBuilder was created successfully
        $this->assertInstanceOf(QueryBuilder::class, $qb);

        // Check that joins were added (should have 2 joins: junction table + target table)
        $reflection = new \ReflectionClass($qb);
        $joinsProperty = $reflection->getProperty('joins');
        $joinsProperty->setAccessible(true);
        $joins = $joinsProperty->getValue($qb);

        // Should have 2 joins: one to junction table, one to target table
        $this->assertCount(2, $joins);

        // First join should be to junction table
        $this->assertEquals('INNER', $joins[0]['type']);
        $this->assertEquals('qb_user_roles', $joins[0]['table']);
        $this->assertStringContainsString('u.id = ', $joins[0]['condition']);

        // Second join should be to target table
        $this->assertEquals('INNER', $joins[1]['type']);
        $this->assertEquals('qb_roles', $joins[1]['table']);
        $this->assertEquals('r', $joins[1]['alias']);
    }

    public function testManyToManyTableNameResolution(): void
    {
        // Test if QueryBuilder can resolve table names for Many-to-Many relationships
        $qb = $this->queryBuilder
            ->select('u')
            ->from(QBUser::class, 'u');

        // This should work because resolveTableName handles associations
        $reflection = new \ReflectionClass($qb);
        $method = $reflection->getMethod('resolveTableName');
        $method->setAccessible(true);

        $tableName = $method->invoke($qb, 'roles');
        $this->assertEquals('qb_roles', $tableName);
    }

    public function testManyToManyMetadataIntegration(): void
    {
        // Test if Many-to-Many associations are properly recognized
        $metadata = $this->metadataFactory->getMetadataFor(QBUser::class);
        $associations = $metadata->getAssociationMappings();

        $this->assertArrayHasKey('roles', $associations);
        
        $rolesAssociation = $associations['roles'];
        $this->assertEquals('ManyToMany', $rolesAssociation->getType());
        $this->assertEquals(QBRole::class, $rolesAssociation->getTargetEntity());
        $this->assertTrue($rolesAssociation->isOwningSide());
        $this->assertNotNull($rolesAssociation->getJoinTable());
    }

    public function testInverseSideManyToManyMetadata(): void
    {
        // Test inverse side Many-to-Many metadata
        $metadata = $this->metadataFactory->getMetadataFor(QBRole::class);
        $associations = $metadata->getAssociationMappings();

        $this->assertArrayHasKey('users', $associations);
        
        $usersAssociation = $associations['users'];
        $this->assertEquals('ManyToMany', $usersAssociation->getType());
        $this->assertEquals(QBUser::class, $usersAssociation->getTargetEntity());
        $this->assertFalse($usersAssociation->isOwningSide());
        $this->assertEquals('roles', $usersAssociation->getMappedBy());
    }

    public function testManyToManyJoinTableConfiguration(): void
    {
        // Test if JoinTable configuration is properly parsed
        $metadata = $this->metadataFactory->getMetadataFor(QBUser::class);
        $associations = $metadata->getAssociationMappings();
        $rolesAssociation = $associations['roles'];
        
        $joinTable = $rolesAssociation->getJoinTable();
        $this->assertNotNull($joinTable);
        $this->assertEquals('qb_user_roles', $joinTable->getName());
        
        $joinColumns = $joinTable->getJoinColumns();
        $this->assertCount(1, $joinColumns);
        $this->assertEquals('user_id', $joinColumns[0]->getName());
        $this->assertEquals('id', $joinColumns[0]->getReferencedColumnName());
        
        $inverseJoinColumns = $joinTable->getInverseJoinColumns();
        $this->assertCount(1, $inverseJoinColumns);
        $this->assertEquals('role_id', $inverseJoinColumns[0]->getName());
        $this->assertEquals('id', $inverseJoinColumns[0]->getReferencedColumnName());
    }

    public function testQueryBuilderManyToManyJoinWorking(): void
    {
        // Test that Many-to-Many joins are now working
        $qb = $this->queryBuilder
            ->select('u', 'r')
            ->from(QBUser::class, 'u')
            ->innerJoin('u.roles', 'r');

        $sql = $qb->getSQL();

        // Should generate something like:
        // SELECT u.*, r.* FROM qb_users u
        // INNER JOIN qb_user_roles ur ON u.id = ur.user_id
        // INNER JOIN qb_roles r ON ur.role_id = r.id

        $this->assertStringContainsString('qb_user_roles', $sql);
        $this->assertStringContainsString('qb_users', $sql);
        $this->assertStringContainsString('qb_roles', $sql);
        $this->assertStringContainsString('INNER JOIN', $sql);
    }

    public function testQueryBuilderInverseSideManyToManyJoinWorking(): void
    {
        // Test that inverse side Many-to-Many joins work
        $qb = $this->queryBuilder
            ->select('r', 'u')
            ->from(QBRole::class, 'r')
            ->innerJoin('r.users', 'u');

        $sql = $qb->getSQL();

        // Should generate the same junction table join but from the other direction
        $this->assertStringContainsString('qb_user_roles', $sql);
        $this->assertStringContainsString('qb_users', $sql);
        $this->assertStringContainsString('qb_roles', $sql);
        $this->assertStringContainsString('INNER JOIN', $sql);
    }
}
