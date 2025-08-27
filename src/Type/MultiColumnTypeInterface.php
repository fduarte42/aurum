<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Type;

/**
 * Interface for types that support multi-column storage
 */
interface MultiColumnTypeInterface extends TypeInterface
{
    /**
     * Get the column postfixes required for this type
     * 
     * @return array<string>
     */
    public function getRequiredColumnPostfixes(): array;

    /**
     * Convert a PHP value to multiple database values
     * 
     * @return array<string, mixed> Array where key is postfix and value is database value
     */
    public function convertToMultipleDatabaseValues(mixed $value): array;

    /**
     * Convert multiple database values to a single PHP value
     * 
     * @param array<string, mixed> $values Array where key is postfix and value is database value
     */
    public function convertFromMultipleDatabaseValues(array $values): mixed;

    /**
     * Get SQL declarations for all columns
     * 
     * @param array<string> $postfixes The column postfixes to generate declarations for
     * @return array<string, string> Array where key is postfix and value is SQL declaration
     */
    public function getMultiColumnSQLDeclarations(array $postfixes): array;

    /**
     * Check if this type requires multi-column storage
     */
    public function requiresMultiColumnStorage(): bool;
}
