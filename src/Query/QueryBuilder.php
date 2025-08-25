<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Query;

use Fduarte42\Aurum\Connection\ConnectionInterface;
use Fduarte42\Aurum\Exception\ORMException;
use Fduarte42\Aurum\Metadata\MetadataFactory;

/**
 * SQL Query Builder implementation with join support
 */
class QueryBuilder implements QueryBuilderInterface
{
    private array $select = [];
    private ?string $from = null;
    private ?string $fromAlias = null;
    private array $joins = [];
    private array $where = [];
    private array $groupBy = [];
    private array $having = [];
    private array $orderBy = [];
    private ?int $maxResults = null;
    private ?int $firstResult = null;
    private array $parameters = [];
    private ?string $rootEntityClass = null;
    private ?MetadataFactory $metadataFactory = null;

    public function __construct(
        private readonly ConnectionInterface $connection,
        ?MetadataFactory $metadataFactory = null
    ) {
        $this->metadataFactory = $metadataFactory;
    }

    public function select(string|array $select): self
    {
        $this->select = is_array($select) ? $select : [$select];
        return $this;
    }

    public function addSelect(string|array $select): self
    {
        $selectFields = is_array($select) ? $select : [$select];
        $this->select = array_merge($this->select, $selectFields);
        return $this;
    }

    public function from(string $table, string $alias): self
    {
        $this->from = $table;
        $this->fromAlias = $alias;
        return $this;
    }

    /**
     * Set the root entity class for automatic join resolution
     */
    public function setRootEntityClass(string $entityClass): self
    {
        $this->rootEntityClass = $entityClass;
        return $this;
    }

    public function innerJoin(string $join, string $alias, ?string $condition = null): self
    {
        $resolvedCondition = $condition ?? $this->resolveJoinCondition($join, $alias);
        $resolvedTable = $this->resolveTableName($join);

        $this->joins[] = [
            'type' => 'INNER',
            'table' => $resolvedTable,
            'alias' => $alias,
            'condition' => $resolvedCondition
        ];
        return $this;
    }

    public function leftJoin(string $join, string $alias, ?string $condition = null): self
    {
        $resolvedCondition = $condition ?? $this->resolveJoinCondition($join, $alias);

        $this->joins[] = [
            'type' => 'LEFT',
            'table' => $this->resolveTableName($join),
            'alias' => $alias,
            'condition' => $resolvedCondition
        ];
        return $this;
    }

    public function rightJoin(string $join, string $alias, ?string $condition = null): self
    {
        $resolvedCondition = $condition ?? $this->resolveJoinCondition($join, $alias);

        $this->joins[] = [
            'type' => 'RIGHT',
            'table' => $this->resolveTableName($join),
            'alias' => $alias,
            'condition' => $resolvedCondition
        ];
        return $this;
    }

    public function where(string $condition): self
    {
        $this->where = [$condition];
        return $this;
    }

    public function andWhere(string $condition): self
    {
        $this->where[] = $condition;
        return $this;
    }

    public function orWhere(string $condition): self
    {
        if (empty($this->where)) {
            $this->where[] = $condition;
        } else {
            $lastCondition = array_pop($this->where);
            $this->where[] = "({$lastCondition}) OR ({$condition})";
        }
        return $this;
    }

    public function groupBy(string|array $groupBy): self
    {
        $this->groupBy = is_array($groupBy) ? $groupBy : [$groupBy];
        return $this;
    }

    public function addGroupBy(string|array $groupBy): self
    {
        $groupByFields = is_array($groupBy) ? $groupBy : [$groupBy];
        $this->groupBy = array_merge($this->groupBy, $groupByFields);
        return $this;
    }

    public function having(string $condition): self
    {
        $this->having = [$condition];
        return $this;
    }

    public function andHaving(string $condition): self
    {
        $this->having[] = $condition;
        return $this;
    }

