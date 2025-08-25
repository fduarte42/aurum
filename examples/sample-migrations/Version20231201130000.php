<?php

declare(strict_types=1);

namespace SampleMigrations;

use Fduarte42\Aurum\Migration\AbstractMigration;
use Fduarte42\Aurum\Connection\ConnectionInterface;

/**
 * Create posts table with foreign key relationships
 */
final class Version20231201130000 extends AbstractMigration
{
    public function getVersion(): string
    {
        return '20231201130000';
    }

    public function getDescription(): string
    {
        return 'Create posts table with foreign key relationships';
    }

    public function getDependencies(): array
    {
        // ✅ Specify dependencies to ensure proper execution order
        return ['20231201120000']; // Depends on users table
    }

    public function up(ConnectionInterface $connection): void
    {
        // ✅ Check if required tables exist before proceeding
        $this->abortIf(
            !$this->tableExists('users'),
            'Users table must exist before creating posts table'
        );

        $this->schemaBuilder->createTable('posts')
            ->uuidPrimaryKey('id')
            ->string('title', ['length' => 255, 'not_null' => true])
            ->string('slug', ['length' => 255, 'not_null' => true])
            ->text('content', ['not_null' => true])
            ->text('excerpt', ['nullable' => true])
            ->string('status', ['length' => 20, 'default' => 'draft', 'not_null' => true])
            ->boolean('featured', ['default' => false, 'not_null' => true])
            ->integer('view_count', ['default' => 0, 'not_null' => true])
            ->datetime('published_at', ['nullable' => true])
            ->uuid('author_id', ['not_null' => true])
            ->timestamps()
            
            // Foreign key relationship
            ->foreign(['author_id'], 'users', ['id'], [
                'name' => 'fk_posts_author',
                'on_delete' => 'CASCADE',
                'on_update' => 'CASCADE'
            ])
            
            // Indexes for performance
            ->unique(['slug'], 'idx_posts_slug_unique')
            ->index(['status'], 'idx_posts_status')
            ->index(['featured'], 'idx_posts_featured')
            ->index(['published_at'], 'idx_posts_published')
            ->index(['author_id'], 'idx_posts_author')
            ->index(['created_at'], 'idx_posts_created')
            
            ->create();

        // ✅ Create additional indexes for complex queries
        $this->schemaBuilder->createIndex('posts', ['status', 'published_at'], 'idx_posts_status_published');
        $this->schemaBuilder->createIndex('posts', ['author_id', 'status'], 'idx_posts_author_status');

        $this->write('✅ Posts table created with foreign key constraints');
        $this->write('✅ Performance indexes created for common query patterns');
    }

    public function down(ConnectionInterface $connection): void
    {
        // ✅ Drop in reverse order - indexes first, then table
        $this->schemaBuilder->dropIndex('posts', 'idx_posts_status_published');
        $this->schemaBuilder->dropIndex('posts', 'idx_posts_author_status');
        $this->schemaBuilder->dropTable('posts');
        
        $this->write('✅ Posts table and indexes dropped');
    }
}
