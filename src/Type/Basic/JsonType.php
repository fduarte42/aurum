<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Type\Basic;

use Fduarte42\Aurum\Type\AbstractType;

/**
 * JSON type implementation
 */
class JsonType extends AbstractType
{
    public function getName(): string
    {
        return 'json';
    }

    public function convertToPHPValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        }

        return $value;
    }

    public function convertToDatabaseValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        return $value;
    }

    public function getSQLDeclaration(array $options = []): string
    {
        return 'JSON';
    }

    public function isCompatibleWithPHPType(string $phpType): bool
    {
        return $phpType === 'array';
    }

    protected function getGenericSQLType(array $options = []): string
    {
        return 'TEXT';
    }

    protected function getSQLiteType(array $options = []): string
    {
        return 'TEXT';
    }

    protected function getMySQLType(array $options = []): string
    {
        return 'JSON';
    }
}
