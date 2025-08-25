<?php

declare(strict_types=1);

namespace SampleMigrations;

use Fduarte42\Aurum\Migration\AbstractMigration;
use Fduarte42\Aurum\Connection\ConnectionInterface;

/**
 * Create users table with proper indexing and constraints
 */
final class Version20231201120000 extends AbstractMigration
{
    public function getVersion(): string
    {
        return '20231201120000';
    }

    public function getDescription(): string
    {
        return 'Create users table with proper indexing and constraints';
    }

    public function up(ConnectionInterface $connection): void
    {
        // ✅ Using schema builder for clean, database-agnostic DDL
        $this->schemaBuilder->createTable('users')
            ->uuidPrimaryKey('id')
            ->string('email', ['length' => 255, 'not_null' => true])
            ->string('username', ['length' => 100, 'not_null' => true])
            ->string('first_name', ['length' => 100, 'not_null' => true])
            ->string('last_name', ['length' => 100, 'not_null' => true])
            ->string('password_hash', ['length' => 255, 'not_null' => true])
            ->boolean('is_active', ['default' => true, 'not_null' => true])
            ->boolean('email_verified', ['default' => false, 'not_null' => true])
            ->datetime('email_verified_at', ['nullable' => true])
            ->datetime('last_login_at', ['nullable' => true])
            ->timestamps() // Adds created_at and updated_at
            
            // Indexes for performance
            ->unique(['email'], 'idx_users_email_unique')
            ->unique(['username'], 'idx_users_username_unique')
            ->index(['is_active'], 'idx_users_active')
            ->index(['email_verified'], 'idx_users_verified')
            ->index(['created_at'], 'idx_users_created')
            
            ->create();

        // ✅ Add some initial data if needed
        $this->addSql("
            INSERT INTO users (id, email, username, first_name, last_name, password_hash, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            '01234567-89ab-cdef-0123-456789abcdef',
            'admin@example.com',
            'admin',
            'System',
            'Administrator',
            password_hash('admin123', PASSWORD_DEFAULT),
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        ]);

        $this->write('✅ Users table created with proper constraints and indexes');
        $this->write('✅ Default admin user created');
    }

    public function down(ConnectionInterface $connection): void
    {
        // ✅ Always provide a way to rollback
        $this->schemaBuilder->dropTable('users');
        $this->write('✅ Users table dropped');
    }

    public function isTransactional(): bool
    {
        // ✅ Most DDL operations should be transactional
        return true;
    }
}
