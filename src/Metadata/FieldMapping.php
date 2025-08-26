<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Metadata;

use Fduarte42\Aurum\Type\TypeInterface;
use Fduarte42\Aurum\Type\TypeRegistry;

/**
 * Field mapping implementation
 */
class FieldMapping implements FieldMappingInterface
{
    private readonly TypeInterface $typeInstance;

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
        private readonly ?string $generationStrategy = null,
        private readonly ?TypeRegistry $typeRegistry = null
    ) {
        $this->typeInstance = $this->typeRegistry?->getType($this->type) ?? $this->createFallbackType();
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
        return $this->typeInstance->convertToPHPValue($value);
    }

    public function convertToDatabaseValue(mixed $value): mixed
    {
        return $this->typeInstance->convertToDatabaseValue($value);
    }

    /**
     * Get the type instance for this field
     */
    public function getTypeInstance(): TypeInterface
    {
        return $this->typeInstance;
    }

    /**
     * Get SQL declaration for this field
     */
    public function getSQLDeclaration(): string
    {
        $options = [];

        if ($this->length !== null) {
            $options['length'] = $this->length;
        }

        if ($this->precision !== null) {
            $options['precision'] = $this->precision;
        }

        if ($this->scale !== null) {
            $options['scale'] = $this->scale;
        }

        return $this->typeInstance->getSQLDeclaration($options);
    }

    /**
     * Create a fallback type instance when TypeRegistry is not available
     */
    private function createFallbackType(): TypeInterface
    {
        // Create a simple fallback type that just passes values through
        return new class($this->type) implements TypeInterface {
            public function __construct(private readonly string $typeName) {}

            public function getName(): string
            {
                return $this->typeName;
            }

            public function convertToPHPValue(mixed $value): mixed
            {
                return $value;
            }

            public function convertToDatabaseValue(mixed $value): mixed
            {
                return $value;
            }

            public function getSQLDeclaration(array $options = []): string
            {
                return 'TEXT';
            }

            public function requiresLength(): bool
            {
                return false;
            }

            public function supportsPrecisionScale(): bool
            {
                return false;
            }

            public function getDefaultLength(): ?int
            {
                return null;
            }

            public function getDefaultPrecision(): ?int
            {
                return null;
            }

            public function getDefaultScale(): ?int
            {
                return null;
            }

            public function isCompatibleWithPHPType(string $phpType): bool
            {
                return false;
            }
        };
    }
}
