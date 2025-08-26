<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit\Schema;

use Fduarte42\Aurum\Schema\SchemaGenerator;
use Fduarte42\Aurum\Metadata\MetadataFactory;
use Fduarte42\Aurum\Metadata\EntityMetadataInterface;
use Fduarte42\Aurum\Metadata\FieldMappingInterface;
use Fduarte42\Aurum\Connection\ConnectionInterface;
use Fduarte42\Aurum\Connection\ConnectionFactory;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class SchemaGeneratorTest extends TestCase
{
    private SchemaGenerator $schemaGenerator;
    private MetadataFactory|MockObject $metadataFactory;
    private ConnectionInterface $connection;

    protected function setUp(): void
    {
        $this->metadataFactory = $this->createMock(MetadataFactory::class);
        $this->connection = ConnectionFactory::createSqliteConnection(':memory:');
        $this->schemaGenerator = new SchemaGenerator($this->metadataFactory, $this->connection);
    }

    public function testGenerateSchemaBuilderCodeForSingleEntity(): void
    {
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
            ],
            'name' => [
                'column_name' => 'name',
                'type' => 'string',
                'length' => 255,
                'nullable' => false
            ],
            'bio' => [
                'column_name' => 'bio',
                'type' => 'text',
                'nullable' => true
            ],
            'active' => [
                'column_name' => 'active',
                'type' => 'boolean',
                'nullable' => false,
                'default' => true
            ],
            'created_at' => [
                'column_name' => 'created_at',
                'type' => 'datetime',
                'nullable' => false
            ]
        ]);

        $this->metadataFactory
            ->expects($this->atLeastOnce())
            ->method('getMetadataFor')
            ->with('User')
            ->willReturn($metadata);

        $code = $this->schemaGenerator->generateSchemaBuilderCode(['User']);

        $this->assertStringContainsString('function createSchema(SchemaBuilderInterface $schemaBuilder): void', $code);
        $this->assertStringContainsString("->createTable('users')", $code);
        $this->assertStringContainsString("->uuidPrimaryKey('id')", $code);
        $this->assertStringContainsString("->string('email', ['length' => 255, 'not_null' => true])", $code);
        $this->assertStringContainsString("->string('name', ['length' => 255, 'not_null' => true])", $code);
        $this->assertStringContainsString("->text('bio', ['nullable' => true])", $code);
        $this->assertStringContainsString("->boolean('active', ['not_null' => true, 'default' => true])", $code);
        $this->assertStringContainsString("->datetime('created_at', ['not_null' => true])", $code);
        $this->assertStringContainsString("->unique(['email']", $code);
        $this->assertStringContainsString('->create();', $code);
    }

    public function testGenerateSchemaBuilderCodeForMultipleEntities(): void
    {
        $userMetadata = $this->createMockMetadata('users', [
            'id' => ['column_name' => 'id', 'type' => 'uuid', 'id' => true, 'nullable' => false]
        ]);

        $postMetadata = $this->createMockMetadata('posts', [
            'id' => ['column_name' => 'id', 'type' => 'uuid', 'id' => true, 'nullable' => false],
            'title' => ['column_name' => 'title', 'type' => 'string', 'length' => 255, 'nullable' => false]
        ]);

        $this->metadataFactory
            ->expects($this->atLeastOnce())
            ->method('getMetadataFor')
            ->willReturnMap([
                ['User', $userMetadata],
                ['Post', $postMetadata]
            ]);

        $code = $this->schemaGenerator->generateSchemaBuilderCode(['User', 'Post']);

        $this->assertStringContainsString("->createTable('users')", $code);
        $this->assertStringContainsString("->createTable('posts')", $code);
    }

    public function testGenerateSqlDdlForSqlite(): void
    {
        $metadata = $this->createMockMetadata('users', [
            'id' => [
                'column_name' => 'id',
                'type' => 'integer',
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
            ->expects($this->atLeastOnce())
            ->method('getMetadataFor')
            ->with('User')
            ->willReturn($metadata);

        $sql = $this->schemaGenerator->generateSqlDdl(['User']);

        $this->assertStringContainsString('-- Generated SQL DDL for sqlite', $sql);
        $this->assertStringContainsString('CREATE TABLE "users"', $sql);
        $this->assertStringContainsString('"id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT', $sql);
        $this->assertStringContainsString('"email" TEXT NOT NULL', $sql);
        $this->assertStringContainsString('CREATE UNIQUE INDEX', $sql);
    }

    public function testGenerateSqlDdlForMariaDb(): void
    {
        $this->markTestSkipped('MariaDB server not available in test environment');

        $connection = ConnectionFactory::createMariaDbConnection(
            'localhost',
            'test',
            'test',
            'test'
        );

        $schemaGenerator = new SchemaGenerator($this->metadataFactory, $connection);

        $metadata = $this->createMockMetadata('users', [
            'id' => [
                'column_name' => 'id',
                'type' => 'integer',
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

        $sql = $schemaGenerator->generateSqlDdl(['User']);

        $this->assertStringContainsString('-- Generated SQL DDL for mariadb', $sql);
        $this->assertStringContainsString('CREATE TABLE `users`', $sql);
        $this->assertStringContainsString('`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY', $sql);
        $this->assertStringContainsString('`email` VARCHAR(255) NOT NULL', $sql);
        $this->assertStringContainsString('ENGINE=InnoDB DEFAULT CHARSET=utf8mb4', $sql);
    }

    public function testGenerateSchemaBuilderCodeWithDecimalColumn(): void
    {
        $metadata = $this->createMockMetadata('products', [
            'id' => [
                'column_name' => 'id',
                'type' => 'uuid',
                'id' => true,
                'nullable' => false
            ],
            'price' => [
                'column_name' => 'price',
                'type' => 'decimal',
                'precision' => 10,
                'scale' => 2,
                'nullable' => false
            ]
        ]);

        $this->metadataFactory
            ->expects($this->atLeastOnce())
            ->method('getMetadataFor')
            ->with('Product')
            ->willReturn($metadata);

        $code = $this->schemaGenerator->generateSchemaBuilderCode(['Product']);

        $this->assertStringContainsString("->decimal('price', ['precision' => 10, 'scale' => 2, 'not_null' => true])", $code);
    }

    public function testGenerateSchemaBuilderCodeWithNullableColumn(): void
    {
        $metadata = $this->createMockMetadata('users', [
            'id' => [
                'column_name' => 'id',
                'type' => 'uuid',
                'id' => true,
                'nullable' => false
            ],
            'bio' => [
                'column_name' => 'bio',
                'type' => 'text',
                'nullable' => true
            ]
        ]);

        $this->metadataFactory
            ->expects($this->atLeastOnce())
            ->method('getMetadataFor')
            ->with('User')
            ->willReturn($metadata);

        $code = $this->schemaGenerator->generateSchemaBuilderCode(['User']);

        $this->assertStringContainsString("->text('bio', ['nullable' => true])", $code);
    }

    public function testGenerateSchemaBuilderCodeWithDefaultValue(): void
    {
        $metadata = $this->createMockMetadata('users', [
            'id' => [
                'column_name' => 'id',
                'type' => 'uuid',
                'id' => true,
                'nullable' => false
            ],
            'active' => [
                'column_name' => 'active',
                'type' => 'boolean',
                'nullable' => false,
                'default' => true
            ]
        ]);

        $this->metadataFactory
            ->expects($this->atLeastOnce())
            ->method('getMetadataFor')
            ->with('User')
            ->willReturn($metadata);

        $code = $this->schemaGenerator->generateSchemaBuilderCode(['User']);

        $this->assertStringContainsString("->boolean('active', ['not_null' => true, 'default' => true])", $code);
    }

    public function testGenerateSchemaBuilderCodeWithAutoIncrementId(): void
    {
        $metadata = $this->createMockMetadata('users', [
            'id' => [
                'column_name' => 'id',
                'type' => 'integer',
                'id' => true,
                'nullable' => false
            ],
            'name' => [
                'column_name' => 'name',
                'type' => 'string',
                'length' => 255,
                'nullable' => false
            ]
        ]);

        $this->metadataFactory
            ->expects($this->atLeastOnce())
            ->method('getMetadataFor')
            ->with('User')
            ->willReturn($metadata);

        $code = $this->schemaGenerator->generateSchemaBuilderCode(['User']);

        $this->assertStringContainsString("->id('id')", $code);
        $this->assertStringNotContainsString("->integer('id'", $code);
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
