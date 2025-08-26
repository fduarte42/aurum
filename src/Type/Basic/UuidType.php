<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Type\Basic;

use Fduarte42\Aurum\Type\AbstractType;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * UUID type implementation
 */
class UuidType extends AbstractType
{
    public function getName(): string
    {
        return 'uuid';
    }

    public function convertToPHPValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof UuidInterface) {
            return $value;
        }

        if (is_string($value)) {
            return Uuid::fromString($value);
        }

        return $value;
    }

    public function convertToDatabaseValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof UuidInterface) {
            return $value->toString();
        }

        return (string) $value;
    }

    public function getSQLDeclaration(array $options = []): string
    {
        return 'CHAR(36)';
    }

    public function isCompatibleWithPHPType(string $phpType): bool
    {
        return in_array($phpType, [
            'Ramsey\\Uuid\\UuidInterface',
            'UuidInterface',
            'Ramsey\\Uuid\\Uuid',
            'Uuid'
        ], true);
    }

    protected function getGenericSQLType(array $options = []): string
    {
        return 'CHAR(36)';
    }

    protected function getSQLiteType(array $options = []): string
    {
        return 'TEXT';
    }

    protected function getMySQLType(array $options = []): string
    {
        return 'CHAR(36)';
    }
}
