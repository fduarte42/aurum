<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit\Schema;

use Fduarte42\Aurum\Schema\SchemaDiffer;
use Fduarte42\Aurum\Schema\SchemaIntrospector;
use Fduarte42\Aurum\Metadata\MetadataFactory;
use Fduarte42\Aurum\Metadata\EntityMetadataInterface;
use Fduarte42\Aurum\Metadata\FieldMappingInterface;
use Fduarte42\Aurum\Connection\ConnectionInterface;
use Fduarte42\Aurum\Connection\ConnectionFactory;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class SchemaDifferTest extends TestCase
{
    private SchemaDiffer $schemaDiffer;
    private MetadataFactory|MockObject $metadataFactory;
    private SchemaIntrospector|MockObject $introspector;
    private ConnectionInterface $connection;

    protected function setUp(): void
    {
        $this->metadataFactory = $this->createMock(MetadataFactory::class);
        $this->introspector = $this->createMock(SchemaIntrospector::class);
        $this->connection = ConnectionFactory::createSqliteConnection(':memory:');

        $this->schemaDiffer = new SchemaDiffer(
            $this->metadataFactory,
            $this->introspector,
            $this->connection
        );
    }

    public function testGenerateMigrationDiffForNewTable(): void
    {
        // Mock current schema (empty)
        $this->introspector
            ->expects($this->once())
            ->method('getTables')
            ->willReturn([]);

        // Mock target schema (new users table)
        $metadata = $this->createMockMetadata('users', [
            'id' => [
                'column_name' => 'id',
                'type' => 'uuid',
                'id' => true,
                'nullable' => false
            ],
            'email' => [
                'column_name' => 'email',
                'type' => 'string',
                'length' => 255,
                'nullable' => false,
                'unique' => true
            ]
        ]);

        $this->metadataFactory
            ->expects($this->once())
            ->method('getMetadataFor')
            ->with('User')
            ->willReturn($metadata);

        $diff = $this->schemaDiffer->generateMigrationDiff(['User']);

        // Check up migration (should create table)
        $this->assertStringContainsString("->createTable('users')", $diff['up']);
        $this->assertStringContainsString("->uuidPrimaryKey('id')", $diff['up']);
        $this->assertStringContainsString("->string('email'", $diff['up']);
        $this->assertStringContainsString("->unique(['email']", $diff['up']);
        $this->assertStringContainsString('->create();', $diff['up']);

        // Check down migration (should drop table)
        $this->assertStringContainsString("->dropTable('users')", $diff['down']);
    }

    public function testGenerateMigrationDiffForDroppedTable(): void
    {
        // Mock current schema (has users table)
        $this->introspector
            ->expects($this->once())
            ->method('getTables')
            ->willReturn(['users']);

        $this->introspector
            ->expects($this->once())
            ->method('getTableStructure')
            ->with('users')
            ->willReturn([
                'name' => 'users',
                'columns' => [
                    [
                        'name' => 'id',
                        'type' => 'integer',
                        'primary_key' => true,
                        'nullable' => false,
                        'auto_increment' => true
                    ]
                ],
                'indexes' => [],
                'foreign_keys' => []
            ]);

        // Mock target schema (empty)
        // No entities provided, so no metadata calls expected

        $diff = $this->schemaDiffer->generateMigrationDiff([]);

        // Check up migration (should drop table)
        $this->assertStringContainsString("->dropTable('users')", $diff['up']);

        // Check down migration (should recreate table)
        $this->assertStringContainsString("->createTable('users')", $diff['down']);
        $this->assertStringContainsString("->id('id')", $diff['down']);
    }

    public function testGenerateMigrationDiffForAddedColumn(): void
    {
        // Mock current schema (users table without bio column)
        $this->introspector
            ->expects($this->once())
            ->method('getTables')
            ->willReturn(['users']);

        $this->introspector
            ->expects($this->once())
            ->method('getTableStructure')
            ->with('users')
            ->willReturn([
                'name' => 'users',
                'columns' => [
                    [
                        'name' => 'id',
                        'type' => 'uuid',
                        'primary_key' => true,
                        'nullable' => false,
                        'auto_increment' => false
                    ],
                    [
                        'name' => 'email',
                        'type' => 'string',
                        'nullable' => false,
                        'length' => 255
                    ]
                ],
                'indexes' => [],
                'foreign_keys' => []
            ]);

        // Mock target schema (users table with bio column)
        $metadata = $this->createMockMetadata('users', [
            'id' => [
                'column_name' => 'id',
                'type' => 'uuid',
                'id' => true,
                'nullable' => false
            ],
            'email' => [
                'column_name' => 'email',
                'type' => 'string',
                'length' => 255,
                'nullable' => false
            ],
            'bio' => [
                'column_name' => 'bio',
                'type' => 'text',
                'nullable' => true
            ]
        ]);

        $this->metadataFactory
            ->expects($this->once())
            ->method('getMetadataFor')
            ->with('User')
            ->willReturn($metadata);

        $diff = $this->schemaDiffer->generateMigrationDiff(['User']);

        // Check up migration (should add bio column)
        $this->assertStringContainsString("->alterTable('users')", $diff['up']);
        $this->assertStringContainsString("->text('bio', ['nullable' => true])", $diff['up']);
        $this->assertStringContainsString('->alter();', $diff['up']);

        // Check down migration (should drop bio column)
        $this->assertStringContainsString("->alterTable('users')", $diff['down']);
        $this->assertStringContainsString("->dropColumn('bio')", $diff['down']);
    }

    public function testGenerateMigrationDiffForModifiedColumn(): void
    {
        // Mock current schema (email column with length 100)
        $this->introspector
            ->expects($this->once())
            ->method('getTables')
            ->willReturn(['users']);

        $this->introspector
            ->expects($this->once())
            ->method('getTableStructure')
            ->with('users')
            ->willReturn([
                'name' => 'users',
                'columns' => [
                    [
                        'name' => 'id',
                        'type' => 'uuid',
                        'primary_key' => true,
                        'nullable' => false
                    ],
                    [
                        'name' => 'email',
                        'type' => 'string',
                        'nullable' => false,
                        'length' => 100
                    ]
                ],
                'indexes' => [],
                'foreign_keys' => []
            ]);

        // Mock target schema (email column with length 255)
        $metadata = $this->createMockMetadata('users', [
            'id' => [
                'column_name' => 'id',
                'type' => 'uuid',
                'id' => true,
                'nullable' => false
            ],
            'email' => [
                'column_name' => 'email',
                'type' => 'string',
                'length' => 255,
                'nullable' => false
            ]
        ]);

        $this->metadataFactory
            ->expects($this->once())
            ->method('getMetadataFor')
            ->with('User')
            ->willReturn($metadata);

        $diff = $this->schemaDiffer->generateMigrationDiff(['User']);

        // Check up migration (should change email column)
        $this->assertStringContainsString("->alterTable('users')", $diff['up']);
        $this->assertStringContainsString("->changeColumn('email', 'string', ['length' => 255, 'not_null' => true])", $diff['up']);

        // Check down migration (should revert email column)
        $this->assertStringContainsString("->changeColumn('email', 'string', ['length' => 100, 'not_null' => true])", $diff['down']);
    }

    public function testGenerateMigrationDiffForAddedIndex(): void
    {
        // Mock current schema (no indexes)
        $this->introspector
            ->expects($this->once())
            ->method('getTables')
            ->willReturn(['users']);

        $this->introspector
            ->expects($this->once())
            ->method('getTableStructure')
            ->with('users')
            ->willReturn([
                'name' => 'users',
                'columns' => [
                    [
                        'name' => 'id',
                        'type' => 'uuid',
                        'primary_key' => true,
                        'nullable' => false
                    ],
                    [
                        'name' => 'email',
                        'type' => 'string',
                        'nullable' => false,
                        'unique' => true
                    ]
                ],
                'indexes' => [],
                'foreign_keys' => []
            ]);

        // Mock target schema (with unique index on email)
        $metadata = $this->createMockMetadata('users', [
            'id' => [
                'column_name' => 'id',
                'type' => 'uuid',
                'id' => true,
                'nullable' => false
            ],
            'email' => [
                'column_name' => 'email',
                'type' => 'string',
                'nullable' => false,
                'unique' => true
            ]
        ]);

        $this->metadataFactory
            ->expects($this->once())
            ->method('getMetadataFor')
            ->with('User')
            ->willReturn($metadata);

        $diff = $this->schemaDiffer->generateMigrationDiff(['User']);

        // Check up migration (should add unique index)
        $this->assertStringContainsString("->alterTable('users')", $diff['up']);
        $this->assertStringContainsString("->unique(['email']", $diff['up']);

        // Check down migration (should drop index)
        $this->assertStringContainsString("->dropIndex('idx_users_email_unique')", $diff['down']);
    }

    public function testGenerateMigrationDiffNoChanges(): void
    {
        // Mock current schema
        $this->introspector
            ->expects($this->once())
            ->method('getTables')
            ->willReturn(['users']);

        $this->introspector
            ->expects($this->once())
            ->method('getTableStructure')
            ->with('users')
            ->willReturn([
                'name' => 'users',
                'columns' => [
                    [
                        'name' => 'id',
                        'type' => 'uuid',
                        'primary_key' => true,
                        'nullable' => false
                    ],
                    [
                        'name' => 'email',
                        'type' => 'string',
                        'nullable' => false,
                        'length' => 255
                    ]
                ],
                'indexes' => [],
                'foreign_keys' => []
            ]);

        // Mock target schema (identical)
        $metadata = $this->createMockMetadata('users', [
            'id' => [
                'column_name' => 'id',
                'type' => 'uuid',
                'id' => true,
                'nullable' => false
            ],
            'email' => [
                'column_name' => 'email',
                'type' => 'string',
                'length' => 255,
                'nullable' => false
            ]
        ]);

        $this->metadataFactory
            ->expects($this->once())
            ->method('getMetadataFor')
            ->with('User')
            ->willReturn($metadata);

        $diff = $this->schemaDiffer->generateMigrationDiff(['User']);

        // Should have minimal or no changes
        $this->assertStringNotContainsString('->createTable', $diff['up']);
        $this->assertStringNotContainsString('->dropTable', $diff['up']);
        $this->assertStringNotContainsString('->alterTable', $diff['up']);
    }

    /**
     * Create a mock metadata object
     */
    private function createMockMetadata(string $tableName, array $fieldMappings): EntityMetadataInterface
    {
        $metadata = $this->createMock(EntityMetadataInterface::class);

        // Convert array field mappings to mock FieldMapping objects
        $mockFieldMappings = [];
        foreach ($fieldMappings as $fieldName => $mappingData) {
            $mockFieldMappings[$fieldName] = $this->createMockFieldMapping($mappingData);
        }

        $metadata->method('getTableName')->willReturn($tableName);
        $metadata->method('getFieldMappings')->willReturn($mockFieldMappings);
        $metadata->method('getAssociationMappings')->willReturn([]);

        return $metadata;
    }

    /**
     * Create a mock field mapping object
     */
    private function createMockFieldMapping(array $mappingData): FieldMappingInterface
    {
        $fieldMapping = $this->createMock(FieldMappingInterface::class);

        $fieldMapping->method('getColumnName')->willReturn($mappingData['column_name'] ?? 'test_column');
        $fieldMapping->method('getType')->willReturn($mappingData['type'] ?? 'string');
        $fieldMapping->method('isNullable')->willReturn($mappingData['nullable'] ?? false);
        $fieldMapping->method('getDefault')->willReturn($mappingData['default'] ?? null);
        $fieldMapping->method('isIdentifier')->willReturn($mappingData['id'] ?? false);
        $fieldMapping->method('isUnique')->willReturn($mappingData['unique'] ?? false);
        $fieldMapping->method('getLength')->willReturn($mappingData['length'] ?? null);
        $fieldMapping->method('getPrecision')->willReturn($mappingData['precision'] ?? null);
        $fieldMapping->method('getScale')->willReturn($mappingData['scale'] ?? null);

        return $fieldMapping;
    }
}
