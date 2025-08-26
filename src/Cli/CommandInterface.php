<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Cli;

/**
 * Interface for CLI commands
 */
interface CommandInterface
{
    /**
     * Execute the command
     */
    public function execute(array $options): int;

    /**
     * Get command name
     */
    public function getName(): string;

    /**
     * Get command description
     */
    public function getDescription(): string;

    /**
     * Get command help text
     */
    public function getHelp(): string;

    /**
     * Validate command options
     */
    public function validateOptions(array $options): array;
}
