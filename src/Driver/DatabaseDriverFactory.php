<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Driver;

use Fduarte42\Aurum\Exception\ORMException;
use PDO;
use PDOException;

/**
 * Factory for creating database drivers based on configuration
 * 
 * Supports configuration-based driver selection and extensible driver registration
 * for adding support for additional database systems.
 */
class DatabaseDriverFactory
{
    /**
     * @var array<string, class-string<DatabaseDriverInterface>>
     */
    private static array $driverMap = [
        'sqlite' => SqliteDriver::class,
        'mariadb' => MariaDbDriver::class,
        'mysql' => MariaDbDriver::class, // MySQL uses the same driver as MariaDB
    ];

    /**
     * Create a database driver from configuration
     * 
     * @param array<string, mixed> $config Database configuration
     * @return DatabaseDriverInterface
     * @throws ORMException
     */
    public static function create(array $config): DatabaseDriverInterface
    {
        $driver = $config['driver'] ?? null;
        
        if (!$driver) {
            throw ORMException::configurationError('Database driver not specified in configuration');
        }

        if (!isset(self::$driverMap[$driver])) {
            throw ORMException::configurationError("Unsupported database driver: {$driver}");
        }

        $pdo = self::createPDOConnection($config);
        $driverClass = self::$driverMap[$driver];
        
        return new $driverClass($pdo);
    }

    /**
     * Create a SQLite driver
     * 
     * @param string $path Database file path or ':memory:' for in-memory database
     * @return DatabaseDriverInterface
     * @throws ORMException
     */
    public static function createSqlite(string $path = ':memory:'): DatabaseDriverInterface
    {
        return self::create([
            'driver' => 'sqlite',
            'path' => $path,
        ]);
    }

    /**
     * Create a MariaDB/MySQL driver
     * 
     * @param string $host Database host
     * @param string $database Database name
     * @param string $username Database username
     * @param string $password Database password
     * @param int $port Database port
     * @param array<string, mixed> $options Additional options
     * @return DatabaseDriverInterface
     * @throws ORMException
     */
    public static function createMariaDb(
        string $host,
        string $database,
        string $username,
        string $password,
        int $port = 3306,
        array $options = []
    ): DatabaseDriverInterface {
        return self::create([
            'driver' => 'mariadb',
            'host' => $host,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'port' => $port,
            'options' => $options,
        ]);
    }

    /**
     * Register a custom database driver
     * 
     * @param string $name Driver name (e.g., 'postgresql', 'oracle')
     * @param class-string<DatabaseDriverInterface> $driverClass Driver class name
     */
    public static function registerDriver(string $name, string $driverClass): void
    {
        if (!is_subclass_of($driverClass, DatabaseDriverInterface::class)) {
            throw new \InvalidArgumentException(
                "Driver class {$driverClass} must implement " . DatabaseDriverInterface::class
            );
        }

        self::$driverMap[$name] = $driverClass;
    }

    /**
     * Get all registered drivers
     * 
     * @return array<string, class-string<DatabaseDriverInterface>>
     */
    public static function getRegisteredDrivers(): array
    {
        return self::$driverMap;
    }

    /**
     * Check if a driver is registered
     */
    public static function hasDriver(string $name): bool
    {
        return isset(self::$driverMap[$name]);
    }

    /**
     * Create PDO connection from configuration
     * 
     * @param array<string, mixed> $config
     * @return PDO
     * @throws ORMException
     */
    private static function createPDOConnection(array $config): PDO
    {
        $driver = $config['driver'];

        try {
            return match ($driver) {
                'sqlite' => self::createSqlitePDO($config),
                'mariadb', 'mysql' => self::createMariaDbPDO($config),
                default => throw ORMException::configurationError("Unsupported driver: {$driver}"),
            };
        } catch (PDOException $e) {
            throw ORMException::connectionFailed($e->getMessage());
        }
    }

    /**
     * Create SQLite PDO connection
     * 
     * @param array<string, mixed> $config
     * @return PDO
     */
    private static function createSqlitePDO(array $config): PDO
    {
        $path = $config['path'] ?? ':memory:';
        $dsn = "sqlite:{$path}";
        
        $options = $config['options'] ?? [];
        
        return new PDO($dsn, null, null, $options);
    }

    /**
     * Create MariaDB/MySQL PDO connection
     * 
     * @param array<string, mixed> $config
     * @return PDO
     */
    private static function createMariaDbPDO(array $config): PDO
    {
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 3306;
        $database = $config['database'] ?? '';
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        $charset = $config['charset'] ?? 'utf8mb4';
        
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
        
        $defaultOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$charset}_unicode_ci",
        ];
        
        $options = array_merge($defaultOptions, $config['options'] ?? []);
        
        return new PDO($dsn, $username, $password, $options);
    }

    /**
     * Create a driver from an existing PDO connection
     * 
     * @param PDO $pdo Existing PDO connection
     * @param string $platform Platform name (sqlite, mariadb, mysql)
     * @return DatabaseDriverInterface
     * @throws ORMException
     */
    public static function createFromPDO(PDO $pdo, string $platform): DatabaseDriverInterface
    {
        if (!isset(self::$driverMap[$platform])) {
            throw ORMException::configurationError("Unsupported platform: {$platform}");
        }

        $driverClass = self::$driverMap[$platform];
        return new $driverClass($pdo);
    }

    /**
     * Auto-detect platform from PDO connection
     * 
     * @param PDO $pdo PDO connection
     * @return string Platform name
     */
    public static function detectPlatform(PDO $pdo): string
    {
        $driverName = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        
        return match ($driverName) {
            'sqlite' => 'sqlite',
            'mysql' => 'mariadb', // Default to MariaDB driver for MySQL connections
            default => throw ORMException::configurationError("Cannot detect platform for PDO driver: {$driverName}"),
        };
    }

    /**
     * Create a driver from an existing PDO connection with auto-detection
     * 
     * @param PDO $pdo Existing PDO connection
     * @return DatabaseDriverInterface
     * @throws ORMException
     */
    public static function createFromPDOWithDetection(PDO $pdo): DatabaseDriverInterface
    {
        $platform = self::detectPlatform($pdo);
        return self::createFromPDO($pdo, $platform);
    }
}
