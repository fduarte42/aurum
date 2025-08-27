<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Type\DateTime;

use Fduarte42\Aurum\Type\AbstractType;
use Fduarte42\Aurum\Type\MultiColumnTypeInterface;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * DateTime with timezone type implementation
 * Stores datetime across 3 columns: _utc, _local, _timezone
 */
class DateTimeWithTimezoneType extends AbstractType implements MultiColumnTypeInterface
{
    public function getName(): string
    {
        return 'datetime_tz';
    }

    public function getRequiredColumnPostfixes(): array
    {
        return ['_utc', '_local', '_timezone'];
    }

    public function requiresMultiColumnStorage(): bool
    {
        return true;
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
            // Try to parse as JSON first (backward compatibility with old storage format)
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

    public function convertToMultipleDatabaseValues(mixed $value): array
    {
        if ($value === null) {
            return [
                '_utc' => null,
                '_local' => null,
                '_timezone' => null,
            ];
        }

        if ($value instanceof DateTimeInterface) {
            // Convert to UTC for storage
            $utcDateTime = $value->setTimezone(new DateTimeZone('UTC'));

            return [
                '_utc' => $utcDateTime->format('Y-m-d H:i:s'),
                '_local' => $value->format('Y-m-d H:i:s'),
                '_timezone' => $value->getTimezone()->getName(),
            ];
        }

        if (is_string($value)) {
            // Parse the datetime and extract timezone info
            $datetime = new DateTimeImmutable($value);
            return $this->convertToMultipleDatabaseValues($datetime);
        }

        if (is_array($value) && isset($value['datetime'], $value['timezone'])) {
            $timezone = new DateTimeZone($value['timezone']);
            $datetime = new DateTimeImmutable($value['datetime'], $timezone);
            return $this->convertToMultipleDatabaseValues($datetime);
        }

        // Fallback: store as-is in all columns
        return [
            '_utc' => $value,
            '_local' => $value,
            '_timezone' => null,
        ];
    }

    public function convertFromMultipleDatabaseValues(array $values): mixed
    {
        // Check if we have all required values
        if (!isset($values['_local']) || !isset($values['_timezone'])) {
            // Try to use UTC if available
            if (isset($values['_utc'])) {
                return new DateTimeImmutable($values['_utc'], new DateTimeZone('UTC'));
            }
            return null;
        }

        if ($values['_local'] === null || $values['_timezone'] === null) {
            return null;
        }

        try {
            $timezone = new DateTimeZone($values['_timezone']);
            return new DateTimeImmutable($values['_local'], $timezone);
        } catch (\Exception $e) {
            // Fallback to UTC if timezone is invalid
            if (isset($values['_utc'])) {
                return new DateTimeImmutable($values['_utc'], new DateTimeZone('UTC'));
            }
            return null;
        }
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

    public function getMultiColumnSQLDeclarations(array $postfixes): array
    {
        $declarations = [];

        foreach ($postfixes as $postfix) {
            switch ($postfix) {
                case '_utc':
                case '_local':
                    $declarations[$postfix] = 'DATETIME';
                    break;
                case '_timezone':
                    $declarations[$postfix] = 'VARCHAR(50)';
                    break;
                default:
                    $declarations[$postfix] = 'TEXT';
                    break;
            }
        }

        return $declarations;
    }

    public function getSQLDeclaration(array $options = []): string
    {
        // For backward compatibility, return JSON when used as single column
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

    /**
     * Get platform-specific multi-column SQL declarations
     */
    public function getPlatformMultiColumnSQLDeclarations(string $platform, array $postfixes): array
    {
        $declarations = [];

        foreach ($postfixes as $postfix) {
            switch ($postfix) {
                case '_utc':
                case '_local':
                    $declarations[$postfix] = match ($platform) {
                        'sqlite' => 'TEXT',
                        'mysql', 'mariadb' => 'DATETIME',
                        default => 'DATETIME',
                    };
                    break;
                case '_timezone':
                    $declarations[$postfix] = match ($platform) {
                        'sqlite' => 'TEXT',
                        'mysql', 'mariadb' => 'VARCHAR(50)',
                        default => 'VARCHAR(50)',
                    };
                    break;
                default:
                    $declarations[$postfix] = 'TEXT';
                    break;
            }
        }

        return $declarations;
    }
}
