<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Type\Basic;

use Fduarte42\Aurum\Type\AbstractType;

/**
 * Boolean type implementation
 */
class BooleanType extends AbstractType
{
    public function getName(): string
    {
        return 'boolean';
    }

    public function convertToPHPValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return (bool) $value;
    }

    public function convertToDatabaseValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return $value ? 1 : 0;
    }

    public function getSQLDeclaration(array $options = []): string
    {
        return 'BOOLEAN';
    }

    public function isCompatibleWithPHPType(string $phpType): bool
    {
        return in_array($phpType, ['bool', 'boolean'], true);
    }

    protected function getGenericSQLType(array $options = []): string
    {
        return 'BOOLEAN';
    }

    protected function getSQLiteType(array $options = []): string
    {
        return 'INTEGER';
    }

    protected function getMySQLType(array $options = []): string
    {
        return 'TINYINT(1)';
    }
}
