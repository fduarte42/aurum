<?php

namespace Fduarte42\Aurum\UnitOfWork;


use Fduarte42\Aurum\Connection\ConnectionInterface;
use ReflectionClass;
use SplObjectStorage;
use Throwable;

class UnitOfWork
{
    private ConnectionInterface $conn;

    private string $uowId;

    private SplObjectStorage $new;

    private SplObjectStorage $managedObjects;

    /** @var array<string,array> */ // oid => snapshot
    private array $snapshots = [];

    private SplObjectStorage $removed ;

    /**
     * Safely escapes a database identifier (table or column name)
     * to prevent SQL injection attacks
     */
    private function escapeIdentifier( string $identifier ): string
    {
        // Replace backticks with nothing and then wrap in backticks
        return '`' . str_replace( '`', '', $identifier ) . '`';
    }

    public function __construct( ConnectionInterface $conn, string $uowId = 'default' )
    {
        $this->conn = $conn;
        $this->uowId = $uowId;
    }

    public function persist( object $entity ): void
    {
        if ( $this->isManaged( $entity ) || $this->isNew( $entity ) ) {
            return;
        }
        $this->new->attach( $entity );
    }

    public function remove( object $entity ): void
    {
        if ($this->isNew( $entity )) {
            $this->new->detach( $entity );
        } elseif ($this->isManaged( $entity )) {
            $this->managedObjects->detach( $entity );
            $this->removed->attach($entity);
        }
    }

    public function registerManaged( object $entity ): void
    {
        $this->managedObjects->attach( $entity );
        $snapshot = $this->snapshot( $entity );
        $this->snapshots[ $this->managedObjects->getHash($entity) ] = $snapshot;
    }

    private function snapshot( object $entity ): array
    {
        $r = [];
        $rc = new ReflectionClass( $entity );
        foreach ( $rc->getProperties() as $p ) {
            $r[ $p->getName() ] = $p->getValue( $entity );
        }
        return $r;
    }

    private function isManaged( object $entity ): bool
    {
        return $this->managedObjects->contains( $entity );
    }

    private function isNew( object $entity ): bool
    {
        return $this->new->contains( $entity );
    }

    public function clear(): void
    {
        $this->managedObjects = new SplObjectStorage();
        $this->snapshots = [];
        $this->removed = new SplObjectStorage();
        $this->new = new SplObjectStorage();
    }

    /**
     * @throws Throwable
     */
    public function flush( array $classesMetadata ): void
    {
        $this->conn->beginTransaction( $this->uowId );
        try {
            // Process relations for new entities
            foreach ( $this->new as $entity ) {
                $this->processRelations( $entity, $classesMetadata );
            }

            // Process relations for managed entities
            foreach ( $this->managedObjects as $entity ) {
                $this->processRelations( $entity, $classesMetadata );
            }

            // INSERT new
            foreach ( $this->new as $entity ) {
                $this->doInsert( $entity, $classesMetadata );
            }

            // UPDATE managed (diff)
            foreach ( $this->managedObjects as $entity ) {
                $originalSnapshot = $this->snapshots[$this->managedObjects->getHash( $entity )];
                $currentSnapshot = $this->snapshot( $entity );
                $this->doUpdateIfNeeded( $entity, $originalSnapshot, $currentSnapshot, $classesMetadata );
            }

            // DELETE removed
            foreach ( $this->removed as $entity ) {
                $this->doDelete( $entity, $classesMetadata );
            }

            $this->conn->commit( $this->uowId );
            $this->clear();
        } catch ( Throwable $ex ) {
            $this->conn->rollBack( $this->uowId );
            throw $ex;
        }
    }

    private function processRelations( object $entity, array $classesMetadata ): void
    {
        $class = get_class( $entity );
        if ( ! isset( $classesMetadata[ $class ] ) ) {
            return;
        }

        $meta = $classesMetadata[ $class ];
        if ( empty( $meta->relations ) ) {
            return;
        }

        foreach ( $meta->relations as $propertyName => $relation ) {
            $prop = $meta->fields[ $propertyName ];
            $value = $prop->getValue( $entity );

            if ( $relation[ 'type' ] === 'oneToMany' && is_array( $value ) ) {
                foreach ( $value as $relatedEntity ) {
                    if ( is_object( $relatedEntity ) ) {
                        $this->persist( $relatedEntity );
                    }
                }
            } elseif ( $relation[ 'type' ] === 'manyToOne' && is_object( $value ) ) {
                $this->persist( $value );
            }
        }
    }

