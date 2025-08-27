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
            return new DateTimeImmutable($value);
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
            $datetime = new DateTimeImmutable($value);
            return $this->convertToMultipleDatabaseValues($datetime);
        }

        throw new \InvalidArgumentException('Cannot convert value to multiple database values: unsupported type');
    }

    public function convertFromMultipleDatabaseValues(array $values): mixed
    {
        if (!isset($values['_local']) || !isset($values['_timezone'])) {
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
            if (isset($values['_utc'])) {
                return new DateTimeImmutable($values['_utc'], new DateTimeZone('UTC'));
            }
            return null;
        }
    }

    public function convertToDatabaseValue(mixed $value): mixed
    {
        throw new \BadMethodCallException(
            'convertToDatabaseValue() is not supported for multi-column types. Use convertToMultipleDatabaseValues() instead.'
        );
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
        throw new \BadMethodCallException(
            'getSQLDeclaration() is not supported for multi-column types. Use getMultiColumnSQLDeclarations() instead.'
        );
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
        throw new \BadMethodCallException(
            'getGenericSQLType() is not supported for multi-column types. Use getMultiColumnSQLDeclarations() instead.'
        );
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
