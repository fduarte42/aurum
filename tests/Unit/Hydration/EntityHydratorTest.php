<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit\Hydration;

use Fduarte42\Aurum\Hydration\EntityHydrator;
use Fduarte42\Aurum\Hydration\EntityHydratorInterface;
use Fduarte42\Aurum\Metadata\EntityMetadataInterface;
use Fduarte42\Aurum\Metadata\FieldMappingInterface;
use Fduarte42\Aurum\Metadata\InheritanceMappingInterface;
use Fduarte42\Aurum\Metadata\MetadataFactory;
use Fduarte42\Aurum\Proxy\ProxyFactoryInterface;
use Fduarte42\Aurum\UnitOfWork\UnitOfWorkInterface;
use PHPUnit\Framework\TestCase;

class EntityHydratorTest extends TestCase
{
    private EntityHydrator $entityHydrator;
    private MetadataFactory $metadataFactory;
    private ProxyFactoryInterface $proxyFactory;
    private EntityMetadataInterface $metadata;
    private UnitOfWorkInterface $unitOfWork;

    protected function setUp(): void
    {
        $this->metadataFactory = $this->createMock(MetadataFactory::class);
        $this->proxyFactory = $this->createMock(ProxyFactoryInterface::class);
        $this->metadata = $this->createMock(EntityMetadataInterface::class);
        $this->unitOfWork = $this->createMock(UnitOfWorkInterface::class);

        $this->entityHydrator = new EntityHydrator(
            $this->metadataFactory
        );
    }

    public function testHydrateDetached(): void
    {
        $data = ['id' => 1, 'name' => 'Test Entity'];
        $entityClass = TestEntity::class;
        
        $entity = new TestEntity();
        
        $fieldMapping = $this->createMock(FieldMappingInterface::class);
        $fieldMapping->method('getFieldName')->willReturn('name');
        $fieldMapping->method('getColumnName')->willReturn('name');
        
        $this->metadataFactory->expects($this->once())
            ->method('getMetadataFor')
            ->with($entityClass)
            ->willReturn($this->metadata);
            
        $this->metadata->expects($this->once())
            ->method('hasInheritance')
            ->willReturn(false);
            
        $this->metadata->expects($this->once())
            ->method('newInstance')
            ->willReturn($entity);
            
        $this->metadata->expects($this->once())
            ->method('getFieldMappings')
            ->willReturn([$fieldMapping]);
            
        $this->metadata->expects($this->once())
            ->method('setFieldValue')
            ->with($entity, 'name', 'Test Entity');
        
        $result = $this->entityHydrator->hydrateDetached($data, $entityClass);
        
        $this->assertSame($entity, $result);
    }

    public function testHydrateManaged(): void
    {
        $data = ['id' => 1, 'name' => 'Test Entity'];
        $entityClass = TestEntity::class;
        
        $entity = new TestEntity();
        
        $fieldMapping = $this->createMock(FieldMappingInterface::class);
        $fieldMapping->method('getFieldName')->willReturn('name');
        $fieldMapping->method('getColumnName')->willReturn('name');
        
        $this->metadataFactory->expects($this->once())
            ->method('getMetadataFor')
            ->with($entityClass)
            ->willReturn($this->metadata);
            
        $this->metadata->expects($this->once())
            ->method('hasInheritance')
            ->willReturn(false);
            
        $this->metadata->expects($this->once())
            ->method('newInstance')
            ->willReturn($entity);
            
        $this->metadata->expects($this->once())
            ->method('getFieldMappings')
            ->willReturn([$fieldMapping]);
            
        $this->metadata->expects($this->once())
            ->method('setFieldValue')
            ->with($entity, 'name', 'Test Entity');
            
        $this->metadata->expects($this->once())
            ->method('getIdentifierValue')
            ->with($entity)
            ->willReturn(1);
            
        $this->unitOfWork->expects($this->once())
            ->method('addToIdentityMap')
            ->with($entityClass . '.1', $entity);
            
        $this->unitOfWork->expects($this->once())
            ->method('setOriginalEntityData')
            ->with($entity);
        
        $result = $this->entityHydrator->hydrateManaged($data, $entityClass, $this->unitOfWork);
        
        $this->assertSame($entity, $result);
    }

