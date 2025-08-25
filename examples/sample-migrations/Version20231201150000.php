<?php

declare(strict_types=1);

namespace SampleMigrations;

use Fduarte42\Aurum\Migration\AbstractMigration;
use Fduarte42\Aurum\Connection\ConnectionInterface;

/**
 * Data migration: Migrate legacy user data and clean up
 */
final class Version20231201150000 extends AbstractMigration
{
    public function getVersion(): string
    {
        return '20231201150000';
    }

    public function getDescription(): string
    {
        return 'Data migration: Migrate legacy user data and clean up';
    }

    public function up(ConnectionInterface $connection): void
    {
        // ✅ Data migrations should be carefully planned
        $this->write('🔄 Starting data migration...');

        // ✅ Check if legacy data exists
        if (!$this->tableExists('legacy_users')) {
            $this->write('ℹ️  No legacy users table found, skipping migration');
            return;
        }

        $legacyUsers = $this->connection->fetchAll('SELECT * FROM legacy_users');
        $this->write("📊 Found {count($legacyUsers)} legacy users to migrate");

        $migratedCount = 0;
        $skippedCount = 0;

        foreach ($legacyUsers as $legacyUser) {
            try {
                // ✅ Validate data before migration
                if (empty($legacyUser['email']) || !filter_var($legacyUser['email'], FILTER_VALIDATE_EMAIL)) {
                    $this->write("⚠️  Skipping user with invalid email: {$legacyUser['email']}");
                    $skippedCount++;
                    continue;
                }

                // ✅ Check if user already exists
                $existingUser = $this->connection->fetchOne(
                    'SELECT id FROM users WHERE email = ?',
                    [$legacyUser['email']]
                );

                if ($existingUser) {
                    $this->write("ℹ️  User already exists: {$legacyUser['email']}");
                    $skippedCount++;
                    continue;
                }

                // ✅ Transform and insert data
                $this->connection->execute('
                    INSERT INTO users (
                        id, email, username, first_name, last_name, 
                        password_hash, is_active, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ', [
                    $this->generateUuid(),
                    $legacyUser['email'],
                    $legacyUser['login'] ?? $this->generateUsername($legacyUser['email']),
                    $legacyUser['fname'] ?? 'Unknown',
                    $legacyUser['lname'] ?? 'User',
                    $legacyUser['password'] ?? password_hash('temp123', PASSWORD_DEFAULT),
                    $legacyUser['active'] ?? 1,
                    $legacyUser['created'] ?? date('Y-m-d H:i:s'),
                    date('Y-m-d H:i:s')
                ]);

                $migratedCount++;

            } catch (\Exception $e) {
                $this->write("❌ Error migrating user {$legacyUser['email']}: {$e->getMessage()}");
                $skippedCount++;
            }
        }

        $this->write("✅ Migration completed: {$migratedCount} migrated, {$skippedCount} skipped");

        // ✅ Clean up legacy table after successful migration
        if ($migratedCount > 0 && $skippedCount === 0) {
            $this->write('🧹 Cleaning up legacy users table...');
            $this->addSql('DROP TABLE legacy_users');
            $this->write('✅ Legacy users table removed');
        } else {
            $this->write('⚠️  Legacy table preserved due to migration issues');
        }
    }

    public function down(ConnectionInterface $connection): void
    {
        // ✅ Data migrations are often irreversible
        $this->write('⚠️  This data migration cannot be automatically reversed');
        $this->write('⚠️  Manual intervention required to restore legacy data');
        
        // ✅ Provide guidance for manual rollback
        $this->write('💡 To rollback manually:');
        $this->write('   1. Restore legacy_users table from backup');
        $this->write('   2. Remove migrated users from users table');
        $this->write('   3. Verify data integrity');
    }

    public function isTransactional(): bool
    {
        // ✅ Data migrations should usually be transactional
        return true;
    }

    /**
     * Generate a UUID for new users
     */
    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Generate username from email
     */
    private function generateUsername(string $email): string
    {
        $username = strstr($email, '@', true);
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $username);
    }
}
