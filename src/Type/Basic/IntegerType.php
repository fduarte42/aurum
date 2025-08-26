<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Type\Basic;

use Fduarte42\Aurum\Type\AbstractType;

/**
 * Integer type implementation
 */
class IntegerType extends AbstractType
{
    public function getName(): string
    {
        return 'integer';
    }

    public function convertToPHPValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return (int) $value;
    }

    public function convertToDatabaseValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return (int) $value;
    }

    public function getSQLDeclaration(array $options = []): string
    {
        return 'INTEGER';
    }

    public function isCompatibleWithPHPType(string $phpType): bool
    {
        return in_array($phpType, ['int', 'integer'], true);
    }

    protected function getGenericSQLType(array $options = []): string
    {
        return 'INTEGER';
    }

    protected function getSQLiteType(array $options = []): string
    {
        return 'INTEGER';
    }

    protected function getMySQLType(array $options = []): string
    {
        return 'INT';
    }
}
