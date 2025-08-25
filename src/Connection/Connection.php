<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Connection;

use Fduarte42\Aurum\Exception\ORMException;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Database connection implementation supporting SQLite and MariaDB
 */
class Connection implements ConnectionInterface
{
    private PDO $pdo;
    private string $platform;
    private array $savepointStack = [];
    private int $savepointCounter = 0;

    public function __construct(PDO $pdo, string $platform)
    {
        $this->pdo = $pdo;
        $this->platform = strtolower($platform);
        
        // Set error mode to exceptions
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Set fetch mode to associative arrays
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function execute(string $sql, array $parameters = []): PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($parameters);
            return $stmt;
        } catch (PDOException $e) {
            throw ORMException::queryFailed($sql, $e->getMessage());
        }
    }

    public function fetchOne(string $sql, array $parameters = []): ?array
    {
        $stmt = $this->execute($sql, $parameters);
        $result = $stmt->fetch();
        return $result === false ? null : $result;
    }

    public function fetchAll(string $sql, array $parameters = []): array
    {
        $stmt = $this->execute($sql, $parameters);
        return $stmt->fetchAll();
    }

    public function beginTransaction(): void
    {
        if ($this->inTransaction()) {
            throw ORMException::transactionAlreadyActive();
        }
        
        try {
            $this->pdo->beginTransaction();
        } catch (PDOException $e) {
            throw ORMException::connectionFailed($e->getMessage());
        }
    }

    public function commit(): void
    {
        if (!$this->inTransaction()) {
            throw ORMException::transactionNotActive();
        }
        
        try {
            $this->pdo->commit();
            $this->savepointStack = [];
            $this->savepointCounter = 0;
        } catch (PDOException $e) {
            throw ORMException::connectionFailed($e->getMessage());
        }
    }

    public function rollback(): void
    {
        if (!$this->inTransaction()) {
            throw ORMException::transactionNotActive();
        }
        
        try {
            $this->pdo->rollBack();
            $this->savepointStack = [];
            $this->savepointCounter = 0;
        } catch (PDOException $e) {
            throw ORMException::connectionFailed($e->getMessage());
        }
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    public function createSavepoint(string $name): void
    {
        if (!$this->inTransaction()) {
            throw ORMException::transactionNotActive();
        }

        if (!$this->supportsSavepoints()) {
            throw ORMException::savepointNotSupported();
        }

        if (in_array($name, $this->savepointStack, true)) {
            throw ORMException::invalidSavepointName($name);
        }

        $sql = $this->getSavepointSQL('CREATE', $name);
        $this->execute($sql);
        $this->savepointStack[] = $name;
    }

    public function releaseSavepoint(string $name): void
    {
        if (!$this->inTransaction()) {
            throw ORMException::transactionNotActive();
        }

        if (!$this->supportsSavepoints()) {
            throw ORMException::savepointNotSupported();
        }

        $index = array_search($name, $this->savepointStack, true);
        if ($index === false) {
            throw ORMException::invalidSavepointName($name);
        }

        $sql = $this->getSavepointSQL('RELEASE', $name);
        $this->execute($sql);
        
        // Remove this savepoint and all nested ones
        $this->savepointStack = array_slice($this->savepointStack, 0, $index);
    }

    public function rollbackToSavepoint(string $name): void
    {
        if (!$this->inTransaction()) {
            throw ORMException::transactionNotActive();
        }

        if (!$this->supportsSavepoints()) {
            throw ORMException::savepointNotSupported();
        }

        $index = array_search($name, $this->savepointStack, true);
        if ($index === false) {
            throw ORMException::invalidSavepointName($name);
        }

        $sql = $this->getSavepointSQL('ROLLBACK', $name);
        $this->execute($sql);
        
        // Remove all savepoints after this one
        $this->savepointStack = array_slice($this->savepointStack, 0, $index + 1);
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function quote(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        
        return $this->pdo->quote((string) $value);
    }

    public function quoteIdentifier(string $identifier): string
    {
        return match ($this->platform) {
            'sqlite' => '"' . str_replace('"', '""', $identifier) . '"',
            'mysql', 'mariadb' => '`' . str_replace('`', '``', $identifier) . '`',
            default => '"' . str_replace('"', '""', $identifier) . '"',
        };
    }

    private function supportsSavepoints(): bool
    {
        return in_array($this->platform, ['sqlite', 'mysql', 'mariadb'], true);
    }

    private function getSavepointSQL(string $operation, string $name): string
    {
        $quotedName = $this->quoteIdentifier($name);
        
        return match ($operation) {
            'CREATE' => "SAVEPOINT {$quotedName}",
            'RELEASE' => match ($this->platform) {
                'sqlite' => "RELEASE SAVEPOINT {$quotedName}",
                'mysql', 'mariadb' => "RELEASE SAVEPOINT {$quotedName}",
                default => "RELEASE SAVEPOINT {$quotedName}",
            },
            'ROLLBACK' => "ROLLBACK TO SAVEPOINT {$quotedName}",
            default => throw new \InvalidArgumentException("Unknown savepoint operation: {$operation}"),
        };
    }

    public function generateSavepointName(): string
    {
        return 'sp_' . (++$this->savepointCounter);
    }
}
