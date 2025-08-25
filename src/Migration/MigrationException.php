<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Migration;

use Fduarte42\Aurum\Exception\ORMException;

/**
 * Exception thrown when a migration fails
 */
class MigrationException extends ORMException
{
    public static function migrationFailed(string $version, string $message): self
    {
        return new self("Migration {$version} failed: {$message}");
    }

    public static function migrationNotFound(string $version): self
    {
        return new self("Migration {$version} not found");
    }

    public static function migrationAlreadyExecuted(string $version): self
    {
        return new self("Migration {$version} has already been executed");
    }

    public static function migrationNotExecuted(string $version): self
    {
        return new self("Migration {$version} has not been executed");
    }

    public static function invalidMigrationVersion(string $version): self
    {
        return new self("Invalid migration version: {$version}");
    }

    public static function dependencyNotMet(string $version, string $dependency): self
    {
        return new self("Migration {$version} depends on {$dependency} which has not been executed");
    }

    public static function circularDependency(string $version): self
    {
        return new self("Circular dependency detected for migration {$version}");
    }

    public static function migrationDirectoryNotFound(string $directory): self
    {
        return new self("Migration directory not found: {$directory}");
    }

    public static function migrationDirectoryNotWritable(string $directory): self
    {
        return new self("Migration directory is not writable: {$directory}");
    }

    public static function invalidMigrationClass(string $class): self
    {
        return new self("Invalid migration class: {$class}");
    }

    public static function migrationClassNotFound(string $class): self
    {
        return new self("Migration class not found: {$class}");
    }
}
