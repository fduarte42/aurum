#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Aurum CLI Tool
 * 
 * A unified command-line interface for Aurum ORM schema and migration management.
 * 
 * Usage:
 *   php bin/aurum-cli.php schema generate --entities="User,Post" --format=schema-builder
 *   php bin/aurum-cli.php migration diff --entities="User,Post" --name="UpdateSchema"
 *   php bin/aurum-cli.php migration diff --namespace="App\Entity"
 *   php bin/aurum-cli.php schema generate  # Auto-discover all entities
 */

// Find autoloader
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
    __DIR__ . '/../../autoload.php',
    __DIR__ . '/../autoload.php'
];

$autoloadFound = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $autoloadFound = true;
        break;
    }
}

if (!$autoloadFound) {
    echo "❌ Error: Could not find Composer autoloader\n";
    echo "Please run 'composer install' first\n";
    exit(1);
}

use Fduarte42\Aurum\Cli\Application;
use Fduarte42\Aurum\Cli\Command\SchemaCommand;
use Fduarte42\Aurum\Cli\Command\MigrationDiffCommand;

// Load configuration from file if it exists
$configFile = getcwd() . '/aurum.config.php';
$config = [];

if (file_exists($configFile)) {
    $config = require $configFile;
}

// Create and configure the application
$app = new Application($config);

// Register commands
$app->addCommand(new SchemaCommand($config));
$app->addCommand(new MigrationDiffCommand($config));

// Run the application
exit($app->run($argv));
