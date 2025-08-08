<?php

namespace Fduarte42\Aurum\UnitOfWork;

use Fduarte42\Aurum\Connection\PdoConnection;
use ReflectionClass;

class UnitOfWork {
    private PdoConnection $conn;
    private string $uowId;
    /** @var array<object> */
    private array $new = [];
    /** @var array<string,object> */ // oid => object
    private array $managedObjects = [];
    /** @var array<string,array> */ // oid => original snapshot
    private array $managed = [];
    /** @var array<string,array> */ // oid => snapshot
    private array $snapshots = [];
    /** @var array<object> */
    private array $removed = [];
    
    /**
     * Safely escapes a database identifier (table or column name)
     * to prevent SQL injection attacks
     */
    private function escapeIdentifier(string $identifier): string {
        // Replace backticks with nothing and then wrap in backticks
        return '`' . str_replace('`', '', $identifier) . '`';
    }

    public function __construct(PdoConnection $conn, string $uowId = 'default') { 
        $this->conn = $conn; 
        $this->uowId = $uowId;
    }

    public function persist(object $entity): void {
        if ($this->isManaged($entity) || $this->isNew($entity)) return;
        $this->new[spl_object_hash($entity)] = $entity;
    }

    public function remove(object $entity): void {
        $oid = spl_object_hash($entity);
        unset($this->new[$oid]);
        $this->removed[$oid] = $entity;
    }

    public function registerManaged(object $entity): void {
        $oid = spl_object_hash($entity);
        $this->managedObjects[$oid] = $entity;
        $snapshot = $this->snapshot($entity);
        $this->managed[$oid] = $snapshot;
        $this->snapshots[$oid] = $snapshot;
    }

    private function snapshot(object $e): array {
        $r = [];
        $rc = new ReflectionClass($e);
        foreach ($rc->getProperties() as $p) {
            $r[$p->getName()] = $p->getValue($e);
        }
        return $r;
    }

    private function isManaged(object $e): bool {
        return isset($this->managed[spl_object_hash($e)]);
    }
    private function isNew(object $e): bool {
        return isset($this->new[spl_object_hash($e)]);
    }

    public function flush(array $classesMetadata): void {
        $this->conn->beginTransaction($this->uowId);
        try {
            // Update snapshots before processing
            foreach ($this->managedObjects as $oid => $entity) {
                $this->snapshots[$oid] = $this->snapshot($entity);
            }
            
            // Process relations for new entities
            foreach ($this->new as $entity) {
                $this->processRelations($entity, $classesMetadata);
            }
            
            // Process relations for managed entities
            foreach ($this->managedObjects as $entity) {
                $this->processRelations($entity, $classesMetadata);
            }
            
            // INSERT new
            foreach ($this->new as $entity) {
                $this->doInsert($entity, $classesMetadata);
            }
            
            // UPDATE managed (diff)
            foreach ($this->managed as $oid => $originalSnapshot) {
                $entity = $this->getObjectByOid($oid);
                if (!$entity) continue;
                $currentSnapshot = $this->snapshots[$oid] ?? [];
                $this->doUpdateIfNeeded($entity, $originalSnapshot, $currentSnapshot, $classesMetadata);
            }
            
            // DELETE removed
            foreach ($this->removed as $entity) {
                $this->doDelete($entity, $classesMetadata);
            }
            
            $this->conn->commit($this->uowId);

            // post flush bookkeeping
            foreach ($this->new as $entity) {
                $this->registerManaged($entity);
            }
            $this->new = [];
            
            foreach ($this->removed as $entity) {
                $oid = spl_object_hash($entity);
                unset($this->managed[$oid]);
                unset($this->managedObjects[$oid]);
                unset($this->snapshots[$oid]);
            }
            $this->removed = [];
            
            // refresh original snapshots for managed
            foreach ($this->managedObjects as $oid => $entity) {
                $this->managed[$oid] = $this->snapshot($entity);
            }
        } catch (\Throwable $ex) {
            $this->conn->rollBack($this->uowId);
            throw $ex;
        }
    }
    
    private function processRelations(object $entity, array $classesMetadata): void {
        $class = get_class($entity);
        if (!isset($classesMetadata[$class])) return;
        
        $meta = $classesMetadata[$class];
        if (empty($meta->relations)) return;
        
        foreach ($meta->relations as $propertyName => $relation) {
            $prop = $meta->fields[$propertyName];
            $value = $prop->getValue($entity);
            
            if ($relation['type'] === 'oneToMany' && is_array($value)) {
                foreach ($value as $relatedEntity) {
                    if (is_object($relatedEntity)) {
                        $this->persist($relatedEntity);
                    }
                }
            } elseif ($relation['type'] === 'manyToOne' && is_object($value)) {
                $this->persist($value);
            }
        }
    }

