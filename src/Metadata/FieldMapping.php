<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Metadata;

use Brick\Math\BigDecimal;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Field mapping implementation
 */
class FieldMapping implements FieldMappingInterface
{
    public function __construct(
        private readonly string $fieldName,
        private readonly string $columnName,
        private readonly string $type,
        private readonly bool $nullable = false,
        private readonly bool $unique = false,
        private readonly ?int $length = null,
        private readonly ?int $precision = null,
        private readonly ?int $scale = null,
        private readonly mixed $default = null,
        private readonly bool $isIdentifier = false,
        private readonly bool $isGenerated = false,
        private readonly ?string $generationStrategy = null
    ) {
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    public function getColumnName(): string
    {
        return $this->columnName;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function isUnique(): bool
    {
        return $this->unique;
    }

    public function getLength(): ?int
    {
        return $this->length;
    }

    public function getPrecision(): ?int
    {
        return $this->precision;
    }

    public function getScale(): ?int
    {
        return $this->scale;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function isIdentifier(): bool
    {
        return $this->isIdentifier;
    }

    public function isGenerated(): bool
    {
        return $this->isGenerated;
    }

    public function getGenerationStrategy(): ?string
    {
        return $this->generationStrategy;
    }

    public function convertToPHPValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this->type) {
            'uuid' => is_string($value) ? Uuid::fromString($value) : $value,
            'decimal' => $this->convertToDecimal($value),
            'integer' => (int) $value,
            'float' => (float) $value,
            'boolean' => (bool) $value,
            'datetime' => is_string($value) ? new \DateTimeImmutable($value) : $value,
            'date' => is_string($value) ? new \DateTimeImmutable($value) : $value,
            'time' => is_string($value) ? new \DateTimeImmutable($value) : $value,
            'json' => is_string($value) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : $value,
            default => $value,
        };
    }

    public function convertToDatabaseValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this->type) {
            'uuid' => $value instanceof UuidInterface ? $value->toString() : (string) $value,
            'decimal' => $this->convertDecimalToDatabase($value),
            'boolean' => $value ? 1 : 0,
            'datetime', 'date', 'time' => $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value,
            'json' => is_array($value) || is_object($value) ? json_encode($value, JSON_THROW_ON_ERROR) : $value,
            default => $value,
        };
    }

    /**
     * Convert value to decimal type (supports both ext-decimal and brick/math)
     */
    private function convertToDecimal(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) || is_numeric($value)) {
            // Always use brick/math for consistency
            return BigDecimal::of((string) $value);
        }

        // If it's already a BigDecimal, return as-is
        if ($value instanceof BigDecimal) {
            return $value;
        }

        // If it's a Decimal object (from ext-decimal), convert to BigDecimal
        if ($value instanceof \Decimal\Decimal) {
            return BigDecimal::of($value->toString());
        }

        return $value;
    }

    /**
     * Convert decimal value to database format
     */
    private function convertDecimalToDatabase(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        // Handle ext-decimal
        if ($value instanceof \Decimal\Decimal) {
            return $value->toString();
        }

        // Handle brick/math
        if ($value instanceof BigDecimal) {
            return (string) $value->toScale($this->scale ?? 2, \Brick\Math\RoundingMode::HALF_UP);
        }

        return (string) $value;
    }
}
