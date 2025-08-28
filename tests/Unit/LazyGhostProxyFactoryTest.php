<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit;

use Fduarte42\Aurum\Exception\ORMException;
use Fduarte42\Aurum\Proxy\LazyGhostProxyFactory;
use Fduarte42\Aurum\Tests\Fixtures\User;
use Fduarte42\Aurum\Connection\ConnectionInterface;
use Fduarte42\Aurum\Metadata\MetadataFactory;
use Fduarte42\Aurum\Metadata\EntityMetadataInterface;
use Fduarte42\Aurum\Metadata\FieldMappingInterface;
use PHPUnit\Framework\TestCase;

class LazyGhostProxyFactoryTest extends TestCase
{
    private LazyGhostProxyFactory $proxyFactory;
    private ConnectionInterface $connection;
    private MetadataFactory $metadataFactory;
    private EntityMetadataInterface $metadata;

    protected function setUp(): void
    {
        // Create mock dependencies
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->metadataFactory = $this->createMock(MetadataFactory::class);
        $this->metadata = $this->createMock(EntityMetadataInterface::class);

        $this->proxyFactory = new LazyGhostProxyFactory($this->connection, $this->metadataFactory);
    }

    public function testCreateProxy(): void
    {
        // Skip test if LazyGhost is not available
        if (!class_exists('\LazyGhost')) {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('LazyGhost is not available. PHP 8.4+ is required for optimized proxy support.');
        }

        $userId = 'test-id';

        // Setup metadata mocks
        $this->setupMetadataMocks();

        $proxy = $this->proxyFactory->createProxy(User::class, $userId, fn() => null);

        // Only run assertions if LazyGhost is available
        if (class_exists('\LazyGhost')) {
            $this->assertInstanceOf(User::class, $proxy);
            $this->assertTrue($this->proxyFactory->isProxy($proxy));
            $this->assertFalse($this->proxyFactory->isProxyInitialized($proxy)); // Should not be initialized yet
            $this->assertEquals($userId, $this->proxyFactory->getProxyIdentifier($proxy));
        }
    }

    public function testCreateProxyWithInvalidClass(): void
    {
        $this->expectException(ORMException::class);
        $this->expectExceptionMessage('Class "NonexistentClass" is not a valid entity class');

        $this->proxyFactory->createProxy('NonexistentClass', 'id', fn() => null);
    }

