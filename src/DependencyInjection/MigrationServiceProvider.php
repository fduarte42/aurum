<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\DependencyInjection;

use Fduarte42\Aurum\Connection\ConnectionInterface;
use Fduarte42\Aurum\Migration\MigrationConfiguration;
use Fduarte42\Aurum\Migration\MigrationService;
use Psr\Container\ContainerInterface;

/**
 * Service provider for migration services
 */
class MigrationServiceProvider implements ServiceProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Migration Configuration
        $container->set(MigrationConfiguration::class, function (ContainerInterface $container) {
            $config = $container->has('config') ? $container->get('config') : [];
            $migrationConfig = $config['migrations'] ?? [];

            $migrationsDirectory = $migrationConfig['directory'] ?? self::getDefaultMigrationsDirectory();
            $migrationsNamespace = $migrationConfig['namespace'] ?? 'Migrations';

            $configuration = new MigrationConfiguration($migrationsDirectory, $migrationsNamespace);

            if (isset($migrationConfig['table_name'])) {
                $configuration->setMigrationTableName($migrationConfig['table_name']);
            }

            if (isset($migrationConfig['template'])) {
                $configuration->setMigrationTemplate($migrationConfig['template']);
            }

            return $configuration;
        });

        // Migration Service
        $container->set(MigrationService::class, function (ContainerInterface $container) {
            $connection = $container->get(ConnectionInterface::class);
            $configuration = $container->get(MigrationConfiguration::class);

            return new MigrationService($connection, $configuration);
        });

        // Alias for easier access
        $container->set('migration.service', function (ContainerInterface $container) {
            return $container->get(MigrationService::class);
        });
    }

    /**
     * Get the default migrations directory
     */
    private static function getDefaultMigrationsDirectory(): string
    {
        // Try to find the project root by looking for composer.json
        $currentDir = __DIR__;
        $maxLevels = 10;
        
        for ($i = 0; $i < $maxLevels; $i++) {
            if (file_exists($currentDir . '/composer.json')) {
                $migrationsDir = $currentDir . '/migrations';
                
                // Create directory if it doesn't exist
                if (!is_dir($migrationsDir)) {
                    mkdir($migrationsDir, 0755, true);
                }
                
                return $migrationsDir;
            }
            
            $parentDir = dirname($currentDir);
            if ($parentDir === $currentDir) {
                break;
            }
            $currentDir = $parentDir;
        }

        // Fallback
        $fallbackDir = getcwd() . '/migrations';
        if (!is_dir($fallbackDir)) {
            mkdir($fallbackDir, 0755, true);
        }
        
        return $fallbackDir;
    }

    /**
     * {@inheritdoc}
     */
    public function getProvidedServices(): array
    {
        return [
            MigrationConfiguration::class,
            MigrationService::class,
            'migration.service',
        ];
    }
}
