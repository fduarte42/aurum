<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Type\DateTime;

use Fduarte42\Aurum\Type\AbstractType;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * DateTime type implementation (date and time without timezone)
 */
class DateTimeType extends AbstractType
{
    public function getName(): string
    {
        return 'datetime';
    }

    public function convertToPHPValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            // Ensure we return DateTimeImmutable
            if ($value instanceof DateTimeImmutable) {
                return $value;
            }
            return DateTimeImmutable::createFromMutable($value);
        }

        if (is_string($value)) {
            return new DateTimeImmutable($value);
        }

        return $value;
    }

    public function convertToDatabaseValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value)) {
            // Validate and normalize the datetime string
            $datetime = new DateTimeImmutable($value);
            return $datetime->format('Y-m-d H:i:s');
        }

        return $value;
    }

    public function getSQLDeclaration(array $options = []): string
    {
        return 'DATETIME';
    }

    public function isCompatibleWithPHPType(string $phpType): bool
    {
        return in_array($phpType, [
            'DateTimeImmutable',
            'DateTime',
            'DateTimeInterface',
        ], true);
    }

    protected function getGenericSQLType(array $options = []): string
    {
        return 'DATETIME';
    }

    protected function getSQLiteType(array $options = []): string
    {
        return 'TEXT';
    }

    protected function getMySQLType(array $options = []): string
    {
        return 'DATETIME';
    }
}
