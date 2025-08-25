<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Proxy;

use Fduarte42\Aurum\Exception\ORMException;
use ReflectionClass;
use WeakMap;

/**
 * Proxy factory implementation using PHP 8.4 LazyGhost objects
 */
class LazyGhostProxyFactory implements ProxyFactoryInterface
{
    /** @var WeakMap<object, mixed> */
    private WeakMap $proxyIdentifiers;

    /** @var WeakMap<object, bool> */
    private WeakMap $proxyInitialized;

    /** @var WeakMap<object, callable> */
    private WeakMap $proxyInitializers;

    public function __construct()
    {
        $this->proxyIdentifiers = new WeakMap();
        $this->proxyInitialized = new WeakMap();
        $this->proxyInitializers = new WeakMap();
    }

    public function createProxy(string $className, mixed $identifier, callable $initializer): object
    {
        if (!class_exists($className)) {
            throw ORMException::invalidEntityClass($className);
        }

        $reflectionClass = new ReflectionClass($className);

        // Check if LazyGhost is available (PHP 8.4+)
        if (class_exists('\LazyGhost') && method_exists($reflectionClass, 'newLazyGhost')) {
            // Create a lazy ghost proxy
            $proxy = $reflectionClass->newLazyGhost(function (object $proxy) use ($initializer, $identifier) {
                // Mark as initialized
                $this->proxyInitialized[$proxy] = true;

                // Call the initializer to load the actual entity data
                $entity = $initializer();

                if ($entity === null) {
                    throw ORMException::entityNotFound(get_class($proxy), $identifier);
                }

                // Copy properties from the loaded entity to the proxy
                $this->copyEntityToProxy($entity, $proxy);
            });

            // Store the identifier and initialization state
            $this->proxyIdentifiers[$proxy] = $identifier;
            $this->proxyInitialized[$proxy] = false;

            return $proxy;
        } else {
            // Fallback: create a simple proxy using newInstanceWithoutConstructor
            $proxy = $reflectionClass->newInstanceWithoutConstructor();

            // Store the identifier and initialization state
            $this->proxyIdentifiers[$proxy] = $identifier;
            $this->proxyInitialized[$proxy] = false;

            // Store the initializer for later use
            $this->proxyInitializers[$proxy] = $initializer;

            return $proxy;
        }
    }

    public function isProxy(object $object): bool
    {
        // Check if it's a LazyGhost (if available)
        if (class_exists('\LazyGhost') && $object instanceof \LazyGhost) {
            return true;
        }

        // Check if it's in our proxy tracking
        return isset($this->proxyIdentifiers[$object]);
    }

    public function initializeProxy(object $proxy): void
    {
        if (!$this->isProxy($proxy)) {
            return;
        }

        if ($this->isProxyInitialized($proxy)) {
            return;
        }

        // Check if it's a LazyGhost
        if (class_exists('\LazyGhost') && $proxy instanceof \LazyGhost) {
            // Force initialization by accessing a property
            $reflectionClass = new ReflectionClass($proxy);
            $properties = $reflectionClass->getProperties();

            if (!empty($properties)) {
                $property = $properties[0];
                $property->setAccessible(true);
                $property->getValue($proxy); // This triggers the lazy initialization
            }
        } else {
            // Fallback proxy initialization
            if (isset($this->proxyInitializers[$proxy])) {
                $initializer = $this->proxyInitializers[$proxy];
                $identifier = $this->proxyIdentifiers[$proxy];

                $entity = $initializer();

                if ($entity === null) {
                    throw ORMException::entityNotFound(get_class($proxy), $identifier);
                }

                // Copy properties from the loaded entity to the proxy
                $this->copyEntityToProxy($entity, $proxy);
                $this->proxyInitialized[$proxy] = true;
            } else {
                // If no initializer is found, mark as initialized anyway
                $this->proxyInitialized[$proxy] = true;
            }
        }
    }

    public function isProxyInitialized(object $proxy): bool
    {
        if (!$this->isProxy($proxy)) {
            return true;
        }

        // Check if it's a LazyGhost
        if (class_exists('\LazyGhost') && $proxy instanceof \LazyGhost) {
            return !$proxy->isLazy();
        }

        // For fallback proxies, check our tracking
        return isset($this->proxyInitialized[$proxy]) && $this->proxyInitialized[$proxy];
    }

    public function getRealClass(object|string $objectOrClass): string
    {
        if (is_string($objectOrClass)) {
            return $objectOrClass;
        }

        if ($this->isProxy($objectOrClass)) {
            return get_parent_class($objectOrClass) ?: get_class($objectOrClass);
        }

        return get_class($objectOrClass);
    }

    public function getProxyIdentifier(object $proxy): mixed
    {
        if (!$this->isProxy($proxy)) {
            throw new \InvalidArgumentException('Object is not a proxy');
        }

        return $this->proxyIdentifiers[$proxy] ?? null;
    }

    /**
     * Copy properties from source entity to target proxy
     */
    private function copyEntityToProxy(object $source, object $target): void
    {
        $sourceReflection = new ReflectionClass($source);
        $targetReflection = new ReflectionClass($target);

        foreach ($sourceReflection->getProperties() as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($source);

            try {
                $targetProperty = $targetReflection->getProperty($property->getName());
                $targetProperty->setAccessible(true);
                $targetProperty->setValue($target, $value);
            } catch (\ReflectionException) {
                // Property doesn't exist in target, skip
            }
        }
    }

    /**
     * Create a collection proxy for lazy loading collections
     */
    public function createCollectionProxy(callable $initializer): \ArrayObject
    {
        return new class($initializer) extends \ArrayObject {
            private bool $initialized = false;
            private $initializer;

            public function __construct(callable $initializer)
            {
                $this->initializer = $initializer;
                parent::__construct();
            }

            private function initialize(): void
            {
                if ($this->initialized) {
                    return;
                }

                $this->initialized = true;
                $data = ($this->initializer)();
                
                if (is_array($data)) {
                    $this->exchangeArray($data);
                }
            }

            public function offsetExists(mixed $key): bool
            {
                $this->initialize();
                return parent::offsetExists($key);
            }

            public function offsetGet(mixed $key): mixed
            {
                $this->initialize();
                return parent::offsetGet($key);
            }

            public function offsetSet(mixed $key, mixed $value): void
            {
                $this->initialize();
                parent::offsetSet($key, $value);
            }

            public function offsetUnset(mixed $key): void
            {
                $this->initialize();
                parent::offsetUnset($key);
            }

            public function count(): int
            {
                $this->initialize();
                return parent::count();
            }

            public function getIterator(): \Iterator
            {
                $this->initialize();
                return parent::getIterator();
            }

            public function getArrayCopy(): array
            {
                $this->initialize();
                return parent::getArrayCopy();
            }
        };
    }
}
