<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Type\DateTime;

use Fduarte42\Aurum\Type\AbstractType;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * DateTime with timezone type implementation
 * Stores datetime and timezone as JSON in database
 */
class DateTimeWithTimezoneType extends AbstractType
{
    public function getName(): string
    {
        return 'datetime_tz';
    }

    public function convertToPHPValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value instanceof DateTimeImmutable ? $value : DateTimeImmutable::createFromMutable($value);
        }

        if (is_string($value)) {
            // Try to parse as JSON first (our storage format)
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['datetime'], $decoded['timezone'])) {
                $timezone = new DateTimeZone($decoded['timezone']);
                return new DateTimeImmutable($decoded['datetime'], $timezone);
            }

            // Fallback to direct datetime parsing
            return new DateTimeImmutable($value);
        }

        if (is_array($value) && isset($value['datetime'], $value['timezone'])) {
            $timezone = new DateTimeZone($value['timezone']);
            return new DateTimeImmutable($value['datetime'], $timezone);
        }

        return $value;
    }

    public function convertToDatabaseValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            $data = [
                'datetime' => $value->format('Y-m-d H:i:s'),
                'timezone' => $value->getTimezone()->getName(),
            ];
            return json_encode($data, JSON_THROW_ON_ERROR);
        }

        if (is_string($value)) {
            // Parse the datetime and extract timezone info
            $datetime = new DateTimeImmutable($value);
            $data = [
                'datetime' => $datetime->format('Y-m-d H:i:s'),
                'timezone' => $datetime->getTimezone()->getName(),
            ];
            return json_encode($data, JSON_THROW_ON_ERROR);
        }

        if (is_array($value) && isset($value['datetime'], $value['timezone'])) {
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
        return in_array($phpType, [
            'DateTimeImmutable',
            'DateTime',
            'DateTimeInterface',
        ], true);
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