    public function testHydrateWithInheritance(): void
    {
        $data = ['id' => 1, 'name' => 'Test Entity', '__discriminator' => 'child'];
        $rootEntityClass = TestEntity::class;
        $childEntityClass = TestChildEntity::class;
        
        $entity = new TestChildEntity();
        
        $rootMetadata = $this->createMock(EntityMetadataInterface::class);
        $childMetadata = $this->createMock(EntityMetadataInterface::class);
        $inheritanceMapping = $this->createMock(InheritanceMappingInterface::class);
        
        $fieldMapping = $this->createMock(FieldMappingInterface::class);
        $fieldMapping->method('getFieldName')->willReturn('name');
        $fieldMapping->method('getColumnName')->willReturn('name');
        
        $this->metadataFactory->expects($this->exactly(2))
            ->method('getMetadataFor')
            ->willReturnMap([
                [$rootEntityClass, $rootMetadata],
                [$childEntityClass, $childMetadata]
            ]);
            
        $rootMetadata->expects($this->once())
            ->method('hasInheritance')
            ->willReturn(true);
            
        $rootMetadata->expects($this->once())
            ->method('getInheritanceMapping')
            ->willReturn($inheritanceMapping);
            
        $inheritanceMapping->expects($this->once())
            ->method('getDiscriminatorColumn')
            ->willReturn('__discriminator');
            
        $inheritanceMapping->expects($this->once())
            ->method('getDiscriminatorMap')
            ->willReturn(['child' => $childEntityClass]);
            
        $childMetadata->expects($this->once())
            ->method('newInstance')
            ->willReturn($entity);
            
        $childMetadata->expects($this->once())
            ->method('getFieldMappings')
            ->willReturn([$fieldMapping]);
            
        $childMetadata->expects($this->once())
            ->method('setFieldValue')
            ->with($entity, 'name', 'Test Entity');
        
        $result = $this->entityHydrator->hydrateDetached($data, $rootEntityClass);
        
        $this->assertSame($entity, $result);
    }

    public function testPopulateEntity(): void
    {
        $entity = new TestEntity();
        $data = ['name' => 'Updated Name'];
        
        $fieldMapping = $this->createMock(FieldMappingInterface::class);
        $fieldMapping->method('getFieldName')->willReturn('name');
        $fieldMapping->method('getColumnName')->willReturn('name');
        
        $this->metadata->expects($this->once())
            ->method('getFieldMappings')
            ->willReturn([$fieldMapping]);
            
        $this->metadata->expects($this->once())
            ->method('setFieldValue')
            ->with($entity, 'name', 'Updated Name');
        
        $this->entityHydrator->populateEntity($entity, $data, $this->metadata);
    }

    public function testMergeEntities(): void
    {
        $sourceEntity = new TestEntity();
        $targetEntity = new TestEntity();

        $fieldMapping = $this->createMock(FieldMappingInterface::class);
        $fieldMapping->method('getFieldName')->willReturn('name');
        $fieldMapping->method('isIdentifier')->willReturn(false);

        $this->metadataFactory->expects($this->once())
            ->method('getMetadataFor')
            ->with(TestEntity::class)
            ->willReturn($this->metadata);

        $this->metadata->expects($this->once())
            ->method('getFieldMappings')
            ->willReturn([$fieldMapping]);

        $this->metadata->expects($this->once())
            ->method('getFieldValue')
            ->with($sourceEntity, 'name')
            ->willReturn('Source Value');

        $this->metadata->expects($this->once())
            ->method('setFieldValue')
            ->with($targetEntity, 'name', 'Source Value');

        $this->entityHydrator->mergeEntities($sourceEntity, $targetEntity);
    }

    public function testHydrateMultiple(): void
    {
        $dataArray = [
            ['id' => 1, 'name' => 'Entity 1'],
            ['id' => 2, 'name' => 'Entity 2']
        ];
        $entityClass = TestEntity::class;
        
        $entity1 = new TestEntity();
        $entity2 = new TestEntity();
        
        $fieldMapping = $this->createMock(FieldMappingInterface::class);
        $fieldMapping->method('getFieldName')->willReturn('name');
        $fieldMapping->method('getColumnName')->willReturn('name');
        
        $this->metadataFactory->expects($this->exactly(2))
            ->method('getMetadataFor')
            ->with($entityClass)
            ->willReturn($this->metadata);
            
        $this->metadata->expects($this->exactly(2))
            ->method('hasInheritance')
            ->willReturn(false);
            
        $this->metadata->expects($this->exactly(2))
            ->method('newInstance')
            ->willReturnOnConsecutiveCalls($entity1, $entity2);
            
        $this->metadata->expects($this->exactly(2))
            ->method('getFieldMappings')
            ->willReturn([$fieldMapping]);
        
        $result = $this->entityHydrator->hydrateMultiple($dataArray, $entityClass, false);
        
        $this->assertCount(2, $result);
        $this->assertSame($entity1, $result[0]);
        $this->assertSame($entity2, $result[1]);
    }

    public function testExtractEntityData(): void
    {
        $entity = new TestEntity();

        $fieldMapping = $this->createMock(FieldMappingInterface::class);
        $fieldMapping->method('getFieldName')->willReturn('name');

        $this->metadataFactory->expects($this->once())
            ->method('getMetadataFor')
            ->with(TestEntity::class)
            ->willReturn($this->metadata);

        $this->metadata->expects($this->once())
            ->method('getFieldMappings')
            ->willReturn([$fieldMapping]);

        $this->metadata->expects($this->once())
            ->method('getFieldValue')
            ->with($entity, 'name')
            ->willReturn('Test Value');

        $result = $this->entityHydrator->extractEntityData($entity);

        $this->assertEquals(['name' => 'Test Value'], $result);
    }
}

// Test entity classes
class TestEntity
{
    public ?int $id = null;
    public ?string $name = null;
}

class TestChildEntity extends TestEntity
{
    public ?string $childProperty = null;
}
