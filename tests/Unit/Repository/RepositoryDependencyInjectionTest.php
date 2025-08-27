<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit\Repository;

use Fduarte42\Aurum\EntityManagerInterface;
use Fduarte42\Aurum\Metadata\EntityMetadataInterface;
use Fduarte42\Aurum\Repository\Repository;
use Fduarte42\Aurum\Repository\RepositoryFactory;
use Fduarte42\Aurum\Repository\RepositoryInterface;
use Fduarte42\Aurum\Tests\Fixtures\Todo;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;

class RepositoryDependencyInjectionTest extends TestCase
{
    private EntityManagerInterface|MockObject $entityManager;
    private EntityMetadataInterface|MockObject $metadata;
    private ContainerInterface|MockObject $container;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->metadata = $this->createMock(EntityMetadataInterface::class);
        $this->container = $this->createMock(ContainerInterface::class);
    }

    public function testRepositoryWithManualDependencyInjection(): void
    {
        // Test manual dependency injection via setters
        $repository = new Repository();
        $repository->setClassName(Todo::class);
        $repository->setEntityManager($this->entityManager);
        $repository->setMetadata($this->metadata);

        $this->assertEquals(Todo::class, $repository->getClassName());
        $this->assertInstanceOf(RepositoryInterface::class, $repository);
    }

    public function testRepositoryWithDefaultConstructor(): void
    {
        // Test default constructor
        $repository = new Repository();
        
        // Dependencies should be injected via setters
        $repository->setClassName(Todo::class);
        $repository->setEntityManager($this->entityManager);
        $repository->setMetadata($this->metadata);
        
        $this->assertEquals(Todo::class, $repository->getClassName());
    }

    public function testRepositoryThrowsExceptionWhenDependenciesNotInjected(): void
    {
        $repository = new Repository();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Repository className not set');
        
        $repository->getClassName();
    }

    public function testRepositoryFactoryWithDefaultRepository(): void
    {
        $metadataFactory = $this->createMock(\Fduarte42\Aurum\Metadata\MetadataFactory::class);
        $metadataFactory->expects($this->once())
            ->method('getMetadataFor')
            ->with(Todo::class)
            ->willReturn($this->metadata);

        $this->entityManager->expects($this->any())
            ->method('getMetadataFactory')
            ->willReturn($metadataFactory);

        $factory = new RepositoryFactory($this->entityManager, $this->container);
        $repository = $factory->createRepository(Todo::class);

        $this->assertInstanceOf(Repository::class, $repository);
        $this->assertEquals(Todo::class, $repository->getClassName());
    }

    public function testRepositoryFactoryWithCustomRepository(): void
    {
        $metadataFactory = $this->createMock(\Fduarte42\Aurum\Metadata\MetadataFactory::class);
        $metadataFactory->expects($this->once())
            ->method('getMetadataFor')
            ->with(Todo::class)
            ->willReturn($this->metadata);

        $this->entityManager->expects($this->any())
            ->method('getMetadataFactory')
            ->willReturn($metadataFactory);

        $factory = new RepositoryFactory($this->entityManager, $this->container);
        $repository = $factory->createRepository(Todo::class, CustomTestRepository::class);

        $this->assertInstanceOf(CustomTestRepository::class, $repository);
        $this->assertEquals(Todo::class, $repository->getClassName());
    }

    public function testRepositoryFactoryWithCustomRepositoryWithCustomConstructor(): void
    {
        $metadataFactory = $this->createMock(\Fduarte42\Aurum\Metadata\MetadataFactory::class);
        $metadataFactory->expects($this->once())
            ->method('getMetadataFor')
            ->with(Todo::class)
            ->willReturn($this->metadata);

        $this->entityManager->expects($this->any())
            ->method('getMetadataFactory')
            ->willReturn($metadataFactory);

        // Mock container to provide custom dependency
        $customService = new \stdClass();
        $this->container->expects($this->once())
            ->method('has')
            ->with(\stdClass::class)
            ->willReturn(true);
        $this->container->expects($this->once())
            ->method('get')
            ->with(\stdClass::class)
            ->willReturn($customService);

        $factory = new RepositoryFactory($this->entityManager, $this->container);
        $repository = $factory->createRepository(Todo::class, CustomRepositoryWithDependency::class);

        $this->assertInstanceOf(CustomRepositoryWithDependency::class, $repository);
        $this->assertEquals(Todo::class, $repository->getClassName());
        $this->assertSame($customService, $repository->getCustomService());
    }

    public function testRepositoryFactoryWithRepositoryWithoutConstructor(): void
    {
        $metadataFactory = $this->createMock(\Fduarte42\Aurum\Metadata\MetadataFactory::class);
        $metadataFactory->expects($this->once())
            ->method('getMetadataFor')
            ->with(Todo::class)
            ->willReturn($this->metadata);

        $this->entityManager->expects($this->any())
            ->method('getMetadataFactory')
            ->willReturn($metadataFactory);

        $factory = new RepositoryFactory($this->entityManager, $this->container);
        $repository = $factory->createRepository(Todo::class, RepositoryWithoutConstructor::class);

        $this->assertInstanceOf(RepositoryWithoutConstructor::class, $repository);
        $this->assertEquals(Todo::class, $repository->getClassName());
    }

    public function testRepositorySetContainer(): void
    {
        $repository = new Repository();
        $repository->setContainer($this->container);
        
        // Container should be set (we can't directly test this without exposing the property,
        // but we can test that it doesn't throw an exception)
        $this->assertInstanceOf(Repository::class, $repository);
    }
}

/**
 * Test repository with default constructor for testing
 */
class CustomTestRepository extends Repository
{
    public function customMethod(): string
    {
        return 'custom';
    }
}

/**
 * Test repository with custom dependency
 */
class CustomRepositoryWithDependency extends Repository
{
    private \stdClass $customService;

    public function __construct(\stdClass $customService)
    {
        parent::__construct();
        $this->customService = $customService;
    }

    public function getCustomService(): \stdClass
    {
        return $this->customService;
    }
}

/**
 * Test repository without constructor
 */
class RepositoryWithoutConstructor extends Repository
{
    // No constructor defined
    
    public function specialMethod(): string
    {
        return 'special';
    }
}
