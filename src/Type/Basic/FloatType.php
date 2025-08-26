<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Type\Basic;

use Fduarte42\Aurum\Type\AbstractType;

/**
 * Float type implementation
 */
class FloatType extends AbstractType
{
    public function getName(): string
    {
        return 'float';
    }

    public function convertToPHPValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return (float) $value;
    }

    public function convertToDatabaseValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return (float) $value;
    }

    public function getSQLDeclaration(array $options = []): string
    {
        return 'REAL';
    }

    public function isCompatibleWithPHPType(string $phpType): bool
    {
        return in_array($phpType, ['float', 'double'], true);
    }

    protected function getGenericSQLType(array $options = []): string
    {
        return 'REAL';
    }

    protected function getSQLiteType(array $options = []): string
    {
        return 'REAL';
    }

    protected function getMySQLType(array $options = []): string
    {
        return 'DOUBLE';
    }
}
