<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Type\DateTime;

use Fduarte42\Aurum\Type\AbstractType;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Date type implementation (date only, no time)
 */
class DateType extends AbstractType
{
    public function getName(): string
    {
        return 'date';
    }

    public function convertToPHPValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            // Convert to date-only by setting time to 00:00:00
            return DateTimeImmutable::createFromFormat('Y-m-d', $value->format('Y-m-d'));
        }

        if (is_string($value)) {
            // Try to parse as date
            $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
            if ($date === false) {
                // Fallback to full datetime parsing and extract date
                $date = new DateTimeImmutable($value);
                return DateTimeImmutable::createFromFormat('Y-m-d', $date->format('Y-m-d'));
            }
            return $date;
        }

        return $value;
    }

    public function convertToDatabaseValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value)) {
            // Validate and normalize the date string
            $date = new DateTimeImmutable($value);
            return $date->format('Y-m-d');
        }

        return $value;
    }

    public function getSQLDeclaration(array $options = []): string
    {
        return 'DATE';
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
        return 'DATE';
    }

    protected function getSQLiteType(array $options = []): string
    {
        return 'TEXT';
    }

    protected function getMySQLType(array $options = []): string
    {
        return 'DATE';
    }
}
