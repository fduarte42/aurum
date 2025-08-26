<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit;

use Fduarte42\Aurum\Connection\ConnectionInterface;
use Fduarte42\Aurum\DependencyInjection\ContainerBuilder;
use Fduarte42\Aurum\DependencyInjection\ORMServiceProvider;
use Fduarte42\Aurum\DependencyInjection\SimpleContainer;
use Fduarte42\Aurum\EntityManager;
use Fduarte42\Aurum\EntityManagerInterface;
use Fduarte42\Aurum\Metadata\MetadataFactory;
use Fduarte42\Aurum\Proxy\ProxyFactoryInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\NotFoundExceptionInterface;

class DependencyInjectionTest extends TestCase
{
    public function testContainerBuilderBasicUsage(): void
    {
        $builder = new ContainerBuilder();
        
        $builder->set('test_service', 'test_value');
        
        $this->assertTrue($builder->has('test_service'));
        $this->assertEquals('test_value', $builder->get('test_service'));
    }

    public function testContainerBuilderWithCallable(): void
    {
        $builder = new ContainerBuilder();
        
        $builder->set('test_service', function() {
            return 'computed_value';
        });
        
        $result1 = $builder->get('test_service');
        $result2 = $builder->get('test_service');
        
        $this->assertEquals('computed_value', $result1);
        $this->assertEquals('computed_value', $result2);
        $this->assertSame($result1, $result2); // Should be cached
    }

    public function testContainerBuilderGetNonexistentService(): void
    {
        $builder = new ContainerBuilder();
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Service 'nonexistent' not found");
        
        $builder->get('nonexistent');
    }

    public function testContainerBuilderHasService(): void
    {
        $builder = new ContainerBuilder();
        
        $this->assertFalse($builder->has('nonexistent'));
        
        $builder->set('existing', 'value');
        $this->assertTrue($builder->has('existing'));
    }

    public function testContainerBuilderSetService(): void
    {
        $builder = new ContainerBuilder();
        
        $builder->setService('test', 'value');
        $this->assertEquals('value', $builder->get('test'));
    }

    public function testContainerBuilderBuild(): void
    {
        $builder = new ContainerBuilder();
        $builder->set('test_service', 'test_value');
        
        $container = $builder->build();
        
        $this->assertInstanceOf(SimpleContainer::class, $container);
        $this->assertTrue($container->has('test_service'));
        $this->assertEquals('test_value', $container->get('test_service'));
    }

    public function testCreateORM(): void
    {
        $config = [
            'connection' => [
                'driver' => 'sqlite',
                'path' => ':memory:'
            ]
        ];
        
        $container = ContainerBuilder::createORM($config);
        
        $this->assertTrue($container->has(ConnectionInterface::class));
        $this->assertTrue($container->has(EntityManagerInterface::class));
        $this->assertTrue($container->has(MetadataFactory::class));
        $this->assertTrue($container->has(ProxyFactoryInterface::class));
    }

    public function testCreateEntityManager(): void
    {
        $config = [
            'connection' => [
                'driver' => 'sqlite',
                'path' => ':memory:'
            ]
        ];
        
        $entityManager = ContainerBuilder::createEntityManager($config);
        
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->assertInstanceOf(EntityManager::class, $entityManager);
    }

    public function testSimpleContainer(): void
    {
        $services = [
            'service1' => 'value1',
            'service2' => function() { return 'computed'; }
        ];
        
        $container = new SimpleContainer($services);
        
        $this->assertTrue($container->has('service1'));
        $this->assertTrue($container->has('service2'));
        $this->assertFalse($container->has('nonexistent'));
        
        $this->assertEquals('value1', $container->get('service1'));
        $this->assertEquals('computed', $container->get('service2'));
    }

    public function testSimpleContainerGetNonexistent(): void
    {
        $container = new SimpleContainer();
        
        $this->expectException(NotFoundExceptionInterface::class);
        $this->expectExceptionMessage("Service 'nonexistent' not found");
        
        $container->get('nonexistent');
    }