    private function doInsert( object $entity, array $classesMetadata ): void
    {
        $meta = $classesMetadata[ get_class( $entity ) ];
        $cols = [];
        $vals = [];
        $params = [];
        foreach ( $meta->fields as $propName => $prop ) {
            // Skip ID field if it's generated
            if ( $propName === $meta->idField && $meta->idGenerated ) {
                continue;
            }

            // Skip relation fields
            if ( isset( $meta->relations[ $propName ] ) ) {
                continue;
            }

            $val = $prop->getValue( $entity );

            // Handle foreign keys for ManyToOne relations
            if ( preg_match( '/(.+)_id$/', $propName, $matches ) && isset( $meta->relations[ $matches[ 1 ] ] ) ) {
                $relationProp = $meta->fields[ $matches[ 1 ] ];
                $relatedEntity = $relationProp->getValue( $entity );
                if ( $relatedEntity ) {
                    $relatedMeta = $classesMetadata[ get_class( $relatedEntity ) ];
                    $relatedIdProp = $relatedMeta->fields[ $relatedMeta->idField ];
                    $val = $relatedIdProp->getValue( $relatedEntity );
                }
            }

            $cols[] = $this->escapeIdentifier( $meta->columnNames[ $propName ] );
            $vals[] = '?';
            $params[] = $val;
        }
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->escapeIdentifier( $meta->table ),
            implode( ',', $cols ),
            implode( ',', $vals )
        );
        $stmt = $this->conn->prepare( $sql );
        $stmt->execute( $params );
        if ( $meta->idField && $meta->idGenerated ) {
            $id = $this->conn->lastInsertId();
            $meta->fields[ $meta->idField ]->setValue( $entity, is_numeric( $id ) ? (int) $id : $id );
        }
    }

    private function doUpdateIfNeeded(
        object $entity,
        array  $originalSnapshot,
        array  $currentSnapshot,
        array $classesMetadata
    ): void {
        $meta = $classesMetadata[ get_class( $entity ) ];
        if ( ! $meta->idField ) {
            return;
        }
        $idProp = $meta->fields[ $meta->idField ];
        $id = $idProp->getValue( $entity );
        if ( $id === null ) {
            return;
        }

        $changes = [];
        $params = [];
        foreach ( $meta->fields as $propName => $prop ) {
            if ( $propName === $meta->idField ) {
                continue;
            }

            // Skip relation properties
            if ( isset( $meta->relations[ $propName ] ) ) {
                continue;
            }

            $cur = $currentSnapshot[ $propName ] ?? null;
            $old = $originalSnapshot[ $propName ] ?? null;
            if ( $cur !== $old ) {
                $changes[] = $this->escapeIdentifier( $meta->columnNames[ $propName ] ) . ' = ?';
                $params[] = $cur;
            }
        }
        if ( empty( $changes ) ) {
            return;
        }
        $params[] = $id;
        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = ?',
            $this->escapeIdentifier( $meta->table ),
            implode( ',', $changes ),
            $this->escapeIdentifier( $meta->columnNames[ $meta->idField ] )
        );
        $stmt = $this->conn->prepare( $sql );
        $stmt->execute( $params );
    }

    private function doDelete( object $entity, array $classesMetadata ): void
    {
        $meta = $classesMetadata[ get_class( $entity ) ];
        if ( ! $meta->idField ) {
            return;
        }
        $idProp = $meta->fields[ $meta->idField ];
        $id = $idProp->getValue( $entity );
        if ( $id === null ) {
            return;
        }

        // Handle cascade delete for relations
        if ( ! empty( $meta->relations ) ) {
            foreach ( $meta->relations as $propertyName => $relation ) {
                if ( ! $relation[ 'cascade' ] ) {
                    continue;
                }

                $prop = $meta->fields[ $propertyName ];
                $value = $prop->getValue( $entity );

                if ( $relation[ 'type' ] === 'oneToMany' && is_array( $value ) ) {
                    foreach ( $value as $relatedEntity ) {
                        if ( is_object( $relatedEntity ) ) {
                            $this->remove( $relatedEntity );
                            $this->doDelete( $relatedEntity, $classesMetadata );
                        }
                    }
                } elseif ( $relation[ 'type' ] === 'manyToOne' && is_object( $value ) ) {
                    $this->remove( $value );
                    $this->doDelete( $value, $classesMetadata );
                }
            }
        }

        $sql = sprintf(
            'DELETE FROM %s WHERE %s = ?',
            $this->escapeIdentifier( $meta->table ),
            $this->escapeIdentifier( $meta->columnNames[ $meta->idField ] )
        );
        $stmt = $this->conn->prepare( $sql );
        $stmt->execute( [$id] );
    }
}
