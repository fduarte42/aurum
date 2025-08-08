<?php

namespace Fduarte42\Aurum\Connection;

use PDO;

class PdoConnection
{
    private PDO $pdo;

    /** @var array<string, string|false> */
    private array $transactionActiveForUow = [];

    public function __construct(string $dsn, string $user = '', string $pass = '', array $opts = [])
    {
        $default = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        $this->pdo = new PDO($dsn, $user, $pass, $opts + $default);
    }

    public function beginTransaction(string $uowId = 'default'): void
    {
        // always start a new transaction
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }

        if ($this->transactionActiveForUow[$uowId] ?? false) {
            $this->transactionActiveForUow[$uowId] = $this->getSavepointName($uowId, $this->transactionActiveForUow[$uowId]);;
            $this->pdo->exec("SAVEPOINT {$this->transactionActiveForUow[$uowId]}");
        }
    }

    private function isAnySavePointActive(): bool {
        return array_reduce(
            $this->transactionActiveForUow,
            fn($transactionActive, $uowTransactionActive) => $transactionActive || ($uowTransactionActive !== false),
            false
        );
    }

    public function commit(string $uowId = 'default'): void
    {
        if ($this->transactionActiveForUow[$uowId] ?? false) {
            throw new \RuntimeException("No active transaction for UnitOfWork: $uowId");
        }

        $this->transactionActiveForUow[$uowId] = false;
        $this->pdo->exec("RELEASE SAVEPOINT {$this->transactionActiveForUow[$uowId]}");

        if ( ! $this->isAnySavePointActive() ) {
            $this->pdo->commit();
        }
    }

    public function rollBack(string $uowId = 'default'): void
    {
        if ($this->transactionActiveForUow[$uowId] ?? false) {
            throw new \RuntimeException("No active transaction for UnitOfWork: $uowId");
        }

        $this->transactionActiveForUow[$uowId] = false;
        $this->pdo->exec("ROLLBACK TO SAVEPOINT {$this->transactionActiveForUow[$uowId]}");

        if ( ! $this->isAnySavePointActive() ) {
            $this->pdo->rollBack();
        }
    }

    private function getSavepointName(string $uowId, int $index): string
    {
        return 'SP_' . preg_replace('/\W+/', '_', $uowId) . '_' . $index;
    }

    // PDO Wrapper
    public function prepare(string $sql): false|\PDOStatement { return $this->pdo->prepare($sql); }
    public function lastInsertId(): string { return $this->pdo->lastInsertId(); }
    public function exec(string $sql): int|false { return $this->pdo->exec($sql); }
    public function query(string $sql): false|\PDOStatement { return $this->pdo->query($sql); }
    public function inTransaction(): bool { return $this->pdo->inTransaction(); }
}