    public function testSimpleContainerSet(): void
    {
        $container = new SimpleContainer();
        
        $container->set('new_service', 'new_value');
        
        $this->assertTrue($container->has('new_service'));
        $this->assertEquals('new_value', $container->get('new_service'));
    }

    public function testORMServiceProvider(): void
    {
        $config = [
            'connection' => [
                'driver' => 'sqlite',
                'path' => ':memory:'
            ]
        ];
        
        $provider = new ORMServiceProvider($config);
        $container = new SimpleContainer();
        
        $provider->register($container);
        
        $providedServices = $provider->getProvidedServices();
        
        $this->assertContains(ConnectionInterface::class, $providedServices);
        $this->assertContains(EntityManagerInterface::class, $providedServices);
        $this->assertContains(MetadataFactory::class, $providedServices);
        $this->assertContains(ProxyFactoryInterface::class, $providedServices);
        $this->assertContains(EntityManager::class, $providedServices);
    }

    public function testORMServiceProviderRegistration(): void
    {
        $config = [
            'connection' => [
                'driver' => 'sqlite',
                'path' => ':memory:'
            ]
        ];
        
        $provider = new ORMServiceProvider($config);
        $container = new SimpleContainer();
        
        $provider->register($container);
        
        // Test that services are registered and can be retrieved
        $this->assertTrue($container->has(ConnectionInterface::class));
        $this->assertTrue($container->has(EntityManagerInterface::class));
        
        $connection = $container->get(ConnectionInterface::class);
        $this->assertInstanceOf(ConnectionInterface::class, $connection);
        
        $entityManager = $container->get(EntityManagerInterface::class);
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);
    }

    public function testContainerBuilderAddServiceProvider(): void
    {
        $config = [
            'connection' => [
                'driver' => 'sqlite',
                'path' => ':memory:'
            ]
        ];
        
        $builder = new ContainerBuilder($config);
        $provider = new ORMServiceProvider($config);
        
        $builder->addServiceProvider($provider);
        
        $this->assertTrue($builder->has(ConnectionInterface::class));
        $this->assertTrue($builder->has(EntityManagerInterface::class));
    }

    public function testORMServiceProviderWithDifferentConfig(): void
    {
        $config = [
            'connection' => [
                'driver' => 'sqlite',
                'path' => ':memory:'
            ],
            'metadata' => [
                'cache' => true
            ]
        ];

        $provider = new ORMServiceProvider($config);
        $container = new SimpleContainer();

        $provider->register($container);

        // Test that services are registered with custom config
        $this->assertTrue($container->has(ConnectionInterface::class));
        $this->assertTrue($container->has(EntityManagerInterface::class));
    }

    public function testORMServiceProviderGetProvidedServicesComplete(): void
    {
        $config = [
            'connection' => [
                'driver' => 'sqlite',
                'path' => ':memory:'
            ]
        ];

        $provider = new ORMServiceProvider($config);
        $providedServices = $provider->getProvidedServices();

        // Verify all expected services are provided
        $expectedServices = [
            ConnectionInterface::class,
            EntityManagerInterface::class,
            MetadataFactory::class,
            ProxyFactoryInterface::class,
            EntityManager::class
        ];

        foreach ($expectedServices as $service) {
            $this->assertContains($service, $providedServices);
        }
    }

    public function testORMServiceProviderRegisterConnection(): void
    {
        $config = [
            'connection' => [
                'driver' => 'sqlite',
                'path' => ':memory:'
            ]
        ];

        $provider = new ORMServiceProvider($config);
        $container = new SimpleContainer();

        // Use reflection to test private registerConnection method
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('registerConnection');
        $method->setAccessible(true);

        $method->invoke($provider, $container);

        $this->assertTrue($container->has(ConnectionInterface::class));
    }

    public function testORMServiceProviderRegisterMetadataFactory(): void
    {
        $config = [];
        $provider = new ORMServiceProvider($config);
        $container = new SimpleContainer();

        // First register the type system (dependency of metadata factory)
        $reflection = new \ReflectionClass($provider);
        $typeSystemMethod = $reflection->getMethod('registerTypeSystem');
        $typeSystemMethod->setAccessible(true);
        $typeSystemMethod->invoke($provider, $container);

        // Then register metadata factory
        $method = $reflection->getMethod('registerMetadataFactory');
        $method->setAccessible(true);
        $method->invoke($provider, $container);

        $this->assertTrue($container->has(MetadataFactory::class));
    }

    public function testORMServiceProviderRegisterProxyFactory(): void
    {
        $config = [];
        $provider = new ORMServiceProvider($config);
        $container = new SimpleContainer();

        // Use reflection to test private registerProxyFactory method
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('registerProxyFactory');
        $method->setAccessible(true);

        $method->invoke($provider, $container);

        $this->assertTrue($container->has(ProxyFactoryInterface::class));
    }

    public function testORMServiceProviderRegisterEntityManager(): void
    {
        $config = [
            'connection' => [
                'driver' => 'sqlite',
                'path' => ':memory:'
            ]
        ];

        $provider = new ORMServiceProvider($config);
        $container = new SimpleContainer();

        // First register dependencies
        $reflection = new \ReflectionClass($provider);

        $registerConnection = $reflection->getMethod('registerConnection');
        $registerConnection->setAccessible(true);
        $registerConnection->invoke($provider, $container);

        $registerTypeSystem = $reflection->getMethod('registerTypeSystem');
        $registerTypeSystem->setAccessible(true);
        $registerTypeSystem->invoke($provider, $container);

        $registerMetadata = $reflection->getMethod('registerMetadataFactory');
        $registerMetadata->setAccessible(true);
        $registerMetadata->invoke($provider, $container);

        $registerProxy = $reflection->getMethod('registerProxyFactory');
        $registerProxy->setAccessible(true);
        $registerProxy->invoke($provider, $container);

        // Now test registerEntityManager
        $registerEM = $reflection->getMethod('registerEntityManager');
        $registerEM->setAccessible(true);
        $registerEM->invoke($provider, $container);

        $this->assertTrue($container->has(EntityManagerInterface::class));
        $this->assertTrue($container->has(EntityManager::class));
    }

    public function testORMServiceProviderWithMockLaravelContainer(): void
    {
        $config = [
            'connection' => [
                'driver' => 'sqlite',
                'path' => ':memory:'
            ]
        ];

        $provider = new ORMServiceProvider($config);

        // Create a mock container that has bind method (Laravel-style)
        $container = new class implements \Psr\Container\ContainerInterface {
            private array $bindings = [];
            private array $instances = [];

            public function bind(string $abstract, $concrete): void
            {
                $this->bindings[$abstract] = $concrete;
            }

            public function make(string $abstract)
            {
                if (isset($this->instances[$abstract])) {
                    return $this->instances[$abstract];
                }

                if (isset($this->bindings[$abstract])) {
                    $concrete = $this->bindings[$abstract];
                    if (is_callable($concrete)) {
                        $instance = $concrete($this);
                    } else {
                        $instance = new $concrete();
                    }
                    $this->instances[$abstract] = $instance;
                    return $instance;
                }

                throw new \Exception("Service {$abstract} not found");
            }

            public function get(string $id)
            {
                return $this->make($id);
            }

            public function has(string $id): bool
            {
                return isset($this->bindings[$id]) || isset($this->instances[$id]);
            }
        };

        // Test that the provider works with Laravel-style container
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('registerConnection');
        $method->setAccessible(true);
        $method->invoke($provider, $container);

        $this->assertTrue($container->has(ConnectionInterface::class));
    }

    public function testORMServiceProviderWithGenericContainer(): void
    {
        $config = [
            'connection' => [
                'driver' => 'sqlite',
                'path' => ':memory:'
            ]
        ];

        $provider = new ORMServiceProvider($config);
        $container = new SimpleContainer();

        // Test that the provider works with generic container
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('registerConnection');
        $method->setAccessible(true);
        $method->invoke($provider, $container);

        $this->assertTrue($container->has(ConnectionInterface::class));
    }

    public function testORMServiceProviderConstructor(): void
    {
        // Test constructor with empty config
        $provider = new ORMServiceProvider();
        $this->assertInstanceOf(ORMServiceProvider::class, $provider);

        // Test constructor with config
        $config = ['connection' => ['driver' => 'sqlite']];
        $provider = new ORMServiceProvider($config);
        $this->assertInstanceOf(ORMServiceProvider::class, $provider);
    }

    public function testORMServiceProviderPrivateMethodsExist(): void
    {
        // Test that all private methods exist and can be called via reflection
        $provider = new ORMServiceProvider();
        $reflection = new \ReflectionClass($provider);

        // Test that all private registration methods exist
        $this->assertTrue($reflection->hasMethod('registerConnection'));
        $this->assertTrue($reflection->hasMethod('registerMetadataFactory'));
        $this->assertTrue($reflection->hasMethod('registerProxyFactory'));
        $this->assertTrue($reflection->hasMethod('registerEntityManager'));

        // Test that methods are private
        $this->assertTrue($reflection->getMethod('registerConnection')->isPrivate());
        $this->assertTrue($reflection->getMethod('registerMetadataFactory')->isPrivate());
        $this->assertTrue($reflection->getMethod('registerProxyFactory')->isPrivate());
        $this->assertTrue($reflection->getMethod('registerEntityManager')->isPrivate());
    }

    public function testORMServiceProviderWithContainerBuilder(): void
    {
        // Test that the provider works with ContainerBuilder (which has set method)
        $config = [
            'connection' => [
                'driver' => 'sqlite',
                'path' => ':memory:'
            ]
        ];

        $provider = new ORMServiceProvider($config);
        $container = new ContainerBuilder();

        // Use reflection to test private methods with ContainerBuilder
        $reflection = new \ReflectionClass($provider);

        $registerConnection = $reflection->getMethod('registerConnection');
        $registerConnection->setAccessible(true);
        $registerConnection->invoke($provider, $container);

        $registerTypeSystem = $reflection->getMethod('registerTypeSystem');
        $registerTypeSystem->setAccessible(true);
        $registerTypeSystem->invoke($provider, $container);

        $registerMetadata = $reflection->getMethod('registerMetadataFactory');
        $registerMetadata->setAccessible(true);
        $registerMetadata->invoke($provider, $container);

        $registerProxy = $reflection->getMethod('registerProxyFactory');
        $registerProxy->setAccessible(true);
        $registerProxy->invoke($provider, $container);

        $registerEM = $reflection->getMethod('registerEntityManager');
        $registerEM->setAccessible(true);
        $registerEM->invoke($provider, $container);

        // Verify services were registered
        $this->assertTrue($container->has(ConnectionInterface::class));
        $this->assertTrue($container->has(MetadataFactory::class));
        $this->assertTrue($container->has(ProxyFactoryInterface::class));
        $this->assertTrue($container->has(EntityManagerInterface::class));
        $this->assertTrue($container->has(EntityManager::class));
    }

    public function testORMServiceProviderRegisterWithLaravelStyleContainer(): void
    {
        // Test with a container that has bind method (Laravel-style)
        $container = new class implements \Psr\Container\ContainerInterface {
            private array $bindings = [];

            public function bind(string $abstract, $concrete): void
            {
                $this->bindings[$abstract] = $concrete;
            }

            public function get(string $id)
            {
                return $this->bindings[$id] ?? null;
            }

            public function has(string $id): bool
            {
                return isset($this->bindings[$id]);
            }
        };

        $provider = new ORMServiceProvider();

        // Use reflection to test private methods with Laravel-style container
        $reflection = new \ReflectionClass($provider);

        $registerMetadata = $reflection->getMethod('registerMetadataFactory');
        $registerMetadata->setAccessible(true);
        $registerMetadata->invoke($provider, $container);

        $registerProxy = $reflection->getMethod('registerProxyFactory');
        $registerProxy->setAccessible(true);
        $registerProxy->invoke($provider, $container);

        $this->assertTrue($container->has(MetadataFactory::class));
        $this->assertTrue($container->has(ProxyFactoryInterface::class));
    }

    public function testORMServiceProviderRegisterWithGenericSetContainer(): void
    {
        // Test with a container that only has set method
        $container = new class implements \Psr\Container\ContainerInterface {
            private array $services = [];

            public function set(string $name, $value): void
            {
                $this->services[$name] = $value;
            }

            public function get(string $id)
            {
                return $this->services[$id] ?? null;
            }

            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }
        };

        $provider = new ORMServiceProvider();

        // Use reflection to test private methods with set-only container
        $reflection = new \ReflectionClass($provider);

        $registerMetadata = $reflection->getMethod('registerMetadataFactory');
        $registerMetadata->setAccessible(true);
        $registerMetadata->invoke($provider, $container);

        $registerProxy = $reflection->getMethod('registerProxyFactory');
        $registerProxy->setAccessible(true);
        $registerProxy->invoke($provider, $container);

        $this->assertTrue($container->has(MetadataFactory::class));
        $this->assertTrue($container->has(ProxyFactoryInterface::class));
    }

    public function testORMServiceProviderRegisterWithBasicContainer(): void
    {
        // Test with a basic container that has no special methods
        $container = new class implements \Psr\Container\ContainerInterface {
            public function get(string $id)
            {
                return null;
            }

            public function has(string $id): bool
            {
                return false;
            }
        };

        $provider = new ORMServiceProvider();

        // Use reflection to test private methods with basic container
        $reflection = new \ReflectionClass($provider);

        $registerMetadata = $reflection->getMethod('registerMetadataFactory');
        $registerMetadata->setAccessible(true);
        $registerMetadata->invoke($provider, $container);

        $registerProxy = $reflection->getMethod('registerProxyFactory');
        $registerProxy->setAccessible(true);
        $registerProxy->invoke($provider, $container);

        // These should not throw exceptions even with basic container
        $this->assertTrue(true);
    }

    public function testORMServiceProviderRegisterConnectionWithDIContainer(): void
    {
        // Test registerConnection with actual DI\Container (if available)
        $config = [
            'connection' => [
                'driver' => 'sqlite',
                'path' => ':memory:'
            ]
        ];

        $provider = new ORMServiceProvider($config);
        $container = new ContainerBuilder(); // This extends DI\Container

        // Use reflection to test private registerConnection method
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('registerConnection');
        $method->setAccessible(true);
        $method->invoke($provider, $container);

        $this->assertTrue($container->has(ConnectionInterface::class));
    }

    public function testORMServiceProviderRegisterMetadataFactoryWithDIContainer(): void
    {
        $provider = new ORMServiceProvider();
        $container = new ContainerBuilder(); // This extends DI\Container

        // First register the type system (dependency of metadata factory)
        $reflection = new \ReflectionClass($provider);
        $typeSystemMethod = $reflection->getMethod('registerTypeSystem');
        $typeSystemMethod->setAccessible(true);
        $typeSystemMethod->invoke($provider, $container);

        // Then register metadata factory
        $method = $reflection->getMethod('registerMetadataFactory');
        $method->setAccessible(true);
        $method->invoke($provider, $container);

        $this->assertTrue($container->has(MetadataFactory::class));
    }

    public function testORMServiceProviderRegisterProxyFactoryWithDIContainer(): void
    {
        $provider = new ORMServiceProvider();
        $container = new ContainerBuilder(); // This extends DI\Container

        // Use reflection to test private registerProxyFactory method
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('registerProxyFactory');
        $method->setAccessible(true);
        $method->invoke($provider, $container);

        $this->assertTrue($container->has(ProxyFactoryInterface::class));
    }

    public function testORMServiceProviderRegisterEntityManagerWithDIContainer(): void
    {
        $config = [
            'connection' => [
                'driver' => 'sqlite',
                'path' => ':memory:'
            ]
        ];

        $provider = new ORMServiceProvider($config);
        $container = new ContainerBuilder(); // This extends DI\Container

        // First register dependencies
        $reflection = new \ReflectionClass($provider);

        $registerConnection = $reflection->getMethod('registerConnection');
        $registerConnection->setAccessible(true);
        $registerConnection->invoke($provider, $container);

        $registerTypeSystem = $reflection->getMethod('registerTypeSystem');
        $registerTypeSystem->setAccessible(true);
        $registerTypeSystem->invoke($provider, $container);

        $registerMetadata = $reflection->getMethod('registerMetadataFactory');
        $registerMetadata->setAccessible(true);
        $registerMetadata->invoke($provider, $container);

        $registerProxy = $reflection->getMethod('registerProxyFactory');
        $registerProxy->setAccessible(true);
        $registerProxy->invoke($provider, $container);

        $registerEM = $reflection->getMethod('registerEntityManager');
        $registerEM->setAccessible(true);
        $registerEM->invoke($provider, $container);

        $this->assertTrue($container->has(EntityManagerInterface::class));
        $this->assertTrue($container->has(EntityManager::class));
    }

    public function testORMServiceProviderRegisterWithNoSpecialMethods(): void
    {
        // Test with a container that has no special methods (bind, set, etc.)
        $container = new class implements \Psr\Container\ContainerInterface {
            public function get(string $id)
            {
                throw new \Exception("Service not found: $id");
            }

            public function has(string $id): bool
            {
                return false;
            }
        };

        $provider = new ORMServiceProvider();

        // Use reflection to test private methods with container that has no special methods
        $reflection = new \ReflectionClass($provider);

        // These should not throw exceptions even though container has no special methods
        $registerMetadata = $reflection->getMethod('registerMetadataFactory');
        $registerMetadata->setAccessible(true);
        $registerMetadata->invoke($provider, $container);

        $registerProxy = $reflection->getMethod('registerProxyFactory');
        $registerProxy->setAccessible(true);
        $registerProxy->invoke($provider, $container);

        // Should complete without throwing exceptions
        $this->assertTrue(true);
    }

    public function testORMServiceProviderGetProvidedServicesMethod(): void
    {
        // Test getProvidedServices method directly
        $provider = new ORMServiceProvider();
        $services = $provider->getProvidedServices();

        $this->assertIsArray($services);
        $this->assertCount(7, $services);
        $this->assertContains(ConnectionInterface::class, $services);
        $this->assertContains(MetadataFactory::class, $services);
        $this->assertContains(ProxyFactoryInterface::class, $services);
        $this->assertContains(EntityManagerInterface::class, $services);
        $this->assertContains(EntityManager::class, $services);
    }

    public function testORMServiceProviderRegisterMethodDirectly(): void
    {
        // Test the register method directly
        $config = [
            'connection' => [
                'driver' => 'sqlite',
                'path' => ':memory:'
            ]
        ];

        $provider = new ORMServiceProvider($config);
        $container = new SimpleContainer();

        // Call register method directly
        $provider->register($container);

        // Verify all services are registered
        $this->assertTrue($container->has(ConnectionInterface::class));
        $this->assertTrue($container->has(MetadataFactory::class));
        $this->assertTrue($container->has(ProxyFactoryInterface::class));
        $this->assertTrue($container->has(EntityManagerInterface::class));
        $this->assertTrue($container->has(EntityManager::class));
    }

    public function testORMServiceProviderRegisterConnectionWithNoSpecialMethods(): void
    {
        // Test the fallback case where container has no special methods
        $container = new class implements \Psr\Container\ContainerInterface {
            public function get(string $id) { return null; }
            public function has(string $id): bool { return false; }
        };

        $config = [
            'connection' => [
                'driver' => 'sqlite',
                'path' => ':memory:'
            ]
        ];

        $provider = new ORMServiceProvider($config);

        // Use reflection to test private registerConnection method
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('registerConnection');
        $method->setAccessible(true);

        // This should complete without error even though no registration happens
        $method->invoke($provider, $container);
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function testORMServiceProviderRegisterMetadataFactoryWithNoSpecialMethods(): void
    {
        // Test the fallback case where container has no special methods
        $container = new class implements \Psr\Container\ContainerInterface {
            public function get(string $id) { return null; }
            public function has(string $id): bool { return false; }
        };

        $provider = new ORMServiceProvider();

        // Use reflection to test private registerMetadataFactory method
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('registerMetadataFactory');
        $method->setAccessible(true);

        // This should complete without error even though no registration happens
        $method->invoke($provider, $container);
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function testORMServiceProviderRegisterProxyFactoryWithNoSpecialMethods(): void
    {
        // Test the fallback case where container has no special methods
        $container = new class implements \Psr\Container\ContainerInterface {
            public function get(string $id) { return null; }
            public function has(string $id): bool { return false; }
        };

        $provider = new ORMServiceProvider();

        // Use reflection to test private registerProxyFactory method
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('registerProxyFactory');
        $method->setAccessible(true);

        // This should complete without error even though no registration happens
        $method->invoke($provider, $container);
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function testORMServiceProviderRegisterEntityManagerWithNoSpecialMethods(): void
    {
        // Test the fallback case where container has no special methods
        $container = new class implements \Psr\Container\ContainerInterface {
            public function get(string $id) { return null; }
            public function has(string $id): bool { return false; }
        };

        $provider = new ORMServiceProvider();

        // Use reflection to test private registerEntityManager method
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('registerEntityManager');
        $method->setAccessible(true);

        // This should complete without error even though no registration happens
        $method->invoke($provider, $container);
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function testORMServiceProviderWithDIContainerInstanceof(): void
    {
        // Test the specific instanceof \DI\Container branch
        // Since ContainerBuilder extends \DI\Container, this tests that branch
        $config = [
            'connection' => [
                'driver' => 'sqlite',
                'path' => ':memory:'
            ]
        ];

        $provider = new ORMServiceProvider($config);
        $container = new ContainerBuilder(); // This is instanceof \DI\Container

        // Test all private methods with DI\Container
        $reflection = new \ReflectionClass($provider);

        $registerConnection = $reflection->getMethod('registerConnection');
        $registerConnection->setAccessible(true);
        $registerConnection->invoke($provider, $container);

        $registerTypeSystem = $reflection->getMethod('registerTypeSystem');
        $registerTypeSystem->setAccessible(true);
        $registerTypeSystem->invoke($provider, $container);

        $registerMetadata = $reflection->getMethod('registerMetadataFactory');
        $registerMetadata->setAccessible(true);
        $registerMetadata->invoke($provider, $container);

        $registerProxy = $reflection->getMethod('registerProxyFactory');
        $registerProxy->setAccessible(true);
        $registerProxy->invoke($provider, $container);

        $registerEM = $reflection->getMethod('registerEntityManager');
        $registerEM->setAccessible(true);
        $registerEM->invoke($provider, $container);

        // Verify all services were registered via DI\Container path
        $this->assertTrue($container->has(ConnectionInterface::class));
        $this->assertTrue($container->has(MetadataFactory::class));
        $this->assertTrue($container->has(ProxyFactoryInterface::class));
        $this->assertTrue($container->has(EntityManagerInterface::class));
        $this->assertTrue($container->has(EntityManager::class));
    }

    public function testORMServiceProviderConstructorWithEmptyConfig(): void
    {
        // Test constructor with no config (default empty array)
        $provider = new ORMServiceProvider();
        $this->assertInstanceOf(ORMServiceProvider::class, $provider);

        // Test that getProvidedServices works with empty config
        $services = $provider->getProvidedServices();
        $this->assertCount(7, $services);
    }

    public function testORMServiceProviderConstructorWithNullConfig(): void
    {
        // Test constructor with explicit empty array
        $provider = new ORMServiceProvider([]);
        $this->assertInstanceOf(ORMServiceProvider::class, $provider);

        // Test that getProvidedServices works
        $services = $provider->getProvidedServices();
        $this->assertIsArray($services);
        $this->assertCount(7, $services);
    }
}
