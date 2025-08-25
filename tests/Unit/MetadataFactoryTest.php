<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit;

use Fduarte42\Aurum\Metadata\MetadataFactory;
use Fduarte42\Aurum\Tests\Fixtures\Todo;
use Fduarte42\Aurum\Tests\Fixtures\User;
use PHPUnit\Framework\TestCase;

class MetadataFactoryTest extends TestCase
{
    private MetadataFactory $metadataFactory;

    protected function setUp(): void
    {
        $this->metadataFactory = new MetadataFactory();
    }

    public function testGetMetadataForTodo(): void
    {
        $metadata = $this->metadataFactory->getMetadataFor(Todo::class);
        
        $this->assertEquals(Todo::class, $metadata->getClassName());
        $this->assertEquals('todos', $metadata->getTableName());
        $this->assertEquals('id', $metadata->getIdentifierFieldName());
        
        $fieldMappings = $metadata->getFieldMappings();
        $this->assertArrayHasKey('id', $fieldMappings);
        $this->assertArrayHasKey('title', $fieldMappings);
        $this->assertArrayHasKey('description', $fieldMappings);
        $this->assertArrayHasKey('completed', $fieldMappings);
        $this->assertArrayHasKey('priority', $fieldMappings);
        
        $titleMapping = $fieldMappings['title'];
        $this->assertEquals('string', $titleMapping->getType());
        $this->assertEquals(255, $titleMapping->getLength());
        $this->assertFalse($titleMapping->isNullable());
        
        $priorityMapping = $fieldMappings['priority'];
        $this->assertEquals('decimal', $priorityMapping->getType());
        $this->assertEquals(10, $priorityMapping->getPrecision());
        $this->assertEquals(2, $priorityMapping->getScale());
        $this->assertTrue($priorityMapping->isNullable());
    }

    public function testGetMetadataForUser(): void
    {
        $metadata = $this->metadataFactory->getMetadataFor(User::class);
        
        $this->assertEquals(User::class, $metadata->getClassName());
        $this->assertEquals('users', $metadata->getTableName());
        
        $fieldMappings = $metadata->getFieldMappings();
        $emailMapping = $fieldMappings['email'];
        $this->assertTrue($emailMapping->isUnique());
        
        $associationMappings = $metadata->getAssociationMappings();
        $this->assertArrayHasKey('todos', $associationMappings);
        
        $todosMapping = $associationMappings['todos'];
        $this->assertEquals('OneToMany', $todosMapping->getType());
        $this->assertEquals(Todo::class, $todosMapping->getTargetEntity());
        $this->assertEquals('user', $todosMapping->getMappedBy());
        $this->assertFalse($todosMapping->isOwningSide());
    }

    public function testEntityInstantiation(): void
    {
        $metadata = $this->metadataFactory->getMetadataFor(Todo::class);
        $todo = $metadata->newInstance();
        
        $this->assertInstanceOf(Todo::class, $todo);
    }

    public function testFieldValueAccess(): void
    {
        $metadata = $this->metadataFactory->getMetadataFor(Todo::class);
        $todo = new Todo('Test Todo', 'Test Description');
        
        $title = $metadata->getFieldValue($todo, 'title');
        $this->assertEquals('Test Todo', $title);
        
        $metadata->setFieldValue($todo, 'title', 'Updated Title');
        $updatedTitle = $metadata->getFieldValue($todo, 'title');
        $this->assertEquals('Updated Title', $updatedTitle);
    }

    public function testMetadataCache(): void
    {
        $metadata1 = $this->metadataFactory->getMetadataFor(Todo::class);
        $metadata2 = $this->metadataFactory->getMetadataFor(Todo::class);
        
        $this->assertSame($metadata1, $metadata2);
    }

    public function testClearCache(): void
    {
        $this->metadataFactory->getMetadataFor(Todo::class);
        $this->assertTrue($this->metadataFactory->hasMetadata(Todo::class));

        $this->metadataFactory->clearCache();
        $this->assertFalse($this->metadataFactory->hasMetadata(Todo::class));
    }

