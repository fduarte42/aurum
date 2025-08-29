<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Repository;

use Fduarte42\Aurum\EntityManagerInterface;
use Fduarte42\Aurum\Hydration\EntityHydratorInterface;
use Fduarte42\Aurum\Metadata\EntityMetadataInterface;
use Fduarte42\Aurum\Query\QueryBuilder;
use Fduarte42\Aurum\Query\QueryBuilderInterface;
use Psr\Container\ContainerInterface;

/**
 * Base repository implementation with common CRUD operations
 *
 * @template T of object
 * @implements RepositoryInterface<T>
 */
class Repository implements RepositoryInterface
{
    protected string $className;
    protected EntityManagerInterface $entityManager;
    protected EntityMetadataInterface $metadata;
    protected ?ContainerInterface $container = null;
    protected ?EntityHydratorInterface $entityHydrator = null;

    /**
     * Parameterless constructor for dependency injection
     * All dependencies will be injected via setters or reflection
     */
    public function __construct()
    {
        // Dependencies will be injected via reflection or setters
    }

    /**
     * Set the entity class name (for dependency injection)
     */
    public function setClassName(string $className): void
    {
        $this->className = $className;
    }

    /**
     * Set the entity manager (for dependency injection)
     */
    public function setEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Set the entity metadata (for dependency injection)
     */
    public function setMetadata(EntityMetadataInterface $metadata): void
    {
        $this->metadata = $metadata;
    }

    /**
     * Set the container (for dependency injection)
     */
    public function setContainer(?ContainerInterface $container): void
    {
        $this->container = $container;
    }

    /**
     * Set the entity hydrator (for dependency injection)
     */
    public function setEntityHydrator(?EntityHydratorInterface $entityHydrator): void
    {
        $this->entityHydrator = $entityHydrator;
    }

    /**
     * Check if all required dependencies are injected
     */
    protected function ensureDependenciesInjected(): void
    {
        if (!isset($this->className)) {
            throw new \RuntimeException('Repository className not set. Ensure dependency injection is properly configured.');
        }
        if (!isset($this->entityManager)) {
            throw new \RuntimeException('Repository entityManager not set. Ensure dependency injection is properly configured.');
        }
        if (!isset($this->metadata)) {
            throw new \RuntimeException('Repository metadata not set. Ensure dependency injection is properly configured.');
        }
        if (!isset($this->entityHydrator)) {
            throw new \RuntimeException('Repository entityHydrator not set. Ensure dependency injection is properly configured.');
        }
    }

    public function find(mixed $id): ?object
    {
        $this->ensureDependenciesInjected();
        return $this->entityManager->find($this->className, $id);
    }

