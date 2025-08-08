<?php

namespace Fduarte42\Aurum\Repository;


use Fduarte42\Aurum\EntityManager;
use PDO;
use ReflectionException;

class Repository {
    private EntityManager $em;
    private string $class;

    public function __construct(EntityManager $em, string $class) {
        $this->em = $em;
        $this->class = $class;
    }
    
    /**
     * Safely escapes a database identifier (table or column name)
     * to prevent SQL injection attacks
     */
    private function escapeIdentifier(string $identifier): string {
        // Replace backticks with nothing and then wrap in backticks
        return '`' . str_replace('`', '', $identifier) . '`';
    }

    public function find($id) { return $this->em->find($this->class, $id); }

    /**
     * @throws ReflectionException
     */
    public function findAll(): array {
        $meta = $this->em->getClassMetadata($this->class);
        $sql = "SELECT * FROM " . $this->escapeIdentifier($meta->table);
        $stmt = $this->em->getConnection()->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $res = [];
        foreach ($rows as $r) $res[] = $this->em->hydrate($this->class, $r);
        return $res;
    }

    /**
     * @throws ReflectionException
     */
    public function findBy(array $criteria, ?array $order = null, ?int $limit = null): array {
        $meta = $this->em->getClassMetadata($this->class);
        $where = [];
        $params = [];
        foreach ($criteria as $k => $v) {
            $col = $meta->columnNames[$k] ?? $k;
            $where[] = $this->escapeIdentifier($col) . " = ?";
            $params[] = $v;
        }
        $sql = "SELECT * FROM " . $this->escapeIdentifier($meta->table) . 
               (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where));
        
        if ($order) {
            $orderParts = [];
            foreach ($order as $k => $dir) {
                $col = $meta->columnNames[$k] ?? $k;
                $direction = ($dir === 'DESC' ? 'DESC' : 'ASC');
                $orderParts[] = $this->escapeIdentifier($col) . ' ' . $direction;
            }
            $sql .= ' ORDER BY ' . implode(', ', $orderParts);
        }
        
        if ($limit) {
            $sql .= ' LIMIT ' . (int)$limit;
        }
        
        $stmt = $this->em->getConnection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $res = [];
        foreach ($rows as $r) $res[] = $this->em->hydrate($this->class, $r);
        return $res;
    }
}
