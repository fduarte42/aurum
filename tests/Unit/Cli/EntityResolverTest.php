<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit\Cli;

use Fduarte42\Aurum\Cli\EntityResolver;
use Fduarte42\Aurum\Metadata\MetadataFactory;
use Fduarte42\Aurum\Metadata\EntityMetadataInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class EntityResolverTest extends TestCase
{
    private EntityResolver $entityResolver;
    private MetadataFactory|MockObject $metadataFactory;

    protected function setUp(): void
    {
        $this->metadataFactory = $this->createMock(MetadataFactory::class);
        $this->entityResolver = new EntityResolver($this->metadataFactory);
    }

    public function testResolveEntitiesWithEntitiesOption(): void
    {
        // Create the test entity class
        if (!class_exists('TestEntity')) {
            eval('class TestEntity {}');
        }

        $this->metadataFactory
            ->expects($this->once())
            ->method('getMetadataFor')
            ->with('TestEntity')
            ->willReturn($this->createMock(EntityMetadataInterface::class));

        $options = ['entities' => 'TestEntity'];
        $result = $this->entityResolver->resolveEntities($options);

        $this->assertEquals(['TestEntity'], $result);
    }

    public function testResolveEntitiesWithMultipleEntities(): void
    {
        // Create the test entity classes
        if (!class_exists('TestEntity1')) {
            eval('class TestEntity1 {}');
        }
        if (!class_exists('TestEntity2')) {
            eval('class TestEntity2 {}');
        }

        $this->metadataFactory
            ->expects($this->exactly(2))
            ->method('getMetadataFor')
            ->willReturnMap([
                ['TestEntity1', $this->createMock(EntityMetadataInterface::class)],
                ['TestEntity2', $this->createMock(EntityMetadataInterface::class)]
            ]);

        $options = ['entities' => 'TestEntity1,TestEntity2'];
        $result = $this->entityResolver->resolveEntities($options);

        $this->assertEquals(['TestEntity1', 'TestEntity2'], $result);
    }

    public function testResolveEntitiesWithNamespaceOption(): void
    {
        // Mock that TestNamespace\Entity1 is an entity
        $this->metadataFactory
            ->expects($this->atLeastOnce())
            ->method('getMetadataFor')
            ->willReturnCallback(function($class) {
                if ($class === 'TestNamespace\\Entity1') {
                    return $this->createMock(EntityMetadataInterface::class);
                }
                throw new \Exception('Not an entity');
            });

        // We need to create a test class in the namespace for this test
        if (!class_exists('TestNamespace\\Entity1')) {
            eval('namespace TestNamespace; class Entity1 {}');
        }

        $options = ['namespace' => 'TestNamespace'];
        $result = $this->entityResolver->resolveEntities($options);

        $this->assertContains('TestNamespace\\Entity1', $result);
    }

    public function testResolveEntitiesWithAutoDiscovery(): void
    {
        // Mock auto-discovery finding some entities
        $this->metadataFactory
            ->expects($this->atLeastOnce())
            ->method('getMetadataFor')
            ->willReturnCallback(function($class) {
                if ($class === 'AutoDiscoveredEntity') {
                    return $this->createMock(EntityMetadataInterface::class);
                }
                throw new \Exception('Not an entity');
            });

        // Create a test entity class
        if (!class_exists('AutoDiscoveredEntity')) {
            eval('class AutoDiscoveredEntity {}');
        }

        $options = []; // No entities or namespace specified
        $result = $this->entityResolver->resolveEntities($options);

        $this->assertContains('AutoDiscoveredEntity', $result);
    }

    public function testResolveEntitiesWithInvalidEntity(): void
    {
        // Mock that the class doesn't exist by not setting up any expectations
        // The resolver will try all candidates and fail

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Entity class not found: NonExistentEntity');

        $options = ['entities' => 'NonExistentEntity'];
        $this->entityResolver->resolveEntities($options);
    }

    public function testResolveEntitiesWithEmptyNamespace(): void
    {
        // Mock that no entities exist in the namespace
        $this->metadataFactory
            ->method('getMetadataFor')
            ->willThrowException(new \Exception('Not an entity'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No entities found in namespace: EmptyNamespace\\');

        $options = ['namespace' => 'EmptyNamespace'];
        $this->entityResolver->resolveEntities($options);
    }

    public function testResolveEntitiesWithNoEntitiesFound(): void
    {
        $this->metadataFactory
            ->expects($this->atLeastOnce())
            ->method('getMetadataFor')
            ->willThrowException(new \Exception('Not an entity'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No entities found. Make sure your entities are loaded and have proper metadata.');

        $options = []; // Auto-discovery with no entities
        $this->entityResolver->resolveEntities($options);
    }

    public function testGetEntitySummary(): void
    {
        // Create test classes that actually exist
        if (!class_exists('TestUser')) {
            eval('class TestUser {}');
        }
        if (!class_exists('TestPost')) {
            eval('class TestPost {}');
        }

        $entityClasses = ['TestUser', 'TestPost'];
        $summary = $this->entityResolver->getEntitySummary($entityClasses);

        $this->assertEquals('Found 2 entities: TestUser, TestPost', $summary);
    }

    public function testGetEntitySummarySingleEntity(): void
    {
        // Create test class that actually exists
        if (!class_exists('TestSingleUser')) {
            eval('class TestSingleUser {}');
        }

        $entityClasses = ['TestSingleUser'];
        $summary = $this->entityResolver->getEntitySummary($entityClasses);

        $this->assertEquals('Found 1 entity: TestSingleUser', $summary);
    }

    public function testResolveEntityClassesWithAppEntityNamespace(): void
    {
        // Create a test entity in App\Entity namespace
        if (!class_exists('App\\Entity\\AppTestEntity')) {
            eval('namespace App\\Entity; class AppTestEntity {}');
        }

        $this->metadataFactory
            ->expects($this->once())
            ->method('getMetadataFor')
            ->with('App\\Entity\\AppTestEntity')
            ->willReturn($this->createMock(EntityMetadataInterface::class));

        $options = ['entities' => 'AppTestEntity'];
        $result = $this->entityResolver->resolveEntities($options);

        $this->assertEquals(['App\\Entity\\AppTestEntity'], $result);
    }

    public function testResolveEntityClassesWithFullyQualifiedName(): void
    {
        // Create the test class
        if (!class_exists('Full\\Qualified\\EntityName')) {
            eval('namespace Full\\Qualified; class EntityName {}');
        }

        $this->metadataFactory
            ->expects($this->once())
            ->method('getMetadataFor')
            ->with('Full\\Qualified\\EntityName')
            ->willReturn($this->createMock(EntityMetadataInterface::class));

        $options = ['entities' => 'Full\\Qualified\\EntityName'];
        $result = $this->entityResolver->resolveEntities($options);

        $this->assertEquals(['Full\\Qualified\\EntityName'], $result);
    }
}
