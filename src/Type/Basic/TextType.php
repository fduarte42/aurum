<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Type\Basic;

use Fduarte42\Aurum\Type\AbstractType;

/**
 * Text type implementation for large text content
 */
class TextType extends AbstractType
{
    public function getName(): string
    {
        return 'text';
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
        return 'TEXT';
    }

    public function isCompatibleWithPHPType(string $phpType): bool
    {
        return $phpType === 'string';
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
        return 'TEXT';
    }
}
