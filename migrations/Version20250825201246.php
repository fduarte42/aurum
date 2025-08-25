<?php

declare(strict_types=1);

namespace Migrations;

use Fduarte42\Aurum\Migration\AbstractMigration;
use Fduarte42\Aurum\Connection\ConnectionInterface;

/**
 * Create test table
 */
final class Version20250825201246 extends AbstractMigration
{
    public function getVersion(): string
    {
        return '20250825201246';
    }

    public function getDescription(): string
    {
        return 'Create test table';
    }

    public function up(ConnectionInterface $connection): void
    {
        // Add your migration logic here
        // Example:
        // $this->addSql('CREATE TABLE example (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        
        // Or use the schema builder:
        // $this->schemaBuilder->createTable('example')
        //     ->addColumn('id', 'integer', ['primary_key' => true])
        //     ->addColumn('name', 'string', ['length' => 255, 'not_null' => true])
        //     ->create();
    }

    public function down(ConnectionInterface $connection): void
    {
        // Add your rollback logic here
        // Example:
        // $this->addSql('DROP TABLE example');
        
        // Or use the schema builder:
        // $this->schemaBuilder->dropTable('example');
    }
}