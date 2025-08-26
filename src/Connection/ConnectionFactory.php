<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Connection;

use Fduarte42\Aurum\Driver\DatabaseDriverFactory;
use Fduarte42\Aurum\Exception\ORMException;

/**
 * Factory for creating database connections using the driver pattern
 *
 * This factory provides backward compatibility while using the new driver system.
 */
class ConnectionFactory
{
    /**
     * Create a SQLite connection
     */
    public static function createSqliteConnection(string $path = ':memory:'): ConnectionInterface
    {
        $driver = DatabaseDriverFactory::createSqlite($path);
        return new Connection($driver);
    }

    /**
     * Create a MariaDB/MySQL connection
     */
    public static function createMariaDbConnection(
        string $host,
        string $database,
        string $username,
        string $password,
        int $port = 3306,
        array $options = []
    ): ConnectionInterface {
        $driver = DatabaseDriverFactory::createMariaDb(
            $host,
            $database,
            $username,
            $password,
            $port,
            $options
        );
        return new Connection($driver);
    }

    /**
     * Create a connection from configuration array
     *
     * @param array<string, mixed> $config Database configuration
     * @return ConnectionInterface
     * @throws ORMException
     */
    public static function createFromConfig(array $config): ConnectionInterface
    {
        $driver = DatabaseDriverFactory::create($config);
        return new Connection($driver);
    }
}
