<?php

declare(strict_types=1);

namespace DemoMigrations;

use Fduarte42\Aurum\Migration\AbstractMigration;
use Fduarte42\Aurum\Connection\ConnectionInterface;

/**
 * Create users table
 */
final class Version20250825203735 extends AbstractMigration
{
    public function getVersion(): string
    {
        return '20250825203735';
    }

    public function getDescription(): string
    {
        return 'Create users table';
    }

    
    public function up(ConnectionInterface $connection): void
    {
        $this->schemaBuilder->createTable('users')
            ->uuidPrimaryKey()
            ->string('email', ['length' => 255, 'not_null' => true])
            ->string('name', ['length' => 255, 'not_null' => true])
            ->datetime('created_at', ['not_null' => true])
            ->unique(['email'])
            ->create();
    }

    
    public function down(ConnectionInterface $connection): void
    {
        $this->schemaBuilder->dropTable('users');
    }
}