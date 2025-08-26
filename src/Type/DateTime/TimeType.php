<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Type\DateTime;

use Fduarte42\Aurum\Type\AbstractType;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Time type implementation (time only, no date)
 */
class TimeType extends AbstractType
{
    public function getName(): string
    {
        return 'time';
    }

    public function convertToPHPValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            // Create a new DateTimeImmutable with today's date and the time from the value
            return DateTimeImmutable::createFromFormat('H:i:s', $value->format('H:i:s'));
        }

        if (is_string($value)) {
            // Try to parse as time
            $time = DateTimeImmutable::createFromFormat('H:i:s', $value);
            if ($time === false) {
                // Try without seconds
                $time = DateTimeImmutable::createFromFormat('H:i', $value);
                if ($time === false) {
                    // Fallback to full datetime parsing and extract time
                    $datetime = new DateTimeImmutable($value);
                    return DateTimeImmutable::createFromFormat('H:i:s', $datetime->format('H:i:s'));
                }
            }
            return $time;
        }

        return $value;
    }

    public function convertToDatabaseValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i:s');
        }

        if (is_string($value)) {
            // Validate and normalize the time string
            $time = new DateTimeImmutable($value);
            return $time->format('H:i:s');
        }

        return $value;
    }

    public function getSQLDeclaration(array $options = []): string
    {
        return 'TIME';
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
        return 'TIME';
    }

    protected function getSQLiteType(array $options = []): string
    {
        return 'TEXT';
    }

    protected function getMySQLType(array $options = []): string
    {
        return 'TIME';
    }
}
