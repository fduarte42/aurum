<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Exception;

/**
 * Base ORM exception
 */
class ORMException extends \Exception
{
    public static function entityNotFound(string $className, mixed $identifier): self
    {
        return new self(sprintf('Entity of type "%s" with identifier "%s" not found.', $className, $identifier));
    }

    public static function entityNotManaged(object $entity): self
    {
        return new self(sprintf('Entity of type "%s" is not managed by the EntityManager.', get_class($entity)));
    }

    public static function invalidEntityClass(string $className): self
    {
        return new self(sprintf('Class "%s" is not a valid entity class.', $className));
    }

    public static function metadataNotFound(string $className): self
    {
        return new self(sprintf('Metadata for class "%s" not found.', $className));
    }

    public static function transactionNotActive(): self
    {
        return new self('No active transaction found.');
    }

    public static function transactionAlreadyActive(): self
    {
        return new self('A transaction is already active.');
    }

    public static function savepointNotSupported(): self
    {
        return new self('Savepoints are not supported by the current database platform.');
    }

    public static function invalidSavepointName(string $name): self
    {
        return new self(sprintf('Invalid savepoint name: "%s".', $name));
    }

    public static function connectionFailed(string $message): self
    {
        return new self(sprintf('Database connection failed: %s', $message));
    }

    public static function queryFailed(string $sql, string $message): self
    {
        return new self(sprintf('Query failed: %s. SQL: %s', $message, $sql));
    }

    public static function unknownType(string $typeName): self
    {
        return new self(sprintf('Unknown type "%s".', $typeName));
    }

    public static function configurationError(string $message): self
    {
        return new self(sprintf('Configuration error: %s', $message));
    }

    public static function transactionFailed(string $operation, string $message): self
    {
        return new self(sprintf('Transaction %s failed: %s', $operation, $message));
    }
}
