<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Metadata;

/**
 * Interface for field mappings that span multiple database columns
 */
interface MultiColumnFieldMappingInterface extends FieldMappingInterface
{
    /**
     * Get column names with their postfixes
     * 
     * @return array<string, string> Array where key is postfix and value is full column name
     */
    public function getColumnNamesWithPostfixes(): array;

    /**
     * Get the base column name (without postfix)
     */
    public function getBaseColumnName(): string;

    /**
     * Get column postfixes used by this field mapping
     * 
     * @return array<string>
     */
    public function getColumnPostfixes(): array;

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
     * @return array<string, string> Array where key is postfix and value is SQL declaration
     */
    public function getMultiColumnSQLDeclarations(): array;
}