    private function doInsert(object $entity, array $metadatas): void {
        $meta = $metadatas[get_class($entity)];
        $cols = [];
        $vals = [];
        $params = [];
        foreach ($meta->fields as $propName => $prop) {
            // Skip ID field if it's generated
            if ($propName === $meta->idField && $meta->idGenerated) continue;
            
            // Skip relation fields
            if (isset($meta->relations[$propName])) continue;
            
            $val = $prop->getValue($entity);
            
            // Handle foreign keys for ManyToOne relations
            if (preg_match('/(.+)_id$/', $propName, $matches) && isset($meta->relations[$matches[1]])) {
                $relationProp = $meta->fields[$matches[1]];
                $relatedEntity = $relationProp->getValue($entity);
                if ($relatedEntity) {
                    $relatedMeta = $metadatas[get_class($relatedEntity)];
                    $relatedIdProp = $relatedMeta->fields[$relatedMeta->idField];
                    $val = $relatedIdProp->getValue($relatedEntity);
                }
            }
            
            $cols[] = $this->escapeIdentifier($meta->columnNames[$propName]);
            $vals[] = '?';
            $params[] = $val;
        }
        $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $this->escapeIdentifier($meta->table), implode(',', $cols), implode(',', $vals));
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        if ($meta->idField && $meta->idGenerated) {
            $id = $this->conn->lastInsertId();
            $meta->fields[$meta->idField]->setValue($entity, is_numeric($id) ? (int)$id : $id);
        }
    }

    private function doUpdateIfNeeded(object $entity, array $originalSnapshot, array $currentSnapshot, array $metadatas): void {
        $meta = $metadatas[get_class($entity)];
        if (!$meta->idField) return;
        $idProp = $meta->fields[$meta->idField];
        $id = $idProp->getValue($entity);
        if ($id === null) return;

        $changes = [];
        $params = [];
        foreach ($meta->fields as $propName => $prop) {
            if ($propName === $meta->idField) continue;
            
            // Skip relation properties
            if (isset($meta->relations[$propName])) continue;
            
            $cur = $currentSnapshot[$propName] ?? null;
            $old = $originalSnapshot[$propName] ?? null;
            if ($cur !== $old) {
                $changes[] = $this->escapeIdentifier($meta->columnNames[$propName]) . ' = ?';
                $params[] = $cur;
            }
        }
        if (empty($changes)) return;
        $params[] = $id;
        $sql = sprintf('UPDATE %s SET %s WHERE %s = ?', 
            $this->escapeIdentifier($meta->table), 
            implode(',', $changes), 
            $this->escapeIdentifier($meta->columnNames[$meta->idField])
        );
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
    }

    private function doDelete(object $entity, array $metadatas): void {
        $meta = $metadatas[get_class($entity)];
        if (!$meta->idField) return;
        $idProp = $meta->fields[$meta->idField];
        $id = $idProp->getValue($entity);
        if ($id === null) return;
        
        // Handle cascade delete for relations
        if (!empty($meta->relations)) {
            foreach ($meta->relations as $propertyName => $relation) {
                if (!$relation['cascade']) continue;
                
                $prop = $meta->fields[$propertyName];
                $value = $prop->getValue($entity);
                
                if ($relation['type'] === 'oneToMany' && is_array($value)) {
                    foreach ($value as $relatedEntity) {
                        if (is_object($relatedEntity)) {
                            $this->remove($relatedEntity);
                            $this->doDelete($relatedEntity, $metadatas);
                        }
                    }
                } elseif ($relation['type'] === 'manyToOne' && is_object($value)) {
                    $this->remove($value);
                    $this->doDelete($value, $metadatas);
                }
            }
        }
        
        $sql = sprintf('DELETE FROM %s WHERE %s = ?', 
            $this->escapeIdentifier($meta->table), 
            $this->escapeIdentifier($meta->columnNames[$meta->idField])
        );
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
    }

    // helper: find object by spl_object_hash in managed or new
    private function getObjectByOid(string $oid): ?object {
        if (isset($this->managedObjects[$oid])) {
            return $this->managedObjects[$oid];
        }
        
        if (isset($this->new[$oid])) {
            return $this->new[$oid];
        }
        
        return null;
    }
}
