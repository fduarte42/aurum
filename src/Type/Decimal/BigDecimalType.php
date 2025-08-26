<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Type\Decimal;

use Fduarte42\Aurum\Type\AbstractType;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * BigDecimal type implementation using brick/math library
 */
class BigDecimalType extends AbstractType
{
    public function getName(): string
    {
        return 'decimal';
    }

    public function convertToPHPValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof BigDecimal) {
            return $value;
        }

        if ($value instanceof \Decimal\Decimal) {
            return BigDecimal::of((string) $value);
        }

        if (is_string($value) || is_numeric($value)) {
            return BigDecimal::of((string) $value);
        }

        return $value;
    }

    public function convertToDatabaseValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof BigDecimal) {
            return (string) $value;
        }

        if ($value instanceof \Decimal\Decimal) {
            return (string) $value;
        }

        return (string) $value;
    }

    public function getSQLDeclaration(array $options = []): string
    {
        $precision = $options['precision'] ?? $this->getDefaultPrecision();
        $scale = $options['scale'] ?? $this->getDefaultScale();

        return "DECIMAL($precision, $scale)";
    }

    public function supportsPrecisionScale(): bool
    {
        return true;
    }

    public function getDefaultPrecision(): ?int
    {
        return 10;
    }

    public function getDefaultScale(): ?int
    {
        return 2;
    }

    public function isCompatibleWithPHPType(string $phpType): bool
    {
        return in_array($phpType, [
            'Brick\\Math\\BigDecimal',
            'BigDecimal',
        ], true);
    }

    protected function getGenericSQLType(array $options = []): string
    {
        return $this->getSQLDeclaration($options);
    }

    protected function getSQLiteType(array $options = []): string
    {
        // SQLite doesn't have native DECIMAL, use TEXT for precision
        return 'TEXT';
    }

    protected function getMySQLType(array $options = []): string
    {
        $precision = $options['precision'] ?? $this->getDefaultPrecision();
        $scale = $options['scale'] ?? $this->getDefaultScale();

        return "DECIMAL($precision, $scale)";
    }
}
