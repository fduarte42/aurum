<?php

declare(strict_types=1);

namespace DemoMigrations;

use Fduarte42\Aurum\Migration\AbstractMigration;
use Fduarte42\Aurum\Connection\ConnectionInterface;

/**
 * Create posts table
 */
final class Version20250825203736 extends AbstractMigration
{
    public function getVersion(): string
    {
        return '20250825203736';
    }

    public function getDescription(): string
    {
        return 'Create posts table';
    }

    
    public function up(ConnectionInterface $connection): void
    {
        $this->schemaBuilder->createTable('posts')
            ->uuidPrimaryKey()
            ->string('title', ['length' => 255, 'not_null' => true])
            ->text('content', ['not_null' => true])
            ->boolean('published', ['default' => false, 'not_null' => true])
            ->datetime('created_at', ['not_null' => true])
            ->uuid('author_id', ['nullable' => true])
            ->foreign(['author_id'], 'users', ['id'], ['on_delete' => 'SET NULL'])
            ->index(['published'])
            ->index(['created_at'])
            ->create();
    }

    
    public function down(ConnectionInterface $connection): void
    {
        $this->schemaBuilder->dropTable('posts');
    }
}