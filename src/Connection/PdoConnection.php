<?php

namespace Fduarte42\Aurum\Connection;


use PDO;
use PDOStatement;
use RuntimeException;

class PdoConnection implements ConnectionInterface
{
    private PDO $pdo;

    private array $savePointOrder;

    /** @var array<string, string|false> */
    private array $transactionActiveForUow = [];

    public function __construct( string $dsn, string $user = '', string $pass = '', array $opts = [] )
    {
        $default = [
            PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $this->pdo = new PDO( $dsn, $user, $pass, $opts + $default );
    }

    public function beginTransaction( string $uowId = 'default' ): void
    {
        if ( ! $this->pdo->inTransaction() ) {
            $this->pdo->beginTransaction();
        }

        $this->createSavepoint( $uowId );
    }

    public function commit( string $uowId = 'default' ): void
    {
        if ( empty( $this->transactionActiveForUow[ $uowId ] ) ) {
            throw new RuntimeException( "No active transaction for UnitOfWork: $uowId" );
        }

        $this->releaseSavepoint( $uowId );

        if ( ! $this->isAnySavePointActive() ) {
            $this->pdo->commit();
        }
    }

    public function rollBack( string $uowId = 'default' ): void
    {
        if ( empty( $this->transactionActiveForUow[ $uowId ] ) ) {
            throw new RuntimeException( "No active transaction for UnitOfWork: $uowId" );
        }

        $this->rollbackSavepoint( $uowId );

        if ( ! $this->isAnySavePointActive() ) {
            $this->pdo->rollBack();
        }
    }

    private function createSavepoint( string $uowId ): void
    {
        if ( ! empty( $this->transactionActiveForUow[ $uowId ] ) ) {
            throw new RuntimeException( "Active save point for UnitOfWork '$uowId' already exists" );
        }
        $savepoint = $this->getSavepointName( $uowId );
        $this->pdo->exec( "SAVEPOINT $savepoint" );
        $this->transactionActiveForUow[ $uowId ] = $savepoint;
        $this->savePointOrder[] = $savepoint;
    }

    private function releaseSavepoint( string $uowId ): void
    {
        if ( empty( $this->transactionActiveForUow[ $uowId ] ) ) {
            throw new RuntimeException( "No active save point for UnitOfWork '$uowId'" );
        }
        $savepoint = $this->transactionActiveForUow[ $uowId ];
        $this->pdo->exec( "RELEASE SAVEPOINT $savepoint" );
        $this->transactionActiveForUow[ $uowId ] = false;

        if ( ! in_array( $savepoint, $this->savePointOrder ) ) {
            throw new RuntimeException( "Illegal save point order for UnitOfWork '$uowId'" );
        }
        while ( end( $this->savePointOrder ) !== $savepoint ) {
            unset( $this->savePointOrder[ array_key_last( $this->savePointOrder ) ] );
        }
    }

    private function rollbackSavepoint( string $uowId ): void
    {
        if ( empty( $this->transactionActiveForUow[ $uowId ] ) ) {
            throw new RuntimeException( "No active save point for UnitOfWork '$uowId'" );
        }
        $savepoint = $this->transactionActiveForUow[ $uowId ];
        $this->pdo->exec( "ROLLBACK TO SAVEPOINT $savepoint" );

        $this->releaseSavepoint( $uowId );
    }

    private function isAnySavePointActive(): bool
    {
        return array_any( $this->transactionActiveForUow, fn( $sp ) => $sp !== false );
    }

    private function getSavepointName( string $uowId ): string
    {
        return 'SP_' . preg_replace( '/\W+/', '_', $uowId );
    }

    // PDO Wrapper
    public function prepare( string $sql ): false|PDOStatement
    {
        return $this->pdo->prepare( $sql );
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
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
}
