<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Type;

/**
 * Abstract base class for type implementations
 */
abstract class AbstractType implements TypeInterface
{
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

    /**
     * Helper method to handle null values
     */
    protected function handleNullValue(mixed $value): mixed
    {
        return $value === null ? null : $value;
    }

    /**
     * Get platform-specific SQL type
     */
    protected function getPlatformSQLType(string $platform, array $options = []): string
    {
        return match ($platform) {
            'sqlite' => $this->getSQLiteType($options),
            'mysql', 'mariadb' => $this->getMySQLType($options),
            default => $this->getGenericSQLType($options),
        };
    }

    /**
     * Get SQLite-specific SQL type
     */
    protected function getSQLiteType(array $options = []): string
    {
        return $this->getGenericSQLType($options);
    }

    /**
     * Get MySQL/MariaDB-specific SQL type
     */
    protected function getMySQLType(array $options = []): string
    {
        return $this->getGenericSQLType($options);
    }

    /**
     * Get generic SQL type (fallback)
     */
    abstract protected function getGenericSQLType(array $options = []): string;
}
