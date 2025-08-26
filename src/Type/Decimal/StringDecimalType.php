<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Type\Decimal;

use Fduarte42\Aurum\Type\AbstractType;
use Brick\Math\BigDecimal;
use Decimal\Decimal;

/**
 * String-based decimal type implementation for high precision without external dependencies
 */
class StringDecimalType extends AbstractType
{
    public function getName(): string
    {
        return 'decimal_string';
    }

    public function convertToPHPValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof BigDecimal) {
            return $value->toString();
        }

        if ($value instanceof Decimal) {
            return $value->toString();
        }

        if (is_string($value) || is_numeric($value)) {
            return $this->normalizeDecimalString((string) $value);
        }

        return (string) $value;
    }

    public function convertToDatabaseValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof BigDecimal) {
            return $value->toString();
        }

        if ($value instanceof Decimal) {
            return $value->toString();
        }

        if (is_string($value) || is_numeric($value)) {
            return $this->normalizeDecimalString((string) $value);
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
        return $phpType === 'string';
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

    /**
     * Normalize decimal string representation
     */
    private function normalizeDecimalString(string $value): string
    {
        // Remove any whitespace
        $value = trim($value);
        
        // Validate that it's a valid decimal number
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException("Invalid decimal value: $value");
        }

        // Convert to string with proper formatting
        $float = (float) $value;
        return number_format($float, $this->getDefaultScale(), '.', '');
    }
}
