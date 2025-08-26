<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Connection;

use Fduarte42\Aurum\Driver\DatabaseDriverInterface;
use Fduarte42\Aurum\Exception\ORMException;
use PDO;
use PDOStatement;

/**
 * Database connection implementation using the driver pattern
 *
 * This class provides a backward-compatible interface while delegating
 * database-specific operations to the appropriate driver implementation.
 */
class Connection implements ConnectionInterface
{
    private DatabaseDriverInterface $driver;
    private array $savepointStack = [];

    public function __construct(DatabaseDriverInterface $driver)
    {
        $this->driver = $driver;
    }

    public function getPdo(): PDO
    {
        return $this->driver->getPdo();
    }

    public function execute(string $sql, array $parameters = []): PDOStatement
    {
        return $this->driver->execute($sql, $parameters);
    }

    public function fetchOne(string $sql, array $parameters = []): ?array
    {
        return $this->driver->fetchOne($sql, $parameters);
    }

    public function fetchAll(string $sql, array $parameters = []): array
    {
        return $this->driver->fetchAll($sql, $parameters);
    }

    public function beginTransaction(): void
    {
        if ($this->inTransaction()) {
            throw ORMException::transactionAlreadyActive();
        }

        $this->driver->beginTransaction();
    }

    public function commit(): void
    {
        if (!$this->inTransaction()) {
            throw ORMException::transactionNotActive();
        }

        $this->driver->commit();
        $this->savepointStack = [];
    }

    public function rollback(): void
    {
        if (!$this->inTransaction()) {
            throw ORMException::transactionNotActive();
        }

        $this->driver->rollback();
        $this->savepointStack = [];
    }

    public function inTransaction(): bool
    {
        return $this->driver->inTransaction();
    }

    public function createSavepoint(string $name): void
    {
        if (!$this->inTransaction()) {
            throw ORMException::transactionNotActive();
        }

        if (!$this->driver->supportsSavepoints()) {
            throw ORMException::savepointNotSupported();
        }

        if (in_array($name, $this->savepointStack, true)) {
            throw ORMException::invalidSavepointName($name);
        }

        $this->driver->createSavepoint($name);
        $this->savepointStack[] = $name;
    }

    public function releaseSavepoint(string $name): void
    {
        if (!$this->inTransaction()) {
            throw ORMException::transactionNotActive();
        }

        if (!$this->driver->supportsSavepoints()) {
            throw ORMException::savepointNotSupported();
        }

        $index = array_search($name, $this->savepointStack, true);
        if ($index === false) {
            throw ORMException::invalidSavepointName($name);
        }

        $this->driver->releaseSavepoint($name);

        // Remove this savepoint and all nested ones
        $this->savepointStack = array_slice($this->savepointStack, 0, $index);
    }

    public function rollbackToSavepoint(string $name): void
    {
        if (!$this->inTransaction()) {
            throw ORMException::transactionNotActive();
        }

        if (!$this->driver->supportsSavepoints()) {
            throw ORMException::savepointNotSupported();
        }

        $index = array_search($name, $this->savepointStack, true);
        if ($index === false) {
            throw ORMException::invalidSavepointName($name);
        }

        $this->driver->rollbackToSavepoint($name);

        // Remove all savepoints after this one
        $this->savepointStack = array_slice($this->savepointStack, 0, $index + 1);
    }

    public function lastInsertId(): string
    {
        return $this->driver->lastInsertId();
    }

    public function getPlatform(): string
    {
        return $this->driver->getPlatform();
    }

    public function quote(mixed $value): string
    {
        return $this->driver->quote($value);
    }

    public function quoteIdentifier(string $identifier): string
    {
        return $this->driver->quoteIdentifier($identifier);
    }

    public function generateSavepointName(): string
    {
        return $this->driver->generateSavepointName();
    }

    /**
     * Get the underlying database driver
     */
    public function getDriver(): DatabaseDriverInterface
    {
        return $this->driver;
    }
}
