<?php

declare(strict_types=1);

namespace Fduarte42\Aurum;

use Fduarte42\Aurum\Connection\ConnectionInterface;
use Fduarte42\Aurum\Exception\ORMException;
use Fduarte42\Aurum\Metadata\MetadataFactory;
use Fduarte42\Aurum\Migration\MigrationManager;
use Fduarte42\Aurum\Migration\MigrationManagerInterface;
use Fduarte42\Aurum\Proxy\ProxyFactoryInterface;
use Fduarte42\Aurum\Repository\Repository;
use Fduarte42\Aurum\Repository\RepositoryInterface;
use Fduarte42\Aurum\UnitOfWork\UnitOfWork;
use Fduarte42\Aurum\UnitOfWork\UnitOfWorkInterface;

/**
 * Entity Manager implementation managing multiple UnitOfWorks
 */
class EntityManager implements EntityManagerInterface
{
    private UnitOfWorkInterface $currentUnitOfWork;

    /** @var array<string, UnitOfWorkInterface> */
    private array $unitOfWorks = [];

    /** @var array<string, RepositoryInterface> */
    private array $repositories = [];

    private int $unitOfWorkCounter = 0;

    private ?MigrationManagerInterface $migrationManager = null;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly MetadataFactory $metadataFactory,
        private readonly ProxyFactoryInterface $proxyFactory
    ) {
        $this->currentUnitOfWork = $this->createUnitOfWork();
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    public function getMetadataFactory(): MetadataFactory
    {
        return $this->metadataFactory;
    }

    public function getUnitOfWork(): UnitOfWorkInterface
    {
        return $this->currentUnitOfWork;
    }

    public function createUnitOfWork(): UnitOfWorkInterface
    {
        $unitOfWorkId = (string) ++$this->unitOfWorkCounter;
        $unitOfWork = new UnitOfWork(
            $this->connection,
            $this->metadataFactory,
            $this->proxyFactory,
            $unitOfWorkId
        );
        
        $this->unitOfWorks[$unitOfWorkId] = $unitOfWork;
        
        return $unitOfWork;
    }

    public function setUnitOfWork(UnitOfWorkInterface $unitOfWork): void
    {
        $this->currentUnitOfWork = $unitOfWork;
    }

    public function find(string $className, mixed $id): ?object
    {
        return $this->currentUnitOfWork->find($className, $id);
    }

    public function persist(object $entity): void
    {
        $this->currentUnitOfWork->persist($entity);
    }

    public function remove(object $entity): void
    {
        $this->currentUnitOfWork->remove($entity);
    }

    public function refresh(object $entity): void
    {
        $this->currentUnitOfWork->refresh($entity);
    }

    public function detach(object $entity): void
    {
        $this->currentUnitOfWork->detach($entity);
    }

    public function contains(object $entity): bool
    {
        return $this->currentUnitOfWork->contains($entity);
    }

    public function flush(): void
    {
        $this->currentUnitOfWork->flush();
    }

    public function clear(): void
    {
        $this->currentUnitOfWork->clear();
    }

    public function getRepository(string $className): RepositoryInterface
    {
        if (!isset($this->repositories[$className])) {
            $metadata = $this->metadataFactory->getMetadataFor($className);
            
            $this->repositories[$className] = new Repository(
                $className,
                $this,
                $metadata
            );
        }
        
        return $this->repositories[$className];
    }

    public function beginTransaction(): void
    {
        if ($this->connection->inTransaction()) {
            throw ORMException::transactionAlreadyActive();
        }
        
        $this->connection->beginTransaction();
    }

    public function commit(): void
    {
        if (!$this->connection->inTransaction()) {
            throw ORMException::transactionNotActive();
        }
        
        // Release all savepoints before committing
        foreach ($this->unitOfWorks as $unitOfWork) {
            try {
                $unitOfWork->releaseSavepoint();
            } catch (\Exception) {
                // Ignore errors when releasing savepoints
            }
        }
        
        $this->connection->commit();
    }

    public function rollback(): void
    {
        if (!$this->connection->inTransaction()) {
            throw ORMException::transactionNotActive();
        }
        
        $this->connection->rollback();
        
        // Clear all unit of works after rollback
        foreach ($this->unitOfWorks as $unitOfWork) {
            $unitOfWork->clear();
        }
    }

    public function transactional(callable $func): mixed
    {
        $this->beginTransaction();
        
        try {
            $result = $func();
            $this->commit();
            return $result;
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function getUnitOfWorks(): array
    {
        return array_values($this->unitOfWorks);
    }

    /**
     * Create a reference to an entity without loading it from the database
     */
    public function getReference(string $className, mixed $id): object
    {
        // Check if entity is already in the current unit of work
        $entity = $this->currentUnitOfWork->find($className, $id);
        if ($entity !== null) {
            return $entity;
        }
        
        // Create a lazy proxy
        return $this->proxyFactory->createProxy(
            $className,
            $id,
            fn() => $this->find($className, $id)
        );
    }

    /**
     * Merge an entity into the current unit of work
     */
    public function merge(object $entity): object
    {
        $className = $this->proxyFactory->getRealClass($entity);
        $metadata = $this->metadataFactory->getMetadataFor($className);
        $id = $metadata->getIdentifierValue($entity);
        
        if ($id === null) {
            throw ORMException::entityNotFound($className, 'null');
        }
        
        // Check if entity is already managed
        $managedEntity = $this->currentUnitOfWork->find($className, $id);
        if ($managedEntity !== null) {
            // Copy state from detached entity to managed entity
            foreach ($metadata->getFieldMappings() as $fieldMapping) {
                if (!$fieldMapping->isIdentifier()) {
                    $value = $metadata->getFieldValue($entity, $fieldMapping->getFieldName());
                    $metadata->setFieldValue($managedEntity, $fieldMapping->getFieldName(), $value);
                }
            }
            return $managedEntity;
        }
        
        // Entity is not managed, persist it
        $this->persist($entity);
        return $entity;
    }

    /**
     * Execute a native SQL query
     */
    public function createNativeQuery(string $sql, array $parameters = []): array
    {
        return $this->connection->fetchAll($sql, $parameters);
    }

    /**
     * Get metadata for an entity class
     */
    public function getClassMetadata(string $className): \Fduarte42\Aurum\Metadata\EntityMetadataInterface
    {
        return $this->metadataFactory->getMetadataFor($className);
    }

    /**
     * Check if the entity manager is open (not closed due to errors)
     */
    public function isOpen(): bool
    {
        return true; // For simplicity, always return true
    }

    /**
     * Close the entity manager
     */
    public function close(): void
    {
        // Clear all unit of works without trying to rollback savepoints
        foreach ($this->unitOfWorks as $unitOfWork) {
            $unitOfWork->clear();
        }
        $this->repositories = [];
    }

    /**
     * Get the migration manager
     */
    public function getMigrationManager(): MigrationManagerInterface
    {
        if ($this->migrationManager === null) {
            // Create migration manager with default configuration
            // The project root is determined by going up from the vendor directory
            $projectRoot = dirname(__DIR__, 4); // Assumes package is in vendor/fduarte42/aurum
            if (!is_dir($projectRoot . '/vendor')) {
                // Fallback: use current working directory
                $projectRoot = getcwd();
            }

            $this->migrationManager = MigrationManager::create($this->connection, $projectRoot);
        }

        return $this->migrationManager;
    }
}
