<?php

declare(strict_types=1);

/**
 * Simple CLI script for running Aurum migrations
 * 
 * Usage:
 *   php migration-cli.php generate "Create users table"
 *   php migration-cli.php migrate
 *   php migration-cli.php rollback
 *   php migration-cli.php status
 *   php migration-cli.php list
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Fduarte42\Aurum\DependencyInjection\ContainerBuilder;
use Fduarte42\Aurum\Migration\MigrationService;

// Configuration - using in-memory database
$config = [
    'connection' => [
        'driver' => 'sqlite',
        'path' => ':memory:'  // In-memory database - no files created!
    ],
    'migrations' => [
        'directory' => __DIR__ . '/migrations',
        'namespace' => 'AppMigrations'
    ]
];

// Create migration service
$container = ContainerBuilder::createORM($config);
$migrationService = $container->get(MigrationService::class);

// Set up output
$migrationService->setVerbose(true);
$migrationService->setOutputWriter(function (string $message) {
    echo "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL;
});

// Parse command line arguments
$command = $argv[1] ?? 'help';
$args = array_slice($argv, 2);

try {
    switch ($command) {
        case 'generate':
        case 'gen':
            if (empty($args[0])) {
                echo "❌ Error: Migration description is required\n";
                echo "Usage: php migration-cli.php generate \"Description of migration\"\n";
                exit(1);
            }
            
            $description = $args[0];
            $version = $migrationService->generate($description);
            
            echo "✅ Generated migration: {$version}\n";
            echo "📁 File: " . $config['migrations']['directory'] . "/Version{$version}.php\n";
            echo "💡 Edit the migration file to add your schema changes\n";
            break;

        case 'migrate':
        case 'up':
            echo "🚀 Running migrations...\n";
            $migrationService->migrate();
            echo "✅ Migration completed!\n";
            break;

        case 'rollback':
        case 'down':
            echo "🔄 Rolling back last migration...\n";
            $migrationService->rollback();
            echo "✅ Rollback completed!\n";
            break;

        case 'status':
            $status = $migrationService->status();
            
            echo "📊 Migration Status\n";
            echo "==================\n";
            echo "Current version: " . ($status['current_version'] ?? 'none') . "\n";
            echo "Executed migrations: {$status['executed_migrations']}\n";
            echo "Pending migrations: {$status['pending_migrations']}\n";
            echo "Total migrations: {$status['total_migrations']}\n";
            
            if ($status['pending_migrations'] > 0) {
                echo "\n💡 Run 'php migration-cli.php migrate' to execute pending migrations\n";
            }
            break;

        case 'list':
            $migrations = $migrationService->list();
            
            echo "📋 Migration List\n";
            echo "=================\n";
            
            if (empty($migrations)) {
                echo "No migrations found.\n";
                echo "💡 Run 'php migration-cli.php generate \"Description\"' to create your first migration\n";
                break;
            }
            
            foreach ($migrations as $migration) {
                $status = $migration['executed'] ? '✅' : '⏳';
                echo "{$status} {$migration['version']}: {$migration['description']}\n";
            }
            break;

        case 'dry-run':
            echo "🧪 Dry run mode - no changes will be made\n";
            $migrationService->setDryRun(true);
            $migrationService->migrate();
            echo "✅ Dry run completed!\n";
            break;

        case 'reset':
            echo "⚠️  This will clear all migration tracking (but keep your data)\n";
            echo "Are you sure? (y/N): ";
            $handle = fopen("php://stdin", "r");
            $line = fgets($handle);
            fclose($handle);
            
            if (trim(strtolower($line)) === 'y') {
                $migrationService->reset();
                echo "✅ Migration tracking reset!\n";
            } else {
                echo "❌ Reset cancelled\n";
            }
            break;

        case 'help':
        case '--help':
        case '-h':
        default:
            echo "🔧 Aurum Migration CLI\n";
            echo "======================\n\n";
            echo "Available commands:\n";
            echo "  generate <description>  Generate a new migration file\n";
            echo "  migrate                 Run all pending migrations\n";
            echo "  rollback               Rollback the last migration\n";
            echo "  status                 Show migration status\n";
            echo "  list                   List all migrations\n";
            echo "  dry-run                Test migrations without executing\n";
            echo "  reset                  Clear migration tracking\n";
            echo "  help                   Show this help message\n\n";
            echo "Examples:\n";
            echo "  php migration-cli.php generate \"Create users table\"\n";
            echo "  php migration-cli.php migrate\n";
            echo "  php migration-cli.php status\n\n";
            echo "Configuration:\n";
            echo "  Database: {$config['connection']['driver']} ({$config['connection']['path']})\n";
            echo "  Migrations: {$config['migrations']['directory']}\n";
            echo "  Namespace: {$config['migrations']['namespace']}\n";
            break;
    }

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    
    if (isset($argv) && in_array('--debug', $argv)) {
        echo "\nStack trace:\n";
        echo $e->getTraceAsString() . "\n";
    } else {
        echo "💡 Use --debug flag for detailed error information\n";
    }
    
    exit(1);
}

echo "\n";