    public function orHaving(string $condition): self
    {
        if (empty($this->having)) {
            $this->having[] = $condition;
        } else {
            $lastCondition = array_pop($this->having);
            $this->having[] = "({$lastCondition}) OR ({$condition})";
        }
        return $this;
    }

    public function orderBy(string|array $orderBy, string $order = 'ASC'): self
    {
        $orderByFields = is_array($orderBy) ? $orderBy : [$orderBy];
        $this->orderBy = [];
        
        foreach ($orderByFields as $field) {
            $this->orderBy[] = $field . ' ' . strtoupper($order);
        }
        
        return $this;
    }

    public function addOrderBy(string|array $orderBy, string $order = 'ASC'): self
    {
        $orderByFields = is_array($orderBy) ? $orderBy : [$orderBy];
        
        foreach ($orderByFields as $field) {
            $this->orderBy[] = $field . ' ' . strtoupper($order);
        }
        
        return $this;
    }

    public function setMaxResults(int $maxResults): self
    {
        $this->maxResults = $maxResults;
        return $this;
    }

    public function setFirstResult(int $firstResult): self
    {
        $this->firstResult = $firstResult;
        return $this;
    }

    public function setParameter(string $key, mixed $value): self
    {
        $this->parameters[$key] = $value;
        return $this;
    }

    public function setParameters(array $parameters): self
    {
        $this->parameters = array_merge($this->parameters, $parameters);
        return $this;
    }

