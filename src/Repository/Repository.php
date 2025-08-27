<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Repository;

use Fduarte42\Aurum\EntityManagerInterface;
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
    }

    public function find(mixed $id): ?object
    {
        $this->ensureDependenciesInjected();
        return $this->entityManager->find($this->className, $id);
    }

    public function findAll(): array
    {
        $this->ensureDependenciesInjected();
        $qb = $this->createQueryBuilder('e');
        return $this->hydrateResults($qb->getResult());
    }

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
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

        return $this->hydrateResults($qb->getResult());
    }

    public function findOneBy(array $criteria, ?array $orderBy = null): ?object
    {
        $results = $this->findBy($criteria, $orderBy, 1);
        return $results[0] ?? null;
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
            $this->entityManager->getMetadataFactory()
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

    public function findBySql(string $sql, array $parameters = []): array
    {
        $this->ensureDependenciesInjected();
        $results = $this->entityManager->getConnection()->fetchAll($sql, $parameters);
        return $this->hydrateResults($results);
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
    public function findWithPagination(array $criteria = [], ?array $orderBy = null, int $page = 1, int $pageSize = 20): array
    {
        $offset = ($page - 1) * $pageSize;
        return $this->findBy($criteria, $orderBy, $pageSize, $offset);
    }

    /**
     * Find entities by a single field value
     */
    public function findByField(string $field, mixed $value): array
    {
        return $this->findBy([$field => $value]);
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
    public function findByLike(string $field, string $pattern): array
    {
        $qb = $this->createQueryBuilder('e')
            ->where("e.{$field} LIKE :pattern")
            ->setParameter('pattern', $pattern);
        
        return $this->hydrateResults($qb->getResult());
    }

    /**
     * Find entities within a range
     */
    public function findByRange(string $field, mixed $min, mixed $max): array
    {
        $qb = $this->createQueryBuilder('e')
            ->where("e.{$field} BETWEEN :min AND :max")
            ->setParameter('min', $min)
            ->setParameter('max', $max);
        
        return $this->hydrateResults($qb->getResult());
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
        $entities = [];

        foreach ($results as $result) {
            $entities[] = $this->hydrateEntity($result);
        }

        return $entities;
    }

    /**
     * Hydrate a single database result into an entity
     */
    private function hydrateEntity(array $data): object
    {
        $this->ensureDependenciesInjected();

        // Determine the correct entity class for inheritance hierarchies
        $entityClass = $this->className;
        $metadata = $this->metadata;

        if ($this->metadata->hasInheritance()) {
            $inheritanceMapping = $this->metadata->getInheritanceMapping();
            $discriminatorColumn = $inheritanceMapping->getDiscriminatorColumn();

            // Check if discriminator value is present in the data
            if (isset($data[$discriminatorColumn]) || isset($data['__discriminator'])) {
                $discriminatorValue = $data[$discriminatorColumn] ?? $data['__discriminator'];
                $actualEntityClass = $inheritanceMapping->getClassNameForDiscriminatorValue($discriminatorValue);

                if ($actualEntityClass !== $entityClass && class_exists($actualEntityClass)) {
                    $entityClass = $actualEntityClass;
                    $metadata = $this->entityManager->getMetadataFactory()->getMetadataFor($entityClass);
                }
            }
        }

        $entity = $metadata->newInstance();
        
        foreach ($metadata->getFieldMappings() as $fieldMapping) {
            $fieldName = $fieldMapping->getFieldName();
            $columnName = $fieldMapping->getColumnName();

            // Skip discriminator field as it's virtual
            if ($fieldName === '__discriminator') {
                continue;
            }

            if (isset($data[$fieldName])) {
                // Data is already mapped by field name (from query builder)
                $metadata->setFieldValue($entity, $fieldName, $data[$fieldName]);
            } elseif (isset($data[$columnName])) {
                // Data is mapped by column name (from raw SQL)
                $metadata->setFieldValue($entity, $fieldName, $data[$columnName]);
            }
        }

        // Add to unit of work if it has an identifier
        $id = $this->metadata->getIdentifierValue($entity);
        if ($id !== null) {
            $this->entityManager->getUnitOfWork()->persist($entity);
        }
        
        return $entity;
    }
}
