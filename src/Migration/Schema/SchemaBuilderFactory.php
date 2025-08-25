<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Migration\Schema;

use Fduarte42\Aurum\Connection\ConnectionInterface;
use Fduarte42\Aurum\Migration\MigrationException;

/**
 * Factory for creating schema builders based on database platform
 */
class SchemaBuilderFactory
{
    /**
     * Create a schema builder for the given connection
     */
    public static function create(ConnectionInterface $connection): SchemaBuilderInterface
    {
        $platform = $connection->getPlatform();

        return match ($platform) {
            'sqlite' => new SqliteSchemaBuilder($connection),
            'mysql', 'mariadb' => new MariaDbSchemaBuilder($connection),
            default => throw new MigrationException("Unsupported database platform: {$platform}")
        };
    }
}
