<?php

namespace Fduarte42\Aurum;

use Fduarte42\Aurum\Connection\ConnectionInterface;
use Fduarte42\Aurum\Metadata\ClassMetadata;
use Fduarte42\Aurum\Repository\Repository;
use Fduarte42\Aurum\UnitOfWork\UnitOfWork;
use ReflectionClass;
use PDO;
use ReflectionException;
use RuntimeException;
use Throwable;

final class EntityManager {
    private ConnectionInterface $conn;

    /** @var array<string, UnitOfWork> */
    private array $uows = [];

    /** @var array<string, ClassMetadata> */
    private array $metadataCache = [];

    public function __construct( ConnectionInterface $conn) {
        $this->conn = $conn;
        $this->uows['default'] = new UnitOfWork($conn, 'default');
    }

    public function createUnitOfWork(string $uowId = 'default'): UnitOfWork {
        if (isset($this->uows[$uowId])) throw new RuntimeException("UnitOfWork $uowId already exists");
        return $this->uows[$uowId] = new UnitOfWork($this->conn, $uowId);
    }

    /**
     * @throws ReflectionException
     */
    public function getClassMetadata(string $class): ClassMetadata {
        return $this->metadataCache[$class] ??= new ClassMetadata($class);
    }

    public function getUnitOfWork(string $uowId = 'default'): UnitOfWork
    {
        return $this->uows[$uowId];
    }

    public function persist(object $entity, string $uowId = 'default'): void
    {
        $this->uows[$uowId]->persist($entity);
    }

    public function remove(object $entity, string $uowId = 'default'): void
    {
        $this->uows[$uowId]->remove($entity);
    }

    /**
     * @throws Throwable
     */
    public function flush(string $uowId = 'default'): void
    {
        $this->uows[$uowId]->flush($this->metadataCache);
    }

    /**
     * @throws ReflectionException
     */
    public function find(string $class, $id, string $uowId = 'default'): ?object
    {
        $meta = $this->getClassMetadata($class);
        if (!$meta->idField) throw new RuntimeException("No id defined for $class");
        $sql = sprintf('SELECT * FROM %s WHERE %s = ? LIMIT 1', 
            $this->escapeIdentifier($meta->table), 
            $this->escapeIdentifier($meta->columnNames[$meta->idField])
        );
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $obj = $this->hydrate($class, $row);
        $this->uows[$uowId]->registerManaged($obj);
        return $obj;
    }
    
    /**
     * Safely escapes a database identifier (table or column name)
     * to prevent SQL injection attacks
     */
    private function escapeIdentifier(string $identifier): string {
        // Replace backticks with nothing and then wrap in backticks
        return '`' . str_replace('`', '', $identifier) . '`';
    }

    /**
     * @throws ReflectionException
     */
    public function hydrate(string $class, array $row): object
    {
        $meta = $this->getClassMetadata($class);
        $rc = new ReflectionClass($class);
        $obj = $rc->newInstanceWithoutConstructor();
        foreach ($meta->fields as $propertyName => $prop) {
            $col = $meta->columnNames[$propertyName];
            if (array_key_exists($col, $row)) {
                $prop->setValue($obj, $row[$col]);
            }
        }
        return $obj;
    }

    public function getRepository(string $class): Repository
    {
        return new Repository($this, $class);
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->conn;
    }
}
