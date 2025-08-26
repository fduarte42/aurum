<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Type;

/**
 * Interface for all type implementations in the ORM
 */
interface TypeInterface
{
    /**
     * Get the type name
     */
    public function getName(): string;

    /**
     * Convert a database value to PHP value
     */
    public function convertToPHPValue(mixed $value): mixed;

    /**
     * Convert a PHP value to database value
     */
    public function convertToDatabaseValue(mixed $value): mixed;

    /**
     * Get the SQL declaration for this type
     */
    public function getSQLDeclaration(array $options = []): string;

    /**
     * Check if this type requires a length parameter
     */
    public function requiresLength(): bool;

    /**
     * Check if this type supports precision/scale parameters
     */
    public function supportsPrecisionScale(): bool;

    /**
     * Get the default length for this type (if applicable)
     */
    public function getDefaultLength(): ?int;

    /**
     * Get the default precision for this type (if applicable)
     */
    public function getDefaultPrecision(): ?int;

    /**
     * Get the default scale for this type (if applicable)
     */
    public function getDefaultScale(): ?int;

    /**
     * Check if the given PHP type is compatible with this type
     */
    public function isCompatibleWithPHPType(string $phpType): bool;
}
