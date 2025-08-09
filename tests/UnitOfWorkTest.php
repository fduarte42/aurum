<?php

use Fduarte42\Aurum\Connection\ConnectionInterface;
use Fduarte42\Aurum\UnitOfWork\UnitOfWork;

// Simple entity used only for the tests
class User
{
    public ?int $id = null;

    public string $name;

    public function __construct( string $name )
    {
        $this->name = $name;
    }
}

// Dummy metadata builder – mimics what a real ORM would provide
function buildUserMetadata(): object
{
    return (object) [
        'table'       => 'users',
        'idField'     => 'id',
        'idGenerated' => true,
        'fields'      => [
            'id'   => new ReflectionProperty( User::class, 'id' ),
            'name' => new ReflectionProperty( User::class, 'name' ),
        ],
        'columnNames' => [
            'id'   => 'id',
            'name' => 'name',
        ],
        'relations'   => [],
    ];
}

// Helper to create a fresh in-memory connection
function makeConnection(): ConnectionInterface
{
    $pdo = new PDO( 'sqlite::memory:' );
    $pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

    // Create the table
    $pdo->exec(
        "
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL
        )
    "
    );

    return new class($pdo) implements ConnectionInterface {
        private PDO $pdo;

        public function __construct( PDO $pdo )
        {
            $this->pdo = $pdo;
        }

        public function beginTransaction( string $uowId = 'default' ): void
        {
            $this->pdo->beginTransaction();
        }

        public function commit( string $uowId = 'default' ): void
        {
            $this->pdo->commit();
        }

        public function rollBack( string $uowId = 'default' ): void
        {
            $this->pdo->rollBack();
        }

        public function prepare( string $sql ): false|PDOStatement
        {
            return $this->pdo->prepare( $sql );
        }

        public function lastInsertId(): string
        {
            return $this->pdo->lastInsertId();
        }

        // Extra helper for tests to fetch rows
        public function fetchUserById( int $id ): ?array
        {
            $stmt = $this->pdo->prepare( "SELECT * FROM users WHERE id = ?" );
            $stmt->execute( [$id] );
            $row = $stmt->fetch( PDO::FETCH_ASSOC );
            return $row ?: null;
        }

        public function exec( string $sql ): int|false
        {
            return $this->pdo->exec( $sql );
        }

        public function query( string $sql ): false|PDOStatement
        {
            return $this->pdo->query( $sql );
        }

        public function inTransaction(): bool
        {
            return $this->pdo->inTransaction();
        }
    };
}

beforeEach( function () {
    $this->conn = makeConnection();
    $this->uow = new UnitOfWork( $this->conn );
} );

it( 'persists a new entity', function () {
    $user = new User( 'Alice' );
    $this->uow->persist( $user );

    expect( $this->uow )->toHaveProperty( 'new' )->and(
        $this->uow->isNew( $user )
    );

    $this->uow->flush( [User::class => buildUserMetadata()] );

    expect( $user->id )->toBeGreaterThan( 0 )
        ->and( $this->uow->isNew( $user ) )->toBeFalse();

    $row = $this->conn->fetchUserById( $user->id );
    expect( $row )->not()->toBeNull()
        ->and( $row[ 'name' ] )->toEqual( 'Alice' );
} );

it( 'updates a managed entity when changed', function () {
    $user = new User( 'Bob' );
    $this->uow->persist( $user );
    $this->uow->flush( [User::class => buildUserMetadata()] );

    $this->uow->registerManaged( $user );
    $user->name = 'Robert';
    $this->uow->flush( [User::class => buildUserMetadata()] );

    $row = $this->conn->fetchUserById( $user->id );
    expect( $row[ 'name' ] )->toEqual( 'Robert' );
} );

it( 'removes an entity and cascades delete if configured', function () {
    $user = new User( 'Charlie' );
    $this->uow->persist( $user );
    $this->uow->flush( [User::class => buildUserMetadata()] );

    $this->uow->registerManaged( $user );
    $this->uow->remove( $user );
    $this->uow->flush( [User::class => buildUserMetadata()] );

    $row = $this->conn->fetchUserById( $user->id );
    expect( $row )->toBeNull();
} );
