<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Proxy;

use Fduarte42\Aurum\Exception\ORMException;
use Fduarte42\Aurum\Hydration\EntityHydratorInterface;
use Fduarte42\Aurum\Metadata\EntityMetadataInterface;
use Fduarte42\Aurum\Connection\ConnectionInterface;
use Fduarte42\Aurum\Metadata\MetadataFactory;
use ReflectionClass;
use WeakMap;

/**
 * Proxy factory implementation using PHP 8.4 LazyGhost objects
 * Fully optimized for lazy-ghost pattern with direct database loading
 */
class LazyGhostProxyFactory implements ProxyFactoryInterface
{
    /** @var WeakMap<object, mixed> */
    private WeakMap $proxyIdentifiers;

    public function __construct(
        private readonly ?ConnectionInterface $connection = null,
        private readonly ?MetadataFactory $metadataFactory = null
    ) {
        $this->proxyIdentifiers = new WeakMap();
    }

    public function createProxy(string $className, mixed $identifier, callable $initializer): object
    {
        if (!class_exists($className)) {
            throw ORMException::invalidEntityClass($className);
        }

        $reflectionClass = new ReflectionClass($className);

        // Check if lazy ghost functionality is available (PHP 8.4+)
        if ($this->isLazyGhostSupported($reflectionClass)) {
            // Create a lazy ghost proxy with direct database loading
            $proxy = $reflectionClass->newLazyGhost(function (object $proxy) use ($className, $identifier) {
                $this->initializeProxyDirectly($proxy, $className, $identifier);
            });

            // Store the identifier
            $this->proxyIdentifiers[$proxy] = $identifier;

            // Note: We don't set the ID property immediately to avoid triggering lazy loading
            // The ID will be available through getProxyIdentifier() method

            return $proxy;
        } else {
            throw new \RuntimeException('Lazy ghost functionality is not available. PHP 8.4+ is required for optimized proxy support.');
        }
    }

    public function isProxy(object $object): bool
    {
        // Check if object is a lazy ghost proxy
        return $this->isLazyGhostObject($object);
    }

    public function initializeProxy(object $proxy): void
    {
        if (!$this->isProxy($proxy)) {
            return;
        }

        if ($this->isProxyInitialized($proxy)) {
            return;
        }

        // Force initialization by accessing a non-identifier property
        $this->triggerLazyInitialization($proxy);
    }

    public function isProxyInitialized(object $proxy): bool
    {
        if (!$this->isProxy($proxy)) {
            return true;
        }

        // For lazy ghost proxies, we need to check if the proxy has been initialized
        // Since we can't rely on isLazy method, we'll use reflection to check if properties are set
        try {
            $reflection = new ReflectionClass($proxy);
            $properties = $reflection->getProperties();

            // If any property has a non-default value, consider it initialized
            foreach ($properties as $property) {
                $property->setAccessible(true);

                // Skip uninitialized typed properties to avoid errors
                if (!$property->isInitialized($proxy)) {
                    continue;
                }

                $value = $property->getValue($proxy);
                if ($value !== null && $value !== '' && $value !== [] && $value !== false) {
                    return true;
                }
            }

            return false;
        } catch (\ReflectionException | \Error) {
            return false; // Assume not initialized if we can't check
        }
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
     * Initialize proxy directly by loading from database
     */
    protected function initializeProxyDirectly(object $proxy, string $className, mixed $identifier): void
    {
        if ($this->connection === null || $this->metadataFactory === null) {
            throw new \RuntimeException('Connection and MetadataFactory are required for direct proxy initialization');
        }

        $metadata = $this->metadataFactory->getMetadataFor($className);

        // Load from database
        $sql = sprintf(
            'SELECT * FROM %s WHERE %s = :id',
            $this->connection->quoteIdentifier($metadata->getTableName()),
            $this->connection->quoteIdentifier($metadata->getIdentifierColumnName())
        );

        $data = $this->connection->fetchOne($sql, ['id' => $identifier]);

        if ($data === null) {
            throw ORMException::entityNotFound($className, $identifier);
        }

        // Populate proxy directly without going through UnitOfWork
        $this->populateProxyFromData($proxy, $metadata, $data);
    }

    /**
     * Set identifier on proxy immediately without triggering lazy loading
     */
    protected function setIdentifierOnProxy(object $proxy, string $className, mixed $identifier): void
    {
        if ($this->metadataFactory === null) {
            return; // Skip if metadata factory not available
        }

        try {
            $metadata = $this->metadataFactory->getMetadataFor($className);
            $identifierField = $metadata->getIdentifierFieldName();

            $reflectionClass = new ReflectionClass($proxy);
            $property = $reflectionClass->getProperty($identifierField);
            $property->setAccessible(true);

            // Set the identifier directly - this should not trigger lazy loading for ID properties
            $property->setValue($proxy, $identifier);
        } catch (\ReflectionException) {
            // Property doesn't exist or can't be set, skip
        }
    }

    /**
     * Populate proxy from database data
     */
    private function populateProxyFromData(object $proxy, EntityMetadataInterface $metadata, array $data): void
    {
        foreach ($metadata->getFieldMappings() as $fieldMapping) {
            $columnName = $fieldMapping->getColumnName();
            if (isset($data[$columnName])) {
                $metadata->setFieldValue($proxy, $fieldMapping->getFieldName(), $data[$columnName]);
            }
        }
    }

    /**
     * Trigger lazy initialization by accessing a non-identifier property
     */
    private function triggerLazyInitialization(object $proxy): void
    {
        $reflectionClass = new ReflectionClass($proxy);
        $properties = $reflectionClass->getProperties();

        // Find a non-identifier property to trigger initialization
        foreach ($properties as $property) {
            if ($this->metadataFactory !== null) {
                try {
                    $metadata = $this->metadataFactory->getMetadataFor($reflectionClass->getName());
                    if (!$metadata->isIdentifier($property->getName())) {
                        $property->setAccessible(true);
                        $property->getValue($proxy); // This triggers the lazy initialization
                        return;
                    }
                } catch (\Exception) {
                    // Continue to next property
                }
            }
        }

        // Fallback: access first property if no metadata available
        if (!empty($properties)) {
            $property = $properties[0];
            $property->setAccessible(true);
            $property->getValue($proxy);
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

    /**
     * Check if lazy ghost functionality is supported
     */
    private function isLazyGhostSupported(ReflectionClass $reflectionClass): bool
    {
        // Check if PHP version supports lazy ghost (8.4+)
        if (!version_compare(PHP_VERSION, '8.4.0', '>=')) {
            return false;
        }

        // Check if the newLazyGhost method exists on ReflectionClass
        return method_exists($reflectionClass, 'newLazyGhost');
    }

    /**
     * Check if an object is a lazy ghost object
     */
    private function isLazyGhostObject(object $object): bool
    {
        // Check if the object is tracked in our proxy identifiers map
        return isset($this->proxyIdentifiers[$object]);
    }
}
