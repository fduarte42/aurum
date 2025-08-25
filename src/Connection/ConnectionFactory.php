<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Connection;

use Fduarte42\Aurum\Exception\ORMException;
use PDO;
use PDOException;

/**
 * Factory for creating database connections
 */
class ConnectionFactory
{
    /**
     * Create a SQLite connection
     */
    public static function createSqliteConnection(string $path = ':memory:'): ConnectionInterface
    {
        try {
            $dsn = "sqlite:{$path}";
            $pdo = new PDO($dsn);
            
            // Enable foreign key constraints for SQLite
            $pdo->exec('PRAGMA foreign_keys = ON');
            
            return new Connection($pdo, 'sqlite');
        } catch (PDOException $e) {
            throw ORMException::connectionFailed($e->getMessage());
        }
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
        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            
            $defaultOptions = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];
            
            $options = array_merge($defaultOptions, $options);
            
            $pdo = new PDO($dsn, $username, $password, $options);
            
            return new Connection($pdo, 'mariadb');
        } catch (PDOException $e) {
            throw ORMException::connectionFailed($e->getMessage());
        }
    }

    /**
     * Create a connection from configuration array
     */
    public static function createFromConfig(array $config): ConnectionInterface
    {
        $driver = $config['driver'] ?? throw new \InvalidArgumentException('Driver is required');
        
        return match ($driver) {
            'sqlite' => self::createSqliteConnection($config['path'] ?? ':memory:'),
            'mysql', 'mariadb' => self::createMariaDbConnection(
                $config['host'] ?? 'localhost',
                $config['database'] ?? throw new \InvalidArgumentException('Database name is required'),
                $config['username'] ?? throw new \InvalidArgumentException('Username is required'),
                $config['password'] ?? '',
                $config['port'] ?? 3306,
                $config['options'] ?? []
            ),
            default => throw new \InvalidArgumentException("Unsupported driver: {$driver}"),
        };
    }
}
