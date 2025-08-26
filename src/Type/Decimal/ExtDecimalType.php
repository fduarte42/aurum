<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Type\Decimal;

use Fduarte42\Aurum\Type\AbstractType;
use Decimal\Decimal;
use Brick\Math\BigDecimal;

/**
 * Decimal type implementation using ext-decimal extension
 */
class ExtDecimalType extends AbstractType
{
    public function getName(): string
    {
        return 'decimal_ext';
    }

    public function convertToPHPValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Decimal) {
            return $value;
        }

        if ($value instanceof BigDecimal) {
            return new Decimal($value->toString());
        }

        if (is_string($value) || is_numeric($value)) {
            return new Decimal((string) $value);
        }

        return $value;
    }

    public function convertToDatabaseValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Decimal) {
            return $value->toString();
        }

        if ($value instanceof BigDecimal) {
            return $value->toString();
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
            'Decimal\\Decimal',
            'Decimal',
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