    public function testInvalidEntityClass(): void
    {
        $this->expectException(\Fduarte42\Aurum\Exception\ORMException::class);
        $this->expectExceptionMessage('Class "stdClass" is not a valid entity class');

        $this->metadataFactory->getMetadataFor(\stdClass::class);
    }

    public function testGetTableNameFromClassName(): void
    {
        // Test with a class that doesn't specify table name
        $metadata = $this->metadataFactory->getMetadataFor(Todo::class);
        $this->assertEquals('todos', $metadata->getTableName());
    }

    public function testGetColumnNameFromFieldName(): void
    {
        $metadata = $this->metadataFactory->getMetadataFor(Todo::class);
        $fieldMapping = $metadata->getFieldMapping('createdAt');
        $this->assertEquals('created_at', $fieldMapping->getColumnName());
    }

    public function testAssociationMappingDetails(): void
    {
        $metadata = $this->metadataFactory->getMetadataFor(Todo::class);
        $userMapping = $metadata->getAssociationMapping('user');

        $this->assertNotNull($userMapping);
        $this->assertEquals('ManyToOne', $userMapping->getType());
        $this->assertTrue($userMapping->isOwningSide());
        $this->assertEquals('user_id', $userMapping->getJoinColumn());
        $this->assertEquals('id', $userMapping->getReferencedColumnName());
        $this->assertTrue($userMapping->isLazy());
        $this->assertTrue($userMapping->isNullable());
    }

    public function testLoadMetadataWithReflectionError(): void
    {
        $this->expectException(\ReflectionException::class);
        $this->expectExceptionMessage('Class "NonExistentClass" does not exist');

        $this->metadataFactory->getMetadataFor('NonExistentClass');
    }

    public function testMetadataFactoryWithCachedResults(): void
    {
        // Load metadata twice to test caching
        $metadata1 = $this->metadataFactory->getMetadataFor(Todo::class);
        $metadata2 = $this->metadataFactory->getMetadataFor(Todo::class);

        // Should return the same instance (cached)
        $this->assertSame($metadata1, $metadata2);
    }

    public function testMetadataFactoryFieldMappingTypes(): void
    {
        $metadata = $this->metadataFactory->getMetadataFor(Todo::class);

        // Test different field types
        $idMapping = $metadata->getFieldMapping('id');
        $this->assertEquals('uuid', $idMapping->getType());
        $this->assertTrue($idMapping->isIdentifier());

        $completedMapping = $metadata->getFieldMapping('completed');
        $this->assertEquals('boolean', $completedMapping->getType());

        $createdAtMapping = $metadata->getFieldMapping('createdAt');
        $this->assertEquals('datetime', $createdAtMapping->getType());
    }

    public function testMetadataFactoryTableNameGeneration(): void
    {
        $todoMetadata = $this->metadataFactory->getMetadataFor(Todo::class);
        $userMetadata = $this->metadataFactory->getMetadataFor(User::class);

        // Test that table names are generated correctly
        $this->assertEquals('todos', $todoMetadata->getTableName());
        $this->assertEquals('users', $userMetadata->getTableName());
    }

    public function testMetadataFactoryAssociationMappings(): void
    {
        $todoMetadata = $this->metadataFactory->getMetadataFor(Todo::class);
        $userMetadata = $this->metadataFactory->getMetadataFor(User::class);

        // Test Todo -> User association
        $userAssociation = $todoMetadata->getAssociationMapping('user');
        $this->assertNotNull($userAssociation);
        $this->assertEquals('ManyToOne', $userAssociation->getType());
        $this->assertEquals(User::class, $userAssociation->getTargetEntity());

        // Test User -> Todos association
        $todosAssociation = $userMetadata->getAssociationMapping('todos');
        $this->assertNotNull($todosAssociation);
        $this->assertEquals('OneToMany', $todosAssociation->getType());
        $this->assertEquals(Todo::class, $todosAssociation->getTargetEntity());
    }