    public function findAll(): \Iterator
    {
        $this->ensureDependenciesInjected();
        $qb = $this->createQueryBuilder('e');
        $sourceIterator = $qb->getArrayResult();
        return new ManagedEntityIterator($sourceIterator, $this, $this->entityManager);
    }

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): \Iterator
    {
        $this->ensureDependenciesInjected();
        $qb = $this->createQueryBuilder('e');

        $this->applyCriteria($qb, $criteria);

        if ($orderBy !== null) {
            foreach ($orderBy as $field => $direction) {
                $qb->addOrderBy("e.{$field}", $direction);
            }
        }

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        if ($offset !== null) {
            $qb->setFirstResult($offset);
        }

        $sourceIterator = $qb->getArrayResult();
        return new ManagedEntityIterator($sourceIterator, $this, $this->entityManager);
    }

    public function findOneBy(array $criteria, ?array $orderBy = null): ?object
    {
        $iterator = $this->findBy($criteria, $orderBy, 1);
        foreach ($iterator as $entity) {
            return $entity; // Return the first (and only) entity
        }
        return null;
    }

    public function count(array $criteria = []): int
    {
        $this->ensureDependenciesInjected();
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(*)');

        $this->applyCriteria($qb, $criteria);

        return (int) $qb->getSingleScalarResult();
    }

    public function createQueryBuilder(string $alias): QueryBuilderInterface
    {
        $this->ensureDependenciesInjected();
        $qb = new QueryBuilder(
            $this->entityManager->getConnection(),
            $this->entityManager->getMetadataFactory(),
            $this->entityHydrator
        );

        // Set the root entity class for automatic join resolution
        $qb->setRootEntityClass($this->className);

        // Build SELECT clause with all entity fields
        $selectFields = [];
        foreach ($this->metadata->getFieldMappings() as $fieldMapping) {
            $selectFields[] = sprintf(
                '%s.%s AS %s',
                $alias,
                $this->entityManager->getConnection()->quoteIdentifier($fieldMapping->getColumnName()),
                $fieldMapping->getFieldName()
            );
        }

        return $qb
            ->select($selectFields)
            ->from($this->metadata->getTableName(), $alias);
    }

    public function getClassName(): string
    {
        $this->ensureDependenciesInjected();
        return $this->className;
    }

    public function findBySql(string $sql, array $parameters = []): \Iterator
    {
        $this->ensureDependenciesInjected();
        $results = $this->entityManager->getConnection()->fetchAll($sql, $parameters);
        $sourceIterator = new \ArrayIterator($results);
        return new ManagedEntityIterator($sourceIterator, $this, $this->entityManager);
    }

    public function findOneBySql(string $sql, array $parameters = []): ?object
    {
        $this->ensureDependenciesInjected();
        $result = $this->entityManager->getConnection()->fetchOne($sql, $parameters);

        if ($result === null) {
            return null;
        }

        return $this->hydrateEntity($result);
    }

    /**
     * Save an entity (persist and flush)
     */
    public function save(object $entity): void
    {
        $this->ensureDependenciesInjected();
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * Delete an entity (remove and flush)
     */
    public function delete(object $entity): void
    {
        $this->ensureDependenciesInjected();
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    /**
     * Find entities with pagination
     */
    public function findWithPagination(array $criteria = [], ?array $orderBy = null, int $page = 1, int $pageSize = 20): \Iterator
    {
        $offset = ($page - 1) * $pageSize;
        return $this->findBy($criteria, $orderBy, $pageSize, $offset);
    }

    /**
     * Find entities with pagination as array (convenience method for backward compatibility)
     */
    public function findWithPaginationAsArray(array $criteria = [], ?array $orderBy = null, int $page = 1, int $pageSize = 20): array
    {
        return iterator_to_array($this->findWithPagination($criteria, $orderBy, $page, $pageSize));
    }

    /**
     * Find entities by a single field value
     */
    public function findByField(string $field, mixed $value): \Iterator
    {
        return $this->findBy([$field => $value]);
    }

    /**
     * Find entities by a single field value as array (convenience method for backward compatibility)
     */
    public function findByFieldAsArray(string $field, mixed $value): array
    {
        return iterator_to_array($this->findByField($field, $value));
    }

    /**
     * Find one entity by a single field value
     */
    public function findOneByField(string $field, mixed $value): ?object
    {
        return $this->findOneBy([$field => $value]);
    }

    /**
     * Check if an entity exists by criteria
     */
    public function exists(array $criteria): bool
    {
        return $this->count($criteria) > 0;
    }

    /**
     * Find entities with LIKE condition
     */
    public function findByLike(string $field, string $pattern): \Iterator
    {
        $qb = $this->createQueryBuilder('e')
            ->where("e.{$field} LIKE :pattern")
            ->setParameter('pattern', $pattern);

        $sourceIterator = $qb->getArrayResult();
        return new ManagedEntityIterator($sourceIterator, $this, $this->entityManager);
    }

    /**
     * Find entities within a range
     */
    public function findByRange(string $field, mixed $min, mixed $max): \Iterator
    {
        $qb = $this->createQueryBuilder('e')
            ->where("e.{$field} BETWEEN :min AND :max")
            ->setParameter('min', $min)
            ->setParameter('max', $max);

        $sourceIterator = $qb->getArrayResult();
        return new ManagedEntityIterator($sourceIterator, $this, $this->entityManager);
    }

    /**
     * Apply criteria to query builder
     */
    private function applyCriteria(QueryBuilderInterface $qb, array $criteria): void
    {
        $this->ensureDependenciesInjected();
        foreach ($criteria as $field => $value) {
            $columnName = $this->metadata->getColumnName($field);
            $fieldMapping = $this->metadata->getFieldMapping($field);

            if ($value === null) {
                $qb->andWhere("e.{$columnName} IS NULL");
            } elseif (is_array($value)) {
                // Convert array values to database format if we have field mapping
                $convertedValues = $value;
                if ($fieldMapping !== null) {
                    $convertedValues = array_map(fn($v) => $fieldMapping->convertToDatabaseValue($v), $value);
                }

                // Create individual parameters for each value in the array
                $paramNames = [];
                foreach ($convertedValues as $index => $arrayValue) {
                    $paramName = 'param_' . $field . '_' . $index;
                    $paramNames[] = ':' . $paramName;
                    $qb->setParameter($paramName, $arrayValue);
                }

                $qb->andWhere("e.{$columnName} IN (" . implode(', ', $paramNames) . ")");
            } else {
                $paramName = 'param_' . $field;

                // Convert value to database format if we have field mapping
                if ($fieldMapping !== null) {
                    $value = $fieldMapping->convertToDatabaseValue($value);
                }

                $qb->andWhere("e.{$columnName} = :{$paramName}")
                   ->setParameter($paramName, $value);
            }
        }
    }

    /**
     * Hydrate database results into entities
     */
    private function hydrateResults(array|\PDOStatement $results): array
    {
        $this->ensureDependenciesInjected();

        $dataArray = [];
        foreach ($results as $result) {
            $dataArray[] = $result;
        }

        // Use the centralized EntityHydrator to hydrate multiple entities
        return $this->entityHydrator->hydrateMultiple(
            $dataArray,
            $this->className,
            true, // managed entities
            $this->entityManager->getCurrentUnitOfWork()
        );
    }

    /**
     * Hydrate a single database result into an entity
     */
    public function hydrateEntity(array $data): object
    {
        $this->ensureDependenciesInjected();

        // Use the centralized EntityHydrator to hydrate managed entities
        return $this->entityHydrator->hydrateManaged(
            $data,
            $this->className,
            $this->entityManager->getCurrentUnitOfWork()
        );
    }

    /**
     * Find all entities as array (convenience method for backward compatibility)
     */
    public function findAllAsArray(): array
    {
        return iterator_to_array($this->findAll());
    }

    /**
     * Find entities by criteria as array (convenience method for backward compatibility)
     */
    public function findByAsArray(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        return iterator_to_array($this->findBy($criteria, $orderBy, $limit, $offset));
    }

    /**
     * Execute a custom SQL query and return results as array (convenience method for backward compatibility)
     */
    public function findBySqlAsArray(string $sql, array $parameters = []): array
    {
        return iterator_to_array($this->findBySql($sql, $parameters));
    }

    /**
     * Find entities with LIKE condition as array (convenience method for backward compatibility)
     */
    public function findByLikeAsArray(string $field, string $pattern): array
    {
        return iterator_to_array($this->findByLike($field, $pattern));
    }

    /**
     * Find entities within a range as array (convenience method for backward compatibility)
     */
    public function findByRangeAsArray(string $field, mixed $min, mixed $max): array
    {
        return iterator_to_array($this->findByRange($field, $min, $max));
    }
}