    public function testCreateProxyWithoutLazyGhost(): void
    {
        // Test the case where LazyGhost is not available
        if (class_exists('\LazyGhost')) {
            $this->markTestSkipped('LazyGhost is available, cannot test fallback behavior');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('LazyGhost is not available. PHP 8.4+ is required for optimized proxy support.');

        $this->proxyFactory->createProxy(User::class, 'test-id', fn() => null);
    }

    public function testProxyInitialization(): void
    {
        // Skip test if LazyGhost is not available
        if (!class_exists('\LazyGhost')) {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('LazyGhost is not available. PHP 8.4+ is required for optimized proxy support.');
        }

        $userId = 'test-id';

        // Setup metadata and database mocks
        $this->setupMetadataMocks();
        $this->setupDatabaseMocks($userId);

        $proxy = $this->proxyFactory->createProxy(User::class, $userId, fn() => null);

        // Only run assertions if LazyGhost is available
        if (class_exists('\LazyGhost')) {
            $this->assertFalse($this->proxyFactory->isProxyInitialized($proxy));

            // Initialize the proxy
            $this->proxyFactory->initializeProxy($proxy);

            $this->assertTrue($this->proxyFactory->isProxyInitialized($proxy));

            // Now we can access properties - they should be loaded from database
            $name = $proxy->getName();
            $this->assertEquals('Test User', $name);
        }
    }

    public function testProxyInitializationWithMissingEntity(): void
    {
        // Skip test if LazyGhost is not available
        if (!class_exists('\LazyGhost')) {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('LazyGhost is not available. PHP 8.4+ is required for optimized proxy support.');
        }

        $userId = 'nonexistent-id';

        // Setup metadata mocks
        $this->setupMetadataMocks();

        // Setup database to return null (entity not found)
        $this->connection->method('quoteIdentifier')->willReturnArgument(0);
        $this->connection->method('fetchOne')->willReturn(null);

        $proxy = $this->proxyFactory->createProxy(User::class, $userId, fn() => null);

        // Only run assertions if LazyGhost is available
        if (class_exists('\LazyGhost')) {
            $this->expectException(ORMException::class);
            $this->expectExceptionMessage('Entity of type "Fduarte42\Aurum\Tests\Fixtures\User" with identifier "nonexistent-id" not found');

            // This should trigger initialization and throw an exception
            $this->proxyFactory->initializeProxy($proxy);
        }
    }

    public function testIsProxy(): void
    {
        // Skip test if LazyGhost is not available
        if (!class_exists('\LazyGhost')) {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('LazyGhost is not available. PHP 8.4+ is required for optimized proxy support.');
        }

        $this->setupMetadataMocks();

        $proxy = $this->proxyFactory->createProxy(User::class, 'test-id', fn() => null);
        $regularUser = new User('test@example.com', 'Test User');

        // Only run assertions if LazyGhost is available
        if (class_exists('\LazyGhost')) {
            $this->assertTrue($this->proxyFactory->isProxy($proxy));
            $this->assertFalse($this->proxyFactory->isProxy($regularUser));
        }
    }

    public function testGetRealClass(): void
    {
        // Skip test if LazyGhost is not available
        if (!class_exists('\LazyGhost')) {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('LazyGhost is not available. PHP 8.4+ is required for optimized proxy support.');
        }

        $this->setupMetadataMocks();

        $proxy = $this->proxyFactory->createProxy(User::class, 'test-id', fn() => null);
        $regularUser = new User('test@example.com', 'Test User');

        // Only run assertions if LazyGhost is available
        if (class_exists('\LazyGhost')) {
            $this->assertEquals(User::class, $this->proxyFactory->getRealClass($proxy));
            $this->assertEquals(User::class, $this->proxyFactory->getRealClass($regularUser));
            $this->assertEquals(User::class, $this->proxyFactory->getRealClass(User::class));
        }
    }

    public function testInitializeNonProxy(): void
    {
        $user = new User('test@example.com', 'Test User');

        // Should not throw exception
        $this->proxyFactory->initializeProxy($user);
        $this->assertTrue(true);
    }

    public function testGetProxyIdentifierWithNonProxy(): void
    {
        $user = new User('test@example.com', 'Test User');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Object is not a proxy');

        $this->proxyFactory->getProxyIdentifier($user);
    }

    public function testCreateCollectionProxy(): void
    {
        $data = ['item1', 'item2', 'item3'];
        $initializerCalled = false;

        $initializer = function() use (&$initializerCalled, $data) {
            $initializerCalled = true;
            return $data;
        };

        $collectionProxy = $this->proxyFactory->createCollectionProxy($initializer);

        $this->assertInstanceOf(\ArrayObject::class, $collectionProxy);
        $this->assertFalse($initializerCalled); // Should not be initialized yet

        // Access should trigger initialization
        $count = $collectionProxy->count();
        $this->assertTrue($initializerCalled);
        $this->assertEquals(3, $count);
    }

    /**
     * Setup metadata mocks for testing
     */
    private function setupMetadataMocks(): void
    {
        $this->metadataFactory->method('getMetadataFor')
            ->with(User::class)
            ->willReturn($this->metadata);

        $this->metadata->method('getTableName')->willReturn('users');
        $this->metadata->method('getIdentifierColumnName')->willReturn('id');
        $this->metadata->method('getIdentifierFieldName')->willReturn('id');
        $this->metadata->method('isIdentifier')->willReturnCallback(fn($field) => $field === 'id');

        // Setup field mappings
        $idMapping = $this->createMock(FieldMappingInterface::class);
        $idMapping->method('getFieldName')->willReturn('id');
        $idMapping->method('getColumnName')->willReturn('id');
        $idMapping->method('isIdentifier')->willReturn(true);

        $nameMapping = $this->createMock(FieldMappingInterface::class);
        $nameMapping->method('getFieldName')->willReturn('name');
        $nameMapping->method('getColumnName')->willReturn('name');
        $nameMapping->method('isIdentifier')->willReturn(false);

        $emailMapping = $this->createMock(FieldMappingInterface::class);
        $emailMapping->method('getFieldName')->willReturn('email');
        $emailMapping->method('getColumnName')->willReturn('email');
        $emailMapping->method('isIdentifier')->willReturn(false);

        $this->metadata->method('getFieldMappings')->willReturn([
            'id' => $idMapping,
            'name' => $nameMapping,
            'email' => $emailMapping,
        ]);

        $this->metadata->method('setFieldValue')->willReturnCallback(
            function ($entity, $field, $value) {
                $reflection = new \ReflectionClass($entity);
                $property = $reflection->getProperty($field);
                $property->setAccessible(true);
                $property->setValue($entity, $value);
            }
        );
    }

    /**
     * Setup database mocks for testing
     */
    private function setupDatabaseMocks(string $userId): void
    {
        $this->connection->method('quoteIdentifier')->willReturnArgument(0);
        $this->connection->method('fetchOne')->willReturn([
            'id' => $userId,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