    public function testAssociationMappingMethods(): void
    {
        $mapping = new \Fduarte42\Aurum\Metadata\AssociationMapping(
            fieldName: 'user',
            targetEntity: User::class,
            type: 'ManyToOne',
            isOwningSide: true,
            mappedBy: null,
            inversedBy: null,
            joinColumn: 'user_id',
            referencedColumnName: 'id'
        );

        // Test all getter methods
        $this->assertEquals('user', $mapping->getFieldName());
        $this->assertEquals('ManyToOne', $mapping->getType());
        $this->assertEquals(User::class, $mapping->getTargetEntity());
        $this->assertTrue($mapping->isOwningSide());
        $this->assertEquals('user_id', $mapping->getJoinColumn());
        $this->assertEquals('id', $mapping->getReferencedColumnName());
    }

    public function testAssociationMappingInverseSide(): void
    {
        $mapping = new \Fduarte42\Aurum\Metadata\AssociationMapping(
            fieldName: 'todos',
            targetEntity: Todo::class,
            type: 'OneToMany',
            isOwningSide: false,  // Not owning side
            mappedBy: 'user',
            inversedBy: null,
            joinColumn: null,   // No join column for inverse side
            referencedColumnName: null    // No referenced column for inverse side
        );

        $this->assertFalse($mapping->isOwningSide());
        $this->assertNull($mapping->getJoinColumn());
        $this->assertNull($mapping->getReferencedColumnName());
        $this->assertEquals('user', $mapping->getMappedBy());
    }

    public function testAssociationMappingAllMethods(): void
    {
        $mapping = new \Fduarte42\Aurum\Metadata\AssociationMapping(
            fieldName: 'user',
            targetEntity: User::class,
            type: 'ManyToOne',
            isOwningSide: true,
            mappedBy: null,
            inversedBy: 'todos',
            joinColumn: 'user_id',
            referencedColumnName: 'id',
            lazy: false,
            nullable: false,
            cascade: ['persist', 'remove']
        );

        // Test all getter methods
        $this->assertEquals('user', $mapping->getFieldName());
        $this->assertEquals(User::class, $mapping->getTargetEntity());
        $this->assertEquals('ManyToOne', $mapping->getType());
        $this->assertTrue($mapping->isOwningSide());
        $this->assertNull($mapping->getMappedBy());
        $this->assertEquals('todos', $mapping->getInversedBy());
        $this->assertEquals('user_id', $mapping->getJoinColumn());
        $this->assertEquals('id', $mapping->getReferencedColumnName());
        $this->assertFalse($mapping->isLazy());
        $this->assertFalse($mapping->isNullable());
        $this->assertEquals(['persist', 'remove'], $mapping->getCascade());
        $this->assertTrue($mapping->isCascade('persist'));
        $this->assertTrue($mapping->isCascade('remove'));
        $this->assertFalse($mapping->isCascade('merge'));
    }

    public function testAssociationMappingCascadeAll(): void
    {
        $mapping = new \Fduarte42\Aurum\Metadata\AssociationMapping(
            fieldName: 'user',
            targetEntity: User::class,
            type: 'ManyToOne',
            cascade: ['all']
        );

        // Test cascade 'all' behavior
        $this->assertTrue($mapping->isCascade('persist'));
        $this->assertTrue($mapping->isCascade('remove'));
        $this->assertTrue($mapping->isCascade('merge'));
        $this->assertTrue($mapping->isCascade('any_operation'));
    }

    public function testEntityMetadataGetters(): void
    {
        $metadata = new \Fduarte42\Aurum\Metadata\EntityMetadata(User::class, 'users');

        // Add some field mappings
        $idMapping = new \Fduarte42\Aurum\Metadata\FieldMapping(
            fieldName: 'id',
            columnName: 'id',
            type: 'uuid',
            isIdentifier: true
        );
        $nameMapping = new \Fduarte42\Aurum\Metadata\FieldMapping('name', 'name', 'string');

        $metadata->addFieldMapping($idMapping);
        $metadata->addFieldMapping($nameMapping);

        // Add association mapping
        $todosMapping = new \Fduarte42\Aurum\Metadata\AssociationMapping(
            fieldName: 'todos',
            targetEntity: Todo::class,
            type: 'OneToMany',
            isOwningSide: false
        );
        $metadata->addAssociationMapping($todosMapping);

        // Test getters
        $this->assertEquals(User::class, $metadata->getClassName());
        $this->assertEquals('users', $metadata->getTableName());
        $this->assertEquals(['id' => $idMapping, 'name' => $nameMapping], $metadata->getFieldMappings());
        $this->assertEquals(['todos' => $todosMapping], $metadata->getAssociationMappings());
    }

