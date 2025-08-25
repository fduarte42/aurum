<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Migration;

use Fduarte42\Aurum\Connection\ConnectionInterface;

/**
 * Interface for database migrations
 */
interface MigrationInterface
{
    /**
     * Get the migration version/identifier
     */
    public function getVersion(): string;

    /**
     * Get a description of what this migration does
     */
    public function getDescription(): string;

    /**
     * Execute the migration (upgrade)
     */
    public function up(ConnectionInterface $connection): void;

    /**
     * Reverse the migration (downgrade)
     */
    public function down(ConnectionInterface $connection): void;

    /**
     * Check if this migration is transactional
     * Non-transactional migrations will not be wrapped in a transaction
     */
    public function isTransactional(): bool;

    /**
     * Get the migration dependencies (other migrations that must be executed first)
     * 
     * @return array<string> Array of migration versions this migration depends on
     */
    public function getDependencies(): array;
}
