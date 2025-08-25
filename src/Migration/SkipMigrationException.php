<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Migration;

/**
 * Exception thrown when a migration should be skipped
 * This is not an error condition, but a way to skip migrations conditionally
 */
class SkipMigrationException extends \Exception
{
    public function __construct(string $message = "Migration skipped")
    {
        parent::__construct($message);
    }
}