    public function testEntityMetadataGetIdentifierFieldName(): void
    {
        $metadata = new \Fduarte42\Aurum\Metadata\EntityMetadata(User::class, 'users');

        $idMapping = new \Fduarte42\Aurum\Metadata\FieldMapping(
            fieldName: 'id',
            columnName: 'id',
            type: 'uuid',
            isIdentifier: true
        );
        $nameMapping = new \Fduarte42\Aurum\Metadata\FieldMapping('name', 'name', 'string');

        $metadata->addFieldMapping($idMapping);
        $metadata->addFieldMapping($nameMapping);

        $identifierField = $metadata->getIdentifierFieldName();
        $this->assertEquals('id', $identifierField);
    }

    public function testEntityMetadataGetIdentifierColumnName(): void
    {
        $metadata = new \Fduarte42\Aurum\Metadata\EntityMetadata(User::class, 'users');

        $idMapping = new \Fduarte42\Aurum\Metadata\FieldMapping(
            fieldName: 'id',
            columnName: 'user_id',
            type: 'uuid',
            isIdentifier: true
        );

        $metadata->addFieldMapping($idMapping);

        $identifierColumn = $metadata->getIdentifierColumnName();
        $this->assertEquals('user_id', $identifierColumn);
    }

    public function testEntityMetadataGetFieldMapping(): void
    {
        $metadata = new \Fduarte42\Aurum\Metadata\EntityMetadata(User::class, 'users');

        $nameMapping = new \Fduarte42\Aurum\Metadata\FieldMapping('name', 'name', 'string');
        $metadata->addFieldMapping($nameMapping);

        $retrievedMapping = $metadata->getFieldMapping('name');
        $this->assertSame($nameMapping, $retrievedMapping);

        $nonexistentMapping = $metadata->getFieldMapping('nonexistent');
        $this->assertNull($nonexistentMapping);
    }

    public function testEntityMetadataGetAssociationMapping(): void
    {
        $metadata = new \Fduarte42\Aurum\Metadata\EntityMetadata(User::class, 'users');

        $todosMapping = new \Fduarte42\Aurum\Metadata\AssociationMapping(
            fieldName: 'todos',
            targetEntity: Todo::class,
            type: 'OneToMany',
            isOwningSide: false
        );
        $metadata->addAssociationMapping($todosMapping);

        $retrievedMapping = $metadata->getAssociationMapping('todos');
        $this->assertSame($todosMapping, $retrievedMapping);

        $nonexistentMapping = $metadata->getAssociationMapping('nonexistent');
        $this->assertNull($nonexistentMapping);
    }

    public function testEntityMetadataGetColumnName(): void
    {
        $metadata = new \Fduarte42\Aurum\Metadata\EntityMetadata(User::class, 'users');

        $nameMapping = new \Fduarte42\Aurum\Metadata\FieldMapping('name', 'user_name', 'string');
        $metadata->addFieldMapping($nameMapping);

        $columnName = $metadata->getColumnName('name');
        $this->assertEquals('user_name', $columnName);

        // Test fallback for field without mapping
        $fallbackColumn = $metadata->getColumnName('nonexistent');
        $this->assertEquals('nonexistent', $fallbackColumn);
    }

