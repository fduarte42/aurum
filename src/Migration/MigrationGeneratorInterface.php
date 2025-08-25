<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Migration;

/**
 * Interface for generating new migration files
 */
interface MigrationGeneratorInterface
{
    /**
     * Generate a new migration file
     * 
     * @param string $description Human-readable description of the migration
     * @param string|null $template Optional template to use for the migration
     * @return string The generated migration version
     */
    public function generate(string $description, ?string $template = null): string;

    /**
     * Generate a migration version string
     */
    public function generateVersion(): string;

    /**
     * Get the default migration template
     */
    public function getDefaultTemplate(): string;

    /**
     * Validate a migration description
     */
    public function validateDescription(string $description): void;
}
