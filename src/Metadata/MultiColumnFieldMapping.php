<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Metadata;

use Fduarte42\Aurum\Type\TypeInterface;
use Fduarte42\Aurum\Type\TypeRegistry;

/**
 * Multi-column field mapping implementation
 */
class MultiColumnFieldMapping implements MultiColumnFieldMappingInterface
{
    private readonly TypeInterface $typeInstance;
    private array $columnNamesWithPostfixes;

    public function __construct(
        private readonly string $fieldName,
        private readonly string $baseColumnName,
        private readonly array $columnPostfixes,
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
        
        // Build column names with postfixes
        $this->columnNamesWithPostfixes = [];
        foreach ($this->columnPostfixes as $postfix) {
            $this->columnNamesWithPostfixes[$postfix] = $this->baseColumnName . $postfix;
        }
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    public function getColumnName(): string
    {
        // Return the first column name for backward compatibility
        return reset($this->columnNamesWithPostfixes) ?: $this->baseColumnName;
    }

    public function getColumnNames(): array
    {
        return array_values($this->columnNamesWithPostfixes);
    }

    public function isMultiColumn(): bool
    {
        return true;
    }

    public function getColumnNamesWithPostfixes(): array
    {
        return $this->columnNamesWithPostfixes;
    }

    public function getBaseColumnName(): string
    {
        return $this->baseColumnName;
    }

    public function getColumnPostfixes(): array
    {
        return $this->columnPostfixes;
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

    public function isPrimaryKey(): bool
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

    public function convertToMultipleDatabaseValues(mixed $value): array
    {
        // Delegate to the type instance if it supports multi-column conversion
        if (method_exists($this->typeInstance, 'convertToMultipleDatabaseValues')) {
            return $this->typeInstance->convertToMultipleDatabaseValues($value);
        }

        // Fallback: convert to single value and replicate across all columns
        $dbValue = $this->convertToDatabaseValue($value);
        $result = [];
        foreach ($this->columnPostfixes as $postfix) {
            $result[$postfix] = $dbValue;
        }
        return $result;
    }

    public function convertFromMultipleDatabaseValues(array $values): mixed
    {
        // Delegate to the type instance if it supports multi-column conversion
        if (method_exists($this->typeInstance, 'convertFromMultipleDatabaseValues')) {
            return $this->typeInstance->convertFromMultipleDatabaseValues($values);
        }

        // Fallback: use the first non-null value
        foreach ($values as $value) {
            if ($value !== null) {
                return $this->convertToPHPValue($value);
            }
        }
        return null;
    }

    public function getMultiColumnSQLDeclarations(): array
    {
        $declarations = [];
        
        // If the type supports multi-column SQL declarations, use that
        if (method_exists($this->typeInstance, 'getMultiColumnSQLDeclarations')) {
            return $this->typeInstance->getMultiColumnSQLDeclarations($this->columnPostfixes);
        }

        // Fallback: use the same SQL declaration for all columns
        $sqlDeclaration = $this->getSQLDeclaration();
        foreach ($this->columnPostfixes as $postfix) {
            $declarations[$postfix] = $sqlDeclaration;
        }
        
        return $declarations;
    }

    /**
     * Get the type instance for this field
     */
    public function getTypeInstance(): TypeInterface
    {
        return $this->typeInstance;
    }

    /**
     * Get SQL declaration for this field (single column fallback)
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