    public function testEntityMetadataIsIdentifier(): void
    {
        $metadata = new \Fduarte42\Aurum\Metadata\EntityMetadata(User::class, 'users');

        $idMapping = new \Fduarte42\Aurum\Metadata\FieldMapping(
            fieldName: 'id',
            columnName: 'id',
            type: 'uuid',
            isIdentifier: true
        );
        $nameMapping = new \Fduarte42\Aurum\Metadata\FieldMapping('name', 'name', 'string');

        $metadata->addFieldMapping($idMapping);
        $metadata->addFieldMapping($nameMapping);

        $this->assertTrue($metadata->isIdentifier('id'));
        $this->assertFalse($metadata->isIdentifier('name'));
    }

    public function testEntityMetadataGetColumnNames(): void
    {
        $metadata = new \Fduarte42\Aurum\Metadata\EntityMetadata(User::class, 'users');

        $idMapping = new \Fduarte42\Aurum\Metadata\FieldMapping('id', 'id', 'uuid');
        $nameMapping = new \Fduarte42\Aurum\Metadata\FieldMapping('name', 'user_name', 'string');

        $metadata->addFieldMapping($idMapping);
        $metadata->addFieldMapping($nameMapping);

        $columnNames = $metadata->getColumnNames();
        $this->assertEquals(['id', 'user_name'], $columnNames);
    }

    public function testEntityMetadataGetFieldName(): void
    {
        $metadata = new \Fduarte42\Aurum\Metadata\EntityMetadata(User::class, 'users');

        $nameMapping = new \Fduarte42\Aurum\Metadata\FieldMapping('name', 'user_name', 'string');
        $metadata->addFieldMapping($nameMapping);

        $fieldName = $metadata->getFieldName('user_name');
        $this->assertEquals('name', $fieldName);

        // Test fallback for column without mapping
        $fallbackField = $metadata->getFieldName('nonexistent_column');
        $this->assertEquals('nonexistent_column', $fallbackField);
    }

    public function testEntityMetadataNewInstance(): void
    {
        $metadata = new \Fduarte42\Aurum\Metadata\EntityMetadata(User::class, 'users');

        $instance = $metadata->newInstance();
        $this->assertInstanceOf(User::class, $instance);
    }

    public function testEntityMetadataConstructor(): void
    {
        // Test constructor with different parameters
        $metadata = new \Fduarte42\Aurum\Metadata\EntityMetadata(User::class, 'users');

        $this->assertEquals(User::class, $metadata->getClassName());
        $this->assertEquals('users', $metadata->getTableName());
    }

    public function testEntityMetadataGetIdentifierFieldNameWithoutIdentifier(): void
    {
        $metadata = new \Fduarte42\Aurum\Metadata\EntityMetadata(User::class, 'users');

        // Add non-identifier field
        $nameMapping = new \Fduarte42\Aurum\Metadata\FieldMapping('name', 'name', 'string');
        $metadata->addFieldMapping($nameMapping);

        $this->expectException(\Fduarte42\Aurum\Exception\ORMException::class);
        $this->expectExceptionMessage('No identifier field found');

        $metadata->getIdentifierFieldName();
    }

    public function testEntityMetadataSetIdentifierValue(): void
    {
        $metadata = new \Fduarte42\Aurum\Metadata\EntityMetadata(User::class, 'users');

        $idMapping = new \Fduarte42\Aurum\Metadata\FieldMapping(
            fieldName: 'id',
            columnName: 'id',
            type: 'uuid',
            isIdentifier: true
        );
        $metadata->addFieldMapping($idMapping);

        $user = new User('test@example.com', 'Test User');
        $newId = \Ramsey\Uuid\Uuid::uuid4()->toString();

        // Set identifier value
        $metadata->setIdentifierValue($user, $newId);

        // Verify identifier value was set
        $retrievedId = $metadata->getIdentifierValue($user);
        $this->assertEquals($newId, $retrievedId);
    }

    public function testMetadataFactoryHasMetadata(): void
    {
        $factory = new MetadataFactory();

        // Initially should not have metadata
        $this->assertFalse($factory->hasMetadata(User::class));

        // After getting metadata, should have it
        $factory->getMetadataFor(User::class);
        $this->assertTrue($factory->hasMetadata(User::class));

        // Clear cache and check again
        $factory->clearCache();
        $this->assertFalse($factory->hasMetadata(User::class));
    }
}
