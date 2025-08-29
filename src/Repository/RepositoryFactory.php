<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Repository;

use Fduarte42\Aurum\EntityManagerInterface;
use Fduarte42\Aurum\Metadata\EntityMetadataInterface;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionParameter;

/**
 * Factory for creating repository instances with dependency injection support
 */
class RepositoryFactory
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ?ContainerInterface $container = null
    ) {
    }

    /**
     * Create a repository instance for the given entity class
     *
     * @template T of object
     * @param class-string<T> $entityClass
     * @param class-string<RepositoryInterface<T>>|null $repositoryClass
     * @return RepositoryInterface<T>
     */
    public function createRepository(string $entityClass, ?string $repositoryClass = null): RepositoryInterface
    {
        $metadata = $this->entityManager->getMetadataFactory()->getMetadataFor($entityClass);
        
        // Use custom repository class if specified, otherwise use default Repository
        $repositoryClass = $repositoryClass ?? Repository::class;
        
        // Try to create repository with dependency injection
        return $this->createWithDependencyInjection($repositoryClass, $entityClass, $metadata);
    }

    /**
     * Create repository instance using dependency injection
     *
     * @template T of object
     * @param class-string<RepositoryInterface<T>> $repositoryClass
     * @param class-string<T> $entityClass
     * @param EntityMetadataInterface $metadata
     * @return RepositoryInterface<T>
     */
    private function createWithDependencyInjection(
        string $repositoryClass,
        string $entityClass,
        EntityMetadataInterface $metadata
    ): RepositoryInterface {
        $reflection = new ReflectionClass($repositoryClass);
        $constructor = $reflection->getConstructor();

        // If no constructor, create instance and inject dependencies via reflection
        if ($constructor === null) {
            $instance = $reflection->newInstanceWithoutConstructor();
            $this->injectDependencies($instance, $entityClass, $metadata);
            return $instance;
        }

        $parameters = $constructor->getParameters();
        
        // If constructor has no parameters, create instance and inject dependencies
        if (empty($parameters)) {
            $instance = $reflection->newInstance();
            $this->injectDependencies($instance, $entityClass, $metadata);
            return $instance;
        }

        // Try to resolve constructor parameters
        $args = $this->resolveConstructorParameters($parameters, $entityClass, $metadata);

        if ($args !== null) {
            // All parameters resolved, use constructor injection
            $instance = $reflection->newInstanceArgs($args);
            // Still inject framework dependencies if they weren't provided via constructor
            $this->injectDependencies($instance, $entityClass, $metadata);
            return $instance;
        }

        // Create instance without constructor and inject dependencies
        $instance = $reflection->newInstanceWithoutConstructor();
        $this->injectDependencies($instance, $entityClass, $metadata);
        return $instance;
    }



    /**
     * Resolve constructor parameters using container and known dependencies
     */
    private function resolveConstructorParameters(array $parameters, string $entityClass, EntityMetadataInterface $metadata): ?array
    {
        $args = [];
        
        foreach ($parameters as $param) {
            $resolved = $this->resolveParameter($param, $entityClass, $metadata);
            if ($resolved === null && !$param->isOptional()) {
                return null; // Cannot resolve required parameter
            }
            $args[] = $resolved;
        }
        
        return $args;
    }

    /**
     * Resolve a single constructor parameter
     */
    private function resolveParameter(ReflectionParameter $param, string $entityClass, EntityMetadataInterface $metadata): mixed
    {
        $type = $param->getType();
        
        if ($type === null) {
            return $param->isOptional() ? $param->getDefaultValue() : null;
        }

        $typeName = $type->getName();

        // Handle known framework dependencies
        switch ($typeName) {
            case 'string':
                // Assume this is the entity class name
                return $entityClass;
                
            case EntityManagerInterface::class:
                return $this->entityManager;
                
            case EntityMetadataInterface::class:
                return $metadata;
                
            case ContainerInterface::class:
                return $this->container;
        }

        // Try to resolve from container
        if ($this->container && $this->container->has($typeName)) {
            return $this->container->get($typeName);
        }

        // Return default value if parameter is optional
        return $param->isOptional() ? $param->getDefaultValue() : null;
    }

    /**
     * Inject dependencies into repository instance using reflection
     */
    private function injectDependencies(object $instance, string $entityClass, EntityMetadataInterface $metadata): void
    {
        $reflection = new ReflectionClass($instance);
        
        // Inject into properties
        $this->injectIntoProperties($reflection, $instance, $entityClass, $metadata);
        
        // Call setter methods if they exist
        $this->callSetterMethods($reflection, $instance, $entityClass, $metadata);
    }

    /**
     * Inject dependencies into properties
     */
    private function injectIntoProperties(ReflectionClass $reflection, object $instance, string $entityClass, EntityMetadataInterface $metadata): void
    {
        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $type = $property->getType();
            if ($type === null) {
                continue;
            }

            $typeName = $type->getName();
            $value = null;

            // Check if property is already set (to avoid overriding constructor-injected values)
            $property->setAccessible(true);
            if ($property->isInitialized($instance) && $property->getValue($instance) !== null) {
                continue;
            }

            switch ($typeName) {
                case 'string':
                    if ($property->getName() === 'className') {
                        $value = $entityClass;
                    }
                    break;

                case EntityManagerInterface::class:
                    $value = $this->entityManager;
                    break;

                case EntityMetadataInterface::class:
                    $value = $metadata;
                    break;

                case ContainerInterface::class:
                    $value = $this->container;
                    break;

                default:
                    if ($this->container && $this->container->has($typeName)) {
                        $value = $this->container->get($typeName);
                    }
                    break;
            }

            if ($value !== null) {
                $property->setValue($instance, $value);
            }
        }
    }

    /**
     * Call setter methods for dependency injection
     */
    private function callSetterMethods(ReflectionClass $reflection, object $instance, string $entityClass, EntityMetadataInterface $metadata): void
    {
        $setters = [
            'setClassName' => $entityClass,
            'setEntityManager' => $this->entityManager,
            'setMetadata' => $metadata,
            'setContainer' => $this->container,
            'setEntityHydrator' => $this->container?->get(\Fduarte42\Aurum\Hydration\EntityHydratorInterface::class),
        ];

        foreach ($setters as $methodName => $value) {
            if ($value !== null && $reflection->hasMethod($methodName)) {
                $method = $reflection->getMethod($methodName);
                if ($method->isPublic() && $method->getNumberOfRequiredParameters() <= 1) {
                    $method->invoke($instance, $value);
                }
            }
        }
    }
}
