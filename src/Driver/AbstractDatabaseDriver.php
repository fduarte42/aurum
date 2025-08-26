<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Driver;

use Fduarte42\Aurum\Exception\ORMException;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Abstract base class for database drivers providing common functionality
 */
abstract class AbstractDatabaseDriver implements DatabaseDriverInterface
{
    protected PDO $pdo;
    protected int $savepointCounter = 0;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->initializeConnection();
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
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    public function fetchAll(string $sql, array $parameters = []): array
    {
        $stmt = $this->execute($sql, $parameters);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function lastInsertId(?string $name = null): string
    {
        return $this->pdo->lastInsertId($name);
    }

    public function quote(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        
        return $this->pdo->quote((string) $value);
    }

    public function beginTransaction(): void
    {
        try {
            $this->pdo->beginTransaction();
        } catch (PDOException $e) {
            throw ORMException::transactionFailed('begin', $e->getMessage());
        }
    }

    public function commit(): void
    {
        try {
            $this->pdo->commit();
        } catch (PDOException $e) {
            throw ORMException::transactionFailed('commit', $e->getMessage());
        }
    }

    public function rollback(): void
    {
        try {
            $this->pdo->rollBack();
        } catch (PDOException $e) {
            throw ORMException::transactionFailed('rollback', $e->getMessage());
        }
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    public function createSavepoint(string $name): void
    {
        if (!$this->supportsSavepoints()) {
            throw new \RuntimeException('Database does not support savepoints');
        }

        $sql = $this->getSavepointSQL('CREATE', $name);
        $this->execute($sql);
    }

    public function releaseSavepoint(string $name): void
    {
        if (!$this->supportsSavepoints()) {
            throw new \RuntimeException('Database does not support savepoints');
        }

        $sql = $this->getSavepointSQL('RELEASE', $name);
        $this->execute($sql);
    }

    public function rollbackToSavepoint(string $name): void
    {
        if (!$this->supportsSavepoints()) {
            throw new \RuntimeException('Database does not support savepoints');
        }

        $sql = $this->getSavepointSQL('ROLLBACK', $name);
        $this->execute($sql);
    }

    public function generateSavepointName(): string
    {
        return 'sp_' . (++$this->savepointCounter);
    }

    public function getLimitOffsetSQL(?int $limit, ?int $offset = null): string
    {
        $sql = '';
        
        if ($limit !== null) {
            $sql .= ' LIMIT ' . $limit;
            
            if ($offset !== null) {
                $sql .= ' OFFSET ' . $offset;
            }
        }
        
        return $sql;
    }

    public function supportsForeignKeys(): bool
    {
        return true; // Most databases support foreign keys
    }

    public function supportsAddingForeignKeys(): bool
    {
        return true; // Most databases support adding foreign keys
    }

    public function supportsDroppingForeignKeys(): bool
    {
        return true; // Most databases support dropping foreign keys
    }

    public function getDefaultPDOOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
    }

    /**
     * Initialize the database connection with platform-specific settings
     */
    protected function initializeConnection(): void
    {
        // Set default PDO attributes
        foreach ($this->getDefaultPDOOptions() as $attribute => $value) {
            try {
                $this->pdo->setAttribute($attribute, $value);
            } catch (\PDOException | \ValueError | \TypeError $e) {
                // Ignore attribute errors for cross-platform compatibility
                // (e.g., MySQL-specific attributes on SQLite connections)
            }
        }

        // Execute platform-specific initialization SQL
        foreach ($this->getConnectionInitializationSQL() as $sql) {
            try {
                $this->pdo->exec($sql);
            } catch (\PDOException | \ValueError | \TypeError $e) {
                // Ignore SQL errors for cross-platform compatibility
                // (e.g., MySQL-specific SQL on SQLite connections)
            }
        }
    }

    /**
     * Get platform-specific savepoint SQL
     */
    protected function getSavepointSQL(string $operation, string $name): string
    {
        $quotedName = $this->quoteIdentifier($name);
        
        return match ($operation) {
            'CREATE' => "SAVEPOINT {$quotedName}",
            'RELEASE' => "RELEASE SAVEPOINT {$quotedName}",
            'ROLLBACK' => "ROLLBACK TO SAVEPOINT {$quotedName}",
            default => throw new \InvalidArgumentException("Unknown savepoint operation: {$operation}"),
        };
    }

    /**
     * Get platform-specific connection initialization SQL
     * Override in concrete drivers for platform-specific initialization
     */
    public function getConnectionInitializationSQL(): array
    {
        return [];
    }
}