    public function getSQL(): string
    {
        if ($this->from === null) {
            throw new \InvalidArgumentException('FROM clause is required');
        }

        $sql = 'SELECT ';
        
        // SELECT clause
        if (empty($this->select)) {
            $sql .= '*';
        } else {
            $sql .= implode(', ', $this->select);
        }
        
        // FROM clause
        $sql .= ' FROM ' . $this->connection->quoteIdentifier($this->from);
        if ($this->fromAlias !== null) {
            $sql .= ' ' . $this->connection->quoteIdentifier($this->fromAlias);
        }
        
        // JOIN clauses
        foreach ($this->joins as $join) {
            $sql .= sprintf(
                ' %s JOIN %s %s ON %s',
                $join['type'],
                $this->connection->quoteIdentifier($join['table']),
                $this->connection->quoteIdentifier($join['alias']),
                $join['condition']
            );
        }
        
        // WHERE clause
        if (!empty($this->where)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }
        
        // GROUP BY clause
        if (!empty($this->groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }
        
        // HAVING clause
        if (!empty($this->having)) {
            $sql .= ' HAVING ' . implode(' AND ', $this->having);
        }
        
        // ORDER BY clause
        if (!empty($this->orderBy)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }
        
        // LIMIT and OFFSET
        if ($this->maxResults !== null) {
            $sql .= ' LIMIT ' . $this->maxResults;
            
            if ($this->firstResult !== null) {
                $sql .= ' OFFSET ' . $this->firstResult;
            }
        }
        
        return $sql;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    public function getResult(): array
    {
        $sql = $this->getSQL();
        return $this->connection->fetchAll($sql, $this->parameters);
    }

    public function getOneOrNullResult(): ?array
    {
        $sql = $this->getSQL();
        return $this->connection->fetchOne($sql, $this->parameters);
    }

    public function getSingleScalarResult(): mixed
    {
        $result = $this->getOneOrNullResult();
        
        if ($result === null) {
            throw ORMException::entityNotFound('scalar', 'result');
        }
        
        $values = array_values($result);
        return $values[0] ?? null;
    }

    /**
     * Create a subquery
     */
    public function createSubquery(): self
    {
        return new self($this->connection);
    }

    /**
     * Add a WHERE IN condition with subquery
     */
    public function whereIn(string $field, QueryBuilderInterface $subquery): self
    {
        $subquerySQL = $subquery->getSQL();
        $this->andWhere("{$field} IN ({$subquerySQL})");
        $this->parameters = array_merge($this->parameters, $subquery->getParameters());
        return $this;
    }

    /**
     * Add a WHERE EXISTS condition with subquery
     */
    public function whereExists(QueryBuilderInterface $subquery): self
    {
        $subquerySQL = $subquery->getSQL();
        $this->andWhere("EXISTS ({$subquerySQL})");
        $this->parameters = array_merge($this->parameters, $subquery->getParameters());
        return $this;
    }

    /**
     * Add a WHERE NOT EXISTS condition with subquery
     */
    public function whereNotExists(QueryBuilderInterface $subquery): self
    {
        $subquerySQL = $subquery->getSQL();
        $this->andWhere("NOT EXISTS ({$subquerySQL})");
        $this->parameters = array_merge($this->parameters, $subquery->getParameters());
        return $this;
    }

    /**
     * Reset the query builder
     */
    public function reset(): self
    {
        $this->select = [];
        $this->from = null;
        $this->fromAlias = null;
        $this->joins = [];
        $this->where = [];
        $this->groupBy = [];
        $this->having = [];
        $this->orderBy = [];
        $this->maxResults = null;
        $this->firstResult = null;
        $this->parameters = [];
        
        return $this;
    }

    /**
     * Resolve join condition automatically from entity metadata
     */
    private function resolveJoinCondition(string $propertyOrEntity, string $alias): string
    {
        if (!$this->metadataFactory || !$this->rootEntityClass) {
            throw new ORMException("Cannot resolve join condition: MetadataFactory or root entity class not set. Use explicit condition or set metadata factory.");
        }

        $rootMetadata = $this->metadataFactory->getMetadataFor($this->rootEntityClass);
        $associations = $rootMetadata->getAssociationMappings();

        // Try to find association by property name
        foreach ($associations as $association) {
            if ($association->getFieldName() === $propertyOrEntity) {
                if ($association->getType() === 'ManyToOne') {
                    // For ManyToOne: root.foreign_key = target.id
                    $joinColumn = $association->getJoinColumn();
                    $referencedColumn = $association->getReferencedColumnName();
                    return "{$this->fromAlias}.{$joinColumn} = {$alias}.{$referencedColumn}";
                } elseif ($association->getType() === 'OneToMany') {
                    // For OneToMany: root.id = target.foreign_key
                    $mappedBy = $association->getMappedBy();
                    $targetMetadata = $this->metadataFactory->getMetadataFor($association->getTargetEntity());
                    $targetAssociations = $targetMetadata->getAssociationMappings();

                    foreach ($targetAssociations as $targetAssociation) {
                        if ($targetAssociation->getFieldName() === $mappedBy) {
                            $joinColumn = $targetAssociation->getJoinColumn();
                            $rootIdColumn = $rootMetadata->getIdentifierFieldName();
                            return "{$this->fromAlias}.{$rootIdColumn} = {$alias}.{$joinColumn}";
                        }
                    }
                }
            }
        }

        throw new ORMException("Cannot resolve join condition for property '{$propertyOrEntity}' in entity '{$this->rootEntityClass}'");
    }

    /**
     * Resolve table name from property name or entity class
     */
    private function resolveTableName(string $propertyOrEntity): string
    {
        if (!$this->metadataFactory || !$this->rootEntityClass) {
            // If no metadata factory, assume it's already a table name
            return $propertyOrEntity;
        }

        $rootMetadata = $this->metadataFactory->getMetadataFor($this->rootEntityClass);
        $associations = $rootMetadata->getAssociationMappings();

        // Try to find association by property name
        foreach ($associations as $association) {
            if ($association->getFieldName() === $propertyOrEntity) {
                $targetEntityClass = $association->getTargetEntity();
                $targetMetadata = $this->metadataFactory->getMetadataFor($targetEntityClass);
                return $targetMetadata->getTableName();
            }
        }

        // If not found as property, assume it's already a table name or entity class
        if (class_exists($propertyOrEntity)) {
            $metadata = $this->metadataFactory->getMetadataFor($propertyOrEntity);
            return $metadata->getTableName();
        }

        return $propertyOrEntity;
    }
}
