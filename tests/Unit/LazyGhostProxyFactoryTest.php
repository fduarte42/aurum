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
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

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
        // Skip test if lazy ghost functionality is not available
        if (!$this->isLazyGhostSupported()) {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Lazy ghost functionality is not available. PHP 8.4+ is required for optimized proxy support.');
        }

        $userId = Uuid::uuid4();

        // Create a test proxy factory that doesn't try to initialize immediately
        $testProxyFactory = new TestLazyGhostProxyFactory();

        $proxy = $testProxyFactory->createProxy(User::class, $userId, fn() => null);

        // Only run assertions if lazy ghost functionality is available
        if ($this->isLazyGhostSupported()) {
            $this->assertInstanceOf(User::class, $proxy);
            $this->assertTrue($testProxyFactory->isProxy($proxy));
            $this->assertFalse($testProxyFactory->isProxyInitialized($proxy)); // Should not be initialized yet
            $this->assertEquals($userId, $testProxyFactory->getProxyIdentifier($proxy));
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
        // Test the case where lazy ghost functionality is not available
        if ($this->isLazyGhostSupported()) {
            $this->markTestSkipped('Lazy ghost functionality is available, cannot test fallback behavior');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Lazy ghost functionality is not available. PHP 8.4+ is required for optimized proxy support.');

        $this->proxyFactory->createProxy(User::class, 'test-id', fn() => null);
    }

    public function testProxyInitialization(): void
    {
        // Skip test if lazy ghost functionality is not available
        if (!$this->isLazyGhostSupported()) {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Lazy ghost functionality is not available. PHP 8.4+ is required for optimized proxy support.');
        }

        $userId = Uuid::uuid4();

        // Create a test proxy factory that tracks initialization
        $testProxyFactory = new TestLazyGhostProxyFactory();

        $proxy = $testProxyFactory->createProxy(User::class, $userId, fn() => null);

        // Only run assertions if lazy ghost functionality is available
        if ($this->isLazyGhostSupported()) {
            // Initially, the proxy should not be initialized
            $this->assertFalse($testProxyFactory->isProxyInitialized($proxy));
            $this->assertFalse($testProxyFactory->initializationCalled);

            // Initialize the proxy
            $testProxyFactory->initializeProxy($proxy);

            $this->assertTrue($testProxyFactory->isProxyInitialized($proxy));
            $this->assertTrue($testProxyFactory->initializationCalled);
            $this->assertEquals(User::class, $testProxyFactory->initializationData['className']);
            $this->assertEquals($userId, $testProxyFactory->initializationData['identifier']);
        }
    }

    public function testProxyInitializationWithMissingEntity(): void
    {
        // Skip test if lazy ghost functionality is not available
        if (!$this->isLazyGhostSupported()) {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Lazy ghost functionality is not available. PHP 8.4+ is required for optimized proxy support.');
        }

        $userId = Uuid::uuid4();

        // Create a test proxy factory that simulates missing entity
        $testProxyFactory = new class extends TestLazyGhostProxyFactory {
            protected function initializeProxyDirectly(object $proxy, string $className, mixed $identifier): void
            {
                parent::initializeProxyDirectly($proxy, $className, $identifier);
                // Simulate entity not found
                throw ORMException::entityNotFound($className, $identifier);
            }
        };

        $proxy = $testProxyFactory->createProxy(User::class, $userId, fn() => null);

        // Only run assertions if lazy ghost functionality is available
        if ($this->isLazyGhostSupported()) {
            $this->expectException(ORMException::class);
            $this->expectExceptionMessage('Entity of type "Fduarte42\Aurum\Tests\Fixtures\User" with identifier "' . $userId->toString() . '" not found');

            // This should trigger initialization and throw an exception
            $testProxyFactory->initializeProxy($proxy);
        }
    }

    public function testIsProxy(): void
    {
        // Skip test if lazy ghost functionality is not available
        if (!$this->isLazyGhostSupported()) {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Lazy ghost functionality is not available. PHP 8.4+ is required for optimized proxy support.');
        }

        // Create a test proxy factory that doesn't initialize immediately
        $testProxyFactory = new TestLazyGhostProxyFactory();

        $proxy = $testProxyFactory->createProxy(User::class, Uuid::uuid4(), fn() => null);
        $regularUser = new User('test@example.com', 'Test User');

        // Only run assertions if lazy ghost functionality is available
        if ($this->isLazyGhostSupported()) {
            $this->assertTrue($testProxyFactory->isProxy($proxy));
            $this->assertFalse($testProxyFactory->isProxy($regularUser));
        }
    }

    public function testGetRealClass(): void
    {
        // Skip test if lazy ghost functionality is not available
        if (!$this->isLazyGhostSupported()) {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Lazy ghost functionality is not available. PHP 8.4+ is required for optimized proxy support.');
        }

        // Create a test proxy factory that doesn't initialize immediately
        $testProxyFactory = new TestLazyGhostProxyFactory();

        $proxy = $testProxyFactory->createProxy(User::class, Uuid::uuid4(), fn() => null);
        $regularUser = new User('test@example.com', 'Test User');

        // Only run assertions if lazy ghost functionality is available
        if ($this->isLazyGhostSupported()) {
            $this->assertEquals(User::class, $testProxyFactory->getRealClass($proxy));
            $this->assertEquals(User::class, $testProxyFactory->getRealClass($regularUser));
            $this->assertEquals(User::class, $testProxyFactory->getRealClass(User::class));
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

        $createdAtMapping = $this->createMock(FieldMappingInterface::class);
        $createdAtMapping->method('getFieldName')->willReturn('createdAt');
        $createdAtMapping->method('getColumnName')->willReturn('created_at');
        $createdAtMapping->method('isIdentifier')->willReturn(false);

        $this->metadata->method('getFieldMappings')->willReturn([
            'id' => $idMapping,
            'name' => $nameMapping,
            'email' => $emailMapping,
            'createdAt' => $createdAtMapping,
        ]);

        $this->metadata->method('setFieldValue')->willReturnCallback(
            function ($entity, $field, $value) {
                $reflection = new \ReflectionClass($entity);
                $property = $reflection->getProperty($field);
                $property->setAccessible(true);

                // Handle UUID conversion for id field
                if ($field === 'id' && is_string($value)) {
                    $value = Uuid::fromString($value);
                }

                // Handle DateTime conversion for createdAt field
                if ($field === 'createdAt' && is_string($value)) {
                    $value = new \DateTimeImmutable($value);
                }

                $property->setValue($entity, $value);
            }
        );
    }

    /**
     * Setup database mocks for testing
     */
    private function setupDatabaseMocks(UuidInterface $userId): void
    {
        $this->connection->method('quoteIdentifier')->willReturnArgument(0);
        $this->connection->method('fetchOne')->willReturn([
            'id' => $userId->toString(),
            'name' => 'Test User',
            'email' => 'test@example.com',
            'created_at' => '2023-01-01 12:00:00',
        ]);
    }

    /**
     * Check if lazy ghost functionality is supported
     */
    private function isLazyGhostSupported(): bool
    {
        // Check if PHP version supports lazy ghost (8.4+)
        if (!version_compare(PHP_VERSION, '8.4.0', '>=')) {
            return false;
        }

        // Check if the newLazyGhost method exists on ReflectionClass
        return method_exists(\ReflectionClass::class, 'newLazyGhost');
    }
}

/**
 * Test helper class that extends LazyGhostProxyFactory for testing purposes
 */
class TestLazyGhostProxyFactory extends LazyGhostProxyFactory
{
    public $initializationCalled = false;
    public $initializationData = [];
    private $initializedProxies = [];

    protected function initializeProxyDirectly(object $proxy, string $className, mixed $identifier): void
    {
        $this->initializationCalled = true;
        $this->initializationData = ['className' => $className, 'identifier' => $identifier];
        $this->initializedProxies[spl_object_id($proxy)] = true;

        // Simulate setting some properties to mark as initialized
        $proxy->name = 'Test User';
        $proxy->email = 'test@example.com';
    }

    protected function setIdentifierOnProxy(object $proxy, string $className, mixed $identifier): void
    {
        // Override to prevent immediate initialization during ID setting
        // Don't call parent method to avoid triggering lazy loading
    }

    public function isProxyInitialized(object $proxy): bool
    {
        if (!$this->isProxy($proxy)) {
            return true;
        }

        // Use our custom tracking instead of property inspection
        return isset($this->initializedProxies[spl_object_id($proxy)]);
    }
}
