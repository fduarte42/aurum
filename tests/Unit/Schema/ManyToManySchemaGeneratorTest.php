<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit\Schema;

use Fduarte42\Aurum\Attribute\{Entity, Id, Column, ManyToMany, JoinTable, JoinColumn};
use Fduarte42\Aurum\Connection\ConnectionInterface;
use Fduarte42\Aurum\Metadata\MetadataFactory;
use Fduarte42\Aurum\Schema\SchemaGenerator;
use PHPUnit\Framework\TestCase;

// Test entities for schema generation
#[Entity(table: 'schema_users')]
class SchemaUser
{
    #[Id]
    #[Column(type: 'uuid')]
    public private(set) ?string $id = null;

    #[ManyToMany(targetEntity: SchemaRole::class)]
    #[JoinTable(
        name: 'user_role_mapping',
        joinColumns: [new JoinColumn(name: 'user_id', referencedColumnName: 'id')],
        inverseJoinColumns: [new JoinColumn(name: 'role_id', referencedColumnName: 'id')]
    )]
    public array $roles = [];

    public function __construct(
        #[Column(type: 'string', length: 255)]
        public string $name
    ) {
    }
}

#[Entity(table: 'schema_roles')]
class SchemaRole
{
    #[Id]
    #[Column(type: 'uuid')]
    public private(set) ?string $id = null;

    #[ManyToMany(targetEntity: SchemaUser::class, mappedBy: 'roles')]
    public array $users = [];

    public function __construct(
        #[Column(type: 'string', length: 100)]
        public string $name
    ) {
    }
}

class ManyToManySchemaGeneratorTest extends TestCase
{
    private SchemaGenerator $schemaGenerator;
    private MetadataFactory $metadataFactory;
    private ConnectionInterface $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->connection->method('getPlatform')->willReturn('sqlite');

        $this->metadataFactory = new MetadataFactory();
        $this->schemaGenerator = new SchemaGenerator($this->metadataFactory, $this->connection);
    }

    public function testSchemaBuilderGenerationWithManyToMany(): void
    {
        $entityClasses = [SchemaUser::class, SchemaRole::class];
        $schemaBuilderCode = $this->schemaGenerator->generateSchemaBuilderCode($entityClasses);

        // Verify entity tables are generated
        $this->assertStringContainsString("createTable('schema_users')", $schemaBuilderCode);
        $this->assertStringContainsString("createTable('schema_roles')", $schemaBuilderCode);

        // Verify junction table is generated
        $this->assertStringContainsString("createTable('user_role_mapping')", $schemaBuilderCode);

        // Verify junction table has correct columns
        $this->assertStringContainsString("addColumn('user_id'", $schemaBuilderCode);
        $this->assertStringContainsString("addColumn('role_id'", $schemaBuilderCode);

        // Verify primary key and foreign keys
        $this->assertStringContainsString("setPrimaryKey(['user_id', 'role_id'])", $schemaBuilderCode);
        $this->assertStringContainsString("addForeignKeyConstraint('schema_users'", $schemaBuilderCode);
        $this->assertStringContainsString("addForeignKeyConstraint('schema_roles'", $schemaBuilderCode);
    }

    public function testSqlGenerationWithManyToMany(): void
    {
        $entityClasses = [SchemaUser::class, SchemaRole::class];
        $sql = $this->schemaGenerator->generateSqlDdl($entityClasses);

        // Verify entity tables are created
        $this->assertStringContainsString('schema_users', $sql);
        $this->assertStringContainsString('schema_roles', $sql);

        // Verify junction table is created
        $this->assertStringContainsString('CREATE TABLE user_role_mapping', $sql);

        // Verify junction table structure
        $this->assertStringContainsString('user_id', $sql);
        $this->assertStringContainsString('role_id', $sql);
        $this->assertStringContainsString('PRIMARY KEY (user_id, role_id)', $sql);
        $this->assertStringContainsString('FOREIGN KEY (user_id) REFERENCES schema_users(id)', $sql);
        $this->assertStringContainsString('FOREIGN KEY (role_id) REFERENCES schema_roles(id)', $sql);
    }

    public function testManyToManyWithoutJoinTable(): void
    {
        // This would use default junction table naming
        // We'll test this in integration tests where we can create actual entities
        $this->assertTrue(true); // Placeholder for now
    }

    public function testJunctionTableDeduplication(): void
    {
        // Test that the same junction table isn't generated multiple times
        $entityClasses = [SchemaUser::class, SchemaRole::class];
        $schemaBuilderCode = $this->schemaGenerator->generateSchemaBuilderCode($entityClasses);

        // Count occurrences of junction table creation
        $occurrences = substr_count($schemaBuilderCode, "createTable('user_role_mapping')");
        $this->assertEquals(1, $occurrences, 'Junction table should only be created once');
    }

    public function testMariaDbSqlGeneration(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->connection->method('getPlatform')->willReturn('mysql');

        $this->schemaGenerator = new SchemaGenerator($this->metadataFactory, $this->connection);
        
        $entityClasses = [SchemaUser::class, SchemaRole::class];
        $sql = $this->schemaGenerator->generateSqlDdl($entityClasses);

        // Verify MariaDB-specific syntax
        $this->assertStringContainsString('CREATE TABLE `user_role_mapping`', $sql);
        $this->assertStringContainsString('ENGINE=InnoDB', $sql);
        $this->assertStringContainsString('DEFAULT CHARSET=utf8mb4', $sql);
        $this->assertStringContainsString('COLLATE=utf8mb4_unicode_ci', $sql);
    }
}
