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
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }

        if (empty($this->transactionActiveForUow[$uowId])) {
            $savepoint = $this->getSavepointName($uowId);
            $this->pdo->exec("SAVEPOINT $savepoint");
            $this->transactionActiveForUow[$uowId] = $savepoint;
        }
    }

    public function commit(string $uowId = 'default'): void
    {
        if (empty($this->transactionActiveForUow[$uowId])) {
            throw new \RuntimeException("No active transaction for UnitOfWork: $uowId");
        }

        $savepoint = $this->transactionActiveForUow[$uowId];
        $this->pdo->exec("RELEASE SAVEPOINT $savepoint");
        $this->transactionActiveForUow[$uowId] = false;

        if (!$this->isAnySavePointActive()) {
            $this->pdo->commit();
        }
    }

    public function rollBack(string $uowId = 'default'): void
    {
        if (empty($this->transactionActiveForUow[$uowId])) {
            throw new \RuntimeException("No active transaction for UnitOfWork: $uowId");
        }

        $savepoint = $this->transactionActiveForUow[$uowId];
        $this->pdo->exec("ROLLBACK TO SAVEPOINT $savepoint");
        $this->transactionActiveForUow[$uowId] = false;

        if (!$this->isAnySavePointActive()) {
            $this->pdo->rollBack();
        }
    }

    private function isAnySavePointActive(): bool
    {
        return array_any($this->transactionActiveForUow, fn($sp) => $sp !== false);
    }

    private function getSavepointName(string $uowId): string
    {
        return 'SP_' . preg_replace('/\W+/', '_', $uowId);
    }

    // PDO Wrapper
    public function prepare(string $sql): false|\PDOStatement { return $this->pdo->prepare($sql); }
    public function lastInsertId(): string { return $this->pdo->lastInsertId(); }
    public function exec(string $sql): int|false { return $this->pdo->exec($sql); }
    public function query(string $sql): false|\PDOStatement { return $this->pdo->query($sql); }
    public function inTransaction(): bool { return $this->pdo->inTransaction(); }
}
