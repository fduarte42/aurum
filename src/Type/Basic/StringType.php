<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Type\Basic;

use Fduarte42\Aurum\Type\AbstractType;

/**
 * String type implementation
 */
class StringType extends AbstractType
{
    public function getName(): string
    {
        return 'string';
    }

    public function convertToPHPValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    public function convertToDatabaseValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    public function getSQLDeclaration(array $options = []): string
    {
        $length = $options['length'] ?? $this->getDefaultLength();
        
        if ($length !== null) {
            return "VARCHAR($length)";
        }

        return 'VARCHAR(255)';
    }

    public function requiresLength(): bool
    {
        return true;
    }

    public function getDefaultLength(): ?int
    {
        return 255;
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
        return 'TEXT';
    }

    protected function getMySQLType(array $options = []): string
    {
        $length = $options['length'] ?? $this->getDefaultLength();
        return "VARCHAR($length)";
    }
}
