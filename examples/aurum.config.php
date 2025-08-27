<?php

/**
 * Example Aurum Configuration File
 * 
 * This file demonstrates how to configure Aurum for CLI tools and applications.
 * Copy this file to your project root as 'aurum.config.php' to use with CLI tools.
 */

return [
    'connection' => [
        'driver' => 'sqlite',
        'path' => ':memory:'  // Use in-memory database for examples
    ],
    'migrations' => [
        'directory' => __DIR__ . '/migrations',
        'namespace' => 'App\\Migrations',
        'table_name' => 'aurum_migrations'
    ],
    'entities' => [
        'paths' => [
            __DIR__ . '/src/Entity',
            __DIR__ . '/../tests/Fixtures'  // Adjusted path since we're in examples/
        ]
    ]
];
