<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\DependencyInjection;

use Fduarte42\Aurum\Connection\ConnectionFactory;
use Fduarte42\Aurum\Connection\ConnectionInterface;
use Fduarte42\Aurum\EntityManager;
use Fduarte42\Aurum\EntityManagerInterface;
use Fduarte42\Aurum\Metadata\MetadataFactory;
use Fduarte42\Aurum\Proxy\LazyGhostProxyFactory;
use Fduarte42\Aurum\Proxy\ProxyFactoryInterface;
use Fduarte42\Aurum\Type\TypeRegistry;
use Fduarte42\Aurum\Type\TypeInference;
use Psr\Container\ContainerInterface;

/**
 * ORM service provider for registering ORM services
 */
class ORMServiceProvider implements ServiceProviderInterface
{
    public function __construct(
        private readonly array $config = []
    ) {
    }

    public function register(ContainerInterface $container): void
    {
        // Register connection
        $this->registerConnection($container);

        // Register type system
        $this->registerTypeSystem($container);

        // Register metadata factory
        $this->registerMetadataFactory($container);

        // Register proxy factory
        $this->registerProxyFactory($container);

        // Register entity manager
        $this->registerEntityManager($container);
    }

    public function getProvidedServices(): array
    {
        return [
            ConnectionInterface::class,
            TypeRegistry::class,
            TypeInference::class,
            MetadataFactory::class,
            ProxyFactoryInterface::class,
            EntityManagerInterface::class,
            EntityManager::class,
        ];
    }

    private function registerConnection(ContainerInterface $container): void
    {
        if ($container instanceof \DI\Container) {
            // PHP-DI container
            $container->set(ConnectionInterface::class, function () {
                return ConnectionFactory::createFromConfig($this->config['connection'] ?? []);
            });
        } elseif (method_exists($container, 'bind')) {
            // Laravel-style container
            $container->bind(ConnectionInterface::class, function () {
                return ConnectionFactory::createFromConfig($this->config['connection'] ?? []);
            });
        } elseif (method_exists($container, 'set')) {
            // Generic container with set method
            $container->set(ConnectionInterface::class, function () {
                return ConnectionFactory::createFromConfig($this->config['connection'] ?? []);
            });
        }
    }

    private function registerTypeSystem(ContainerInterface $container): void
    {
        if ($container instanceof \DI\Container) {
            $container->set(TypeRegistry::class, \DI\create(TypeRegistry::class));
            $container->set(TypeInference::class, function (ContainerInterface $c) {
                return new TypeInference($c->get(TypeRegistry::class));
            });
        } elseif (method_exists($container, 'bind')) {
            $container->bind(TypeRegistry::class, TypeRegistry::class);
            $container->bind(TypeInference::class, function ($c) {
                return new TypeInference($c->make(TypeRegistry::class));
            });
        } elseif (method_exists($container, 'set')) {
            $typeRegistry = new TypeRegistry();
            $container->set(TypeRegistry::class, $typeRegistry);
            $container->set(TypeInference::class, new TypeInference($typeRegistry));
        }
    }

    private function registerMetadataFactory(ContainerInterface $container): void
    {
        if ($container instanceof \DI\Container) {
            $container->set(MetadataFactory::class, function (ContainerInterface $c) {
                return new MetadataFactory(
                    $c->get(TypeRegistry::class),
                    $c->get(TypeInference::class)
                );
            });
        } elseif (method_exists($container, 'bind')) {
            $container->bind(MetadataFactory::class, function ($c) {
                return new MetadataFactory(
                    $c->make(TypeRegistry::class),
                    $c->make(TypeInference::class)
                );
            });
        } elseif (method_exists($container, 'set')) {
            $container->set(MetadataFactory::class, new MetadataFactory(
                $container->get(TypeRegistry::class),
                $container->get(TypeInference::class)
            ));
        }
    }

    private function registerProxyFactory(ContainerInterface $container): void
    {
        if ($container instanceof \DI\Container) {
            $container->set(ProxyFactoryInterface::class, \DI\create(LazyGhostProxyFactory::class));
        } elseif (method_exists($container, 'bind')) {
            $container->bind(ProxyFactoryInterface::class, LazyGhostProxyFactory::class);
        } elseif (method_exists($container, 'set')) {
            $container->set(ProxyFactoryInterface::class, new LazyGhostProxyFactory());
        }
    }

    private function registerEntityManager(ContainerInterface $container): void
    {
        if ($container instanceof \DI\Container) {
            $container->set(EntityManagerInterface::class, function (ContainerInterface $c) {
                return new EntityManager(
                    $c->get(ConnectionInterface::class),
                    $c->get(MetadataFactory::class),
                    $c->get(ProxyFactoryInterface::class),
                    $c
                );
            });
            $container->set(EntityManager::class, \DI\get(EntityManagerInterface::class));
        } elseif (method_exists($container, 'bind')) {
            $container->bind(EntityManagerInterface::class, function ($c) {
                return new EntityManager(
                    $c->make(ConnectionInterface::class),
                    $c->make(MetadataFactory::class),
                    $c->make(ProxyFactoryInterface::class),
                    $c
                );
            });
            $container->bind(EntityManager::class, EntityManagerInterface::class);
        } elseif (method_exists($container, 'set')) {
            $container->set(EntityManagerInterface::class, function () use ($container) {
                return new EntityManager(
                    $container->get(ConnectionInterface::class),
                    $container->get(MetadataFactory::class),
                    $container->get(ProxyFactoryInterface::class),
                    $container
                );
            });
            $container->set(EntityManager::class, $container->get(EntityManagerInterface::class));
        }
    }
}
