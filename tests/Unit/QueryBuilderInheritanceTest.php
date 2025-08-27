<?php

declare(strict_types=1);

namespace Tests\Unit;

use Fduarte42\Aurum\Connection\ConnectionInterface;
use Fduarte42\Aurum\Metadata\EntityMetadata;
use Fduarte42\Aurum\Metadata\InheritanceMapping;
use Fduarte42\Aurum\Metadata\MetadataFactory;
use Fduarte42\Aurum\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class QueryBuilderInheritanceTest extends TestCase
{
    private QueryBuilder $queryBuilder;
    private ConnectionInterface|MockObject $connection;
    private MetadataFactory|MockObject $metadataFactory;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->connection->method('quoteIdentifier')
            ->willReturnCallback(fn($identifier) => $identifier);

        $this->metadataFactory = $this->createMock(MetadataFactory::class);
        $this->queryBuilder = new QueryBuilder($this->connection, $this->metadataFactory);
    }

    public function testFromWithInheritanceRootClass(): void
    {
        // Create mock inheritance mapping for root class
        $inheritanceMapping = $this->createMock(InheritanceMapping::class);
        $inheritanceMapping->method('isRootClass')->willReturn(true);
        $inheritanceMapping->method('getDiscriminatorColumn')->willReturn('dtype');
        $inheritanceMapping->method('getAllClassNames')->willReturn([
            'App\\Entity\\Vehicle',
            'App\\Entity\\Car',
            'App\\Entity\\Motorcycle'
        ]);
        $inheritanceMapping->method('getDiscriminatorValue')
            ->willReturnCallback(fn($class) => $class);

        // Create mock metadata
        $metadata = $this->createMock(EntityMetadata::class);
        $metadata->method('getTableName')->willReturn('vehicles');
        $metadata->method('hasInheritance')->willReturn(true);
        $metadata->method('getInheritanceMapping')->willReturn($inheritanceMapping);
        $metadata->method('getClassName')->willReturn('App\\Entity\\Vehicle');

        $this->metadataFactory->method('getMetadataFor')
            ->with('App\\Entity\\Vehicle')
            ->willReturn($metadata);

        // Test from method with inheritance - use table name directly since class doesn't exist
        $this->queryBuilder->select('*')->from('vehicles', 'v');

        // Manually call the inheritance condition method to test it
        $reflectionMethod = new \ReflectionMethod($this->queryBuilder, 'addInheritanceDiscriminatorCondition');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($this->queryBuilder, $metadata, 'v');

        $sql = $this->queryBuilder->getSQL();
        $parameters = $this->queryBuilder->getParameters();

        // Should include discriminator WHERE clause for all classes in hierarchy
        $this->assertStringContainsString('FROM vehicles v', $sql);
        $this->assertStringContainsString('WHERE v.dtype IN (:discriminator_0, :discriminator_1, :discriminator_2)', $sql);
        
        $this->assertEquals('App\\Entity\\Vehicle', $parameters['discriminator_0']);
        $this->assertEquals('App\\Entity\\Car', $parameters['discriminator_1']);
        $this->assertEquals('App\\Entity\\Motorcycle', $parameters['discriminator_2']);
    }

    public function testFromWithInheritanceChildClass(): void
    {
        // Create mock inheritance mapping for child class
        $inheritanceMapping = $this->createMock(InheritanceMapping::class);
        $inheritanceMapping->method('isRootClass')->willReturn(false);
        $inheritanceMapping->method('getDiscriminatorColumn')->willReturn('dtype');
        $inheritanceMapping->method('getDiscriminatorValue')
            ->with('App\\Entity\\Car')
            ->willReturn('App\\Entity\\Car');

        // Create mock metadata
        $metadata = $this->createMock(EntityMetadata::class);
        $metadata->method('getTableName')->willReturn('vehicles');
        $metadata->method('hasInheritance')->willReturn(true);
        $metadata->method('getInheritanceMapping')->willReturn($inheritanceMapping);
        $metadata->method('getClassName')->willReturn('App\\Entity\\Car');

        $this->metadataFactory->method('getMetadataFor')
            ->with('App\\Entity\\Car')
            ->willReturn($metadata);

        // Test from method with child class - use table name directly
        $this->queryBuilder->select('*')->from('vehicles', 'c');

        // Manually call the inheritance condition method to test it
        $reflectionMethod = new \ReflectionMethod($this->queryBuilder, 'addInheritanceDiscriminatorCondition');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($this->queryBuilder, $metadata, 'c');

        $sql = $this->queryBuilder->getSQL();
        $parameters = $this->queryBuilder->getParameters();

        // Should include discriminator WHERE clause for specific child class
        $this->assertStringContainsString('FROM vehicles c', $sql);
        $this->assertStringContainsString('WHERE c.dtype = :discriminator_exact', $sql);
        
        $this->assertEquals('App\\Entity\\Car', $parameters['discriminator_exact']);
    }

    public function testFromWithoutInheritance(): void
    {
        // Create mock metadata without inheritance
        $metadata = $this->createMock(EntityMetadata::class);
        $metadata->method('getTableName')->willReturn('users');
        $metadata->method('hasInheritance')->willReturn(false);

        $this->metadataFactory->method('getMetadataFor')
            ->with('App\\Entity\\User')
            ->willReturn($metadata);

        // Test from method without inheritance - use table name directly
        $this->queryBuilder->select('*')->from('users', 'u');

        $sql = $this->queryBuilder->getSQL();
        $parameters = $this->queryBuilder->getParameters();

        // Should not include discriminator WHERE clause
        $this->assertStringContainsString('FROM users u', $sql);
        $this->assertStringNotContainsString('dtype', $sql);
        $this->assertEmpty($parameters);
    }

    public function testWhereEntityClass(): void
    {
        // Create mock inheritance mapping
        $inheritanceMapping = $this->createMock(InheritanceMapping::class);
        $inheritanceMapping->method('getDiscriminatorColumn')->willReturn('dtype');
        $inheritanceMapping->method('getDiscriminatorValue')
            ->with('App\\Entity\\Car')
            ->willReturn('App\\Entity\\Car');

        // Create mock metadata
        $metadata = $this->createMock(EntityMetadata::class);
        $metadata->method('hasInheritance')->willReturn(true);
        $metadata->method('getInheritanceMapping')->willReturn($inheritanceMapping);

        $this->metadataFactory->method('getMetadataFor')
            ->with('App\\Entity\\Car')
            ->willReturn($metadata);

        // Test whereEntityClass method
        $this->queryBuilder
            ->select('*')
            ->from('vehicles', 'v')
            ->whereEntityClass('App\\Entity\\Car', 'v');

        $sql = $this->queryBuilder->getSQL();
        $parameters = $this->queryBuilder->getParameters();

        $this->assertStringContainsString('WHERE v.dtype = :entity_class_discriminator', $sql);
        $this->assertEquals('App\\Entity\\Car', $parameters['entity_class_discriminator']);
    }

    public function testWhereNotEntityClass(): void
    {
        // Create mock inheritance mapping
        $inheritanceMapping = $this->createMock(InheritanceMapping::class);
        $inheritanceMapping->method('getDiscriminatorColumn')->willReturn('dtype');
        $inheritanceMapping->method('getDiscriminatorValue')
            ->willReturnCallback(fn($class) => $class);

        // Create mock metadata
        $metadata = $this->createMock(EntityMetadata::class);
        $metadata->method('hasInheritance')->willReturn(true);
        $metadata->method('getInheritanceMapping')->willReturn($inheritanceMapping);

        $this->metadataFactory->method('getMetadataFor')
            ->with('App\\Entity\\Car')
            ->willReturn($metadata);

        // Test whereNotEntityClass method
        $this->queryBuilder
            ->select('*')
            ->from('vehicles', 'v')
            ->whereNotEntityClass(['App\\Entity\\Car', 'App\\Entity\\Motorcycle'], 'v');

        $sql = $this->queryBuilder->getSQL();
        $parameters = $this->queryBuilder->getParameters();

        $this->assertStringContainsString('WHERE v.dtype NOT IN (:exclude_discriminator_0, :exclude_discriminator_1)', $sql);
        $this->assertEquals('App\\Entity\\Car', $parameters['exclude_discriminator_0']);
        $this->assertEquals('App\\Entity\\Motorcycle', $parameters['exclude_discriminator_1']);
    }

    public function testWhereEntityClassWithoutMetadataFactory(): void
    {
        $queryBuilderWithoutMetadata = new QueryBuilder($this->connection);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('MetadataFactory is required for inheritance-aware queries');

        $queryBuilderWithoutMetadata->whereEntityClass('App\\Entity\\Car');
    }

    public function testWhereEntityClassWithoutInheritance(): void
    {
        // Create mock metadata without inheritance
        $metadata = $this->createMock(EntityMetadata::class);
        $metadata->method('hasInheritance')->willReturn(false);

        $this->metadataFactory->method('getMetadataFor')
            ->with('App\\Entity\\User')
            ->willReturn($metadata);

        // Test whereEntityClass with non-inheritance entity
        $this->queryBuilder
            ->select('*')
            ->from('users', 'u')
            ->whereEntityClass('App\\Entity\\User', 'u');

        $sql = $this->queryBuilder->getSQL();
        $parameters = $this->queryBuilder->getParameters();

        // Should not add discriminator condition
        $this->assertStringNotContainsString('dtype', $sql);
        $this->assertEmpty($parameters);
    }

    public function testWhereNotEntityClassWithEmptyArray(): void
    {
        // Test whereNotEntityClass with empty array
        $this->queryBuilder
            ->select('*')
            ->from('vehicles', 'v')
            ->whereNotEntityClass([], 'v');

        $sql = $this->queryBuilder->getSQL();
        $parameters = $this->queryBuilder->getParameters();

        // Should not add any conditions
        $this->assertStringNotContainsString('dtype', $sql);
        $this->assertEmpty($parameters);
    }

    public function testInheritanceWithRootClassHavingNoChildren(): void
    {
        // Create mock inheritance mapping for root class with no children
        $inheritanceMapping = $this->createMock(InheritanceMapping::class);
        $inheritanceMapping->method('isRootClass')->willReturn(true);
        $inheritanceMapping->method('getDiscriminatorColumn')->willReturn('dtype');
        $inheritanceMapping->method('getAllClassNames')->willReturn(['App\\Entity\\Vehicle']); // Only root class

        // Create mock metadata
        $metadata = $this->createMock(EntityMetadata::class);
        $metadata->method('getTableName')->willReturn('vehicles');
        $metadata->method('hasInheritance')->willReturn(true);
        $metadata->method('getInheritanceMapping')->willReturn($inheritanceMapping);
        $metadata->method('getClassName')->willReturn('App\\Entity\\Vehicle');

        $this->metadataFactory->method('getMetadataFor')
            ->with('App\\Entity\\Vehicle')
            ->willReturn($metadata);

        // Test from method with root class having no children - use table name directly
        $this->queryBuilder->select('*')->from('vehicles', 'v');

        // Manually call the inheritance condition method to test it
        $reflectionMethod = new \ReflectionMethod($this->queryBuilder, 'addInheritanceDiscriminatorCondition');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($this->queryBuilder, $metadata, 'v');

        $sql = $this->queryBuilder->getSQL();
        $parameters = $this->queryBuilder->getParameters();

        // Should not include discriminator WHERE clause when there are no child classes
        $this->assertStringContainsString('FROM vehicles v', $sql);
        $this->assertStringNotContainsString('dtype', $sql);
        $this->assertEmpty($parameters);
    }
}
