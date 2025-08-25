<?php

declare(strict_types=1);

namespace SampleMigrations;

use Fduarte42\Aurum\Migration\AbstractMigration;
use Fduarte42\Aurum\Connection\ConnectionInterface;

/**
 * Add user profile fields and optimize existing indexes
 */
final class Version20231201140000 extends AbstractMigration
{
    public function getVersion(): string
    {
        return '20231201140000';
    }

    public function getDescription(): string
    {
        return 'Add user profile fields and optimize existing indexes';
    }

    public function getDependencies(): array
    {
        return ['20231201120000']; // Depends on users table
    }

    public function up(ConnectionInterface $connection): void
    {
        // ✅ Use conditional logic to handle different scenarios
        $this->skipIf(
            $this->columnExists('users', 'bio'),
            'User profile fields already exist'
        );

        // ✅ Add new columns to existing table
        $this->schemaBuilder->alterTable('users')
            ->text('bio', ['nullable' => true])
            ->string('avatar_url', ['length' => 500, 'nullable' => true])
            ->string('website', ['length' => 255, 'nullable' => true])
            ->string('location', ['length' => 100, 'nullable' => true])
            ->date('birth_date', ['nullable' => true])
            ->string('timezone', ['length' => 50, 'default' => 'UTC'])
            ->string('locale', ['length' => 10, 'default' => 'en'])
            ->alter();

        // ✅ Add indexes for new searchable fields
        $this->schemaBuilder->createIndex('users', ['location'], 'idx_users_location');
        $this->schemaBuilder->createIndex('users', ['timezone'], 'idx_users_timezone');

        // ✅ Warn about potential performance impact
        $this->warnIf(
            $this->getTableRowCount('users') > 10000,
            'Adding columns to large users table may take some time'
        );

        // ✅ Update existing data with sensible defaults
        $this->addSql("
            UPDATE users 
            SET timezone = 'UTC', locale = 'en' 
            WHERE timezone IS NULL OR locale IS NULL
        ");

        $this->write('✅ User profile fields added');
        $this->write('✅ Default values set for existing users');
    }

    public function down(ConnectionInterface $connection): void
    {
        // ✅ For SQLite, we need to handle column drops carefully
        if ($this->connection->getPlatform() === 'sqlite') {
            $this->write('⚠️  SQLite does not support dropping columns directly');
            $this->write('⚠️  Manual intervention required to remove profile fields');
            return;
        }

        // ✅ For other databases, drop the added columns
        $this->schemaBuilder->dropIndex('users', 'idx_users_location');
        $this->schemaBuilder->dropIndex('users', 'idx_users_timezone');

        $this->schemaBuilder->alterTable('users')
            ->dropColumn('bio')
            ->dropColumn('avatar_url')
            ->dropColumn('website')
            ->dropColumn('location')
            ->dropColumn('birth_date')
            ->dropColumn('timezone')
            ->dropColumn('locale')
            ->alter();

        $this->write('✅ User profile fields removed');
    }

    /**
     * Helper method to get table row count
     */
    private function getTableRowCount(string $tableName): int
    {
        $result = $this->connection->fetchOne("SELECT COUNT(*) as count FROM {$tableName}");
        return (int) $result['count'];
    }

    public function isTransactional(): bool
    {
        // ✅ Large data migrations might need to be non-transactional
        return $this->getTableRowCount('users') < 50000;
    }
}
