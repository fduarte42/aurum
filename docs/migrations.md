# Migration System

Aurum's migration system provides version control for your database schema, allowing you to evolve your database structure over time while maintaining consistency across environments.

## Overview

Migrations in Aurum are PHP classes that define how to transform your database schema from one version to another. Each migration has:

- **Version**: Unique timestamp-based identifier
- **Up method**: Applies the migration (forward)
- **Down method**: Reverts the migration (backward)
- **Description**: Human-readable description of changes

## Basic Migration Structure

```php
<?php

declare(strict_types=1);

namespace App\Migrations;

use Fduarte42\Aurum\Migration\AbstractMigration;
use Fduarte42\Aurum\Connection\ConnectionInterface;

final class Version20231201120000 extends AbstractMigration
{
    public function getVersion(): string
    {
        return '20231201120000';
    }

    public function getDescription(): string
    {
        return 'Create users table';
    }

    public function up(ConnectionInterface $connection): void
    {
        $connection->execute('
            CREATE TABLE users (
                id CHAR(36) PRIMARY KEY,
                email VARCHAR(255) UNIQUE NOT NULL,
                name VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            )
        ');
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->execute('DROP TABLE users');
    }
}
```

## Creating Migrations

### Using the CLI (Recommended)

The easiest way to create migrations is using the Aurum CLI:

```bash
# Generate migration from entity differences
php bin/aurum-cli.php migration diff --entities="User,Post" --name="CreateUserAndPostTables"

# Generate migration for all entities in a namespace
php bin/aurum-cli.php migration diff --namespace="App\Entity" --name="InitialSchema"

# Auto-discover all entities and generate migration
php bin/aurum-cli.php migration diff --name="UpdateSchema"
```

### Manual Migration Creation

You can also create migrations manually using the MigrationService:

```php
use Fduarte42\Aurum\Migration\MigrationService;

$migrationService = MigrationService::create($connection, 'migrations', 'App\\Migrations');
$version = $migrationService->generate('Create users table');

echo "Created migration: Version{$version}\n";
```

### Migration File Structure

Migrations are stored in a dedicated directory (default: `migrations/`):

```
migrations/
├── Version20231201120000.php  # Create users table
├── Version20231201130000.php  # Add posts table
├── Version20231202090000.php  # Add user_posts relationship
└── Version20231202100000.php  # Add indexes
```

## Migration Operations

### Running Migrations

```php
use Fduarte42\Aurum\Migration\MigrationService;

$migrationService = MigrationService::create($connection);

// Migrate to latest version
$migrationService->migrate();

// Migrate with verbose output
$migrationService->setVerbose(true);
$migrationService->migrate();
```

### Rolling Back Migrations

```php
// Rollback the last migration
$migrationService->rollback();

// Rollback multiple migrations
$migrationService->rollback(3); // Rollback last 3 migrations
```

### Migration Status

```php
// Get migration status
$status = $migrationService->status();

foreach ($status as $migration) {
    echo sprintf(
        "%s %s - %s\n",
        $migration['executed'] ? '✓' : '✗',
        $migration['version'],
        $migration['description']
    );
}
```

### Listing Migrations

```php
// List all migrations
$migrations = $migrationService->list();

foreach ($migrations as $migration) {
    echo "{$migration['version']} - {$migration['description']}\n";
}
```

## Schema Builder Integration

Aurum migrations can use a fluent schema builder for database-agnostic operations:

```php
public function up(ConnectionInterface $connection): void
{
    $schema = new SchemaBuilder($connection);
    
    $schema->create('users', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('email', 255)->unique();
        $table->string('name', 255);
        $table->timestamps();
    });
    
    $schema->create('posts', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('title', 255);
        $table->text('content');
        $table->uuid('author_id');
        $table->foreign('author_id')->references('id')->on('users');
        $table->timestamps();
    });
}
```

## Advanced Migration Patterns

### Data Migrations

Migrations can also transform data:

```php
public function up(ConnectionInterface $connection): void
{
    // Add new column
    $connection->execute('ALTER TABLE users ADD COLUMN full_name VARCHAR(255)');
    
    // Migrate existing data
    $users = $connection->fetchAll('SELECT id, first_name, last_name FROM users');
    
    foreach ($users as $user) {
        $fullName = trim($user['first_name'] . ' ' . $user['last_name']);
        $connection->execute(
            'UPDATE users SET full_name = ? WHERE id = ?',
            [$fullName, $user['id']]
        );
    }
    
    // Remove old columns
    $connection->execute('ALTER TABLE users DROP COLUMN first_name');
    $connection->execute('ALTER TABLE users DROP COLUMN last_name');
}
```

### Conditional Migrations

```php
public function up(ConnectionInterface $connection): void
{
    // Check if column exists before adding
    $columns = $connection->fetchAll("PRAGMA table_info(users)");
    $columnNames = array_column($columns, 'name');
    
    if (!in_array('phone', $columnNames)) {
        $connection->execute('ALTER TABLE users ADD COLUMN phone VARCHAR(20)');
    }
}
```

### Complex Schema Changes

```php
public function up(ConnectionInterface $connection): void
{
    // Create new table with updated structure
    $connection->execute('
        CREATE TABLE users_new (
            id CHAR(36) PRIMARY KEY,
            email VARCHAR(255) UNIQUE NOT NULL,
            username VARCHAR(100) UNIQUE NOT NULL,
            profile_data JSON,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )
    ');
    
    // Copy data from old table
    $connection->execute('
        INSERT INTO users_new (id, email, username, created_at, updated_at)
        SELECT id, email, email as username, created_at, updated_at
        FROM users
    ');
    
    // Drop old table and rename new one
    $connection->execute('DROP TABLE users');
    $connection->execute('ALTER TABLE users_new RENAME TO users');
}
```

## Migration Configuration

### Basic Configuration

```php
$config = [
    'migrations' => [
        'directory' => 'database/migrations',
        'namespace' => 'Database\\Migrations',
        'table_name' => 'migrations'
    ]
];
```

### Advanced Configuration

```php
$config = [
    'connection' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'myapp',
        'username' => 'user',
        'password' => 'password'
    ],
    'migrations' => [
        'directory' => 'database/migrations',
        'namespace' => 'Database\\Migrations',
        'table_name' => 'doctrine_migration_versions',
        'organize_migrations' => 'year', // Optional: organize by year
        'custom_template' => 'path/to/custom/template.php'
    ]
];
```

## CLI Integration

### Preview Changes

Before creating migrations, preview what changes would be made:

```bash
# Preview changes for specific entities
php bin/aurum-cli.php migration diff --entities="User,Post" --preview

# Preview changes for namespace
php bin/aurum-cli.php migration diff --namespace="App\Entity" --preview

# Preview all changes
php bin/aurum-cli.php migration diff --preview
```

### Generate Migrations

```bash
# Generate migration with descriptive name
php bin/aurum-cli.php migration diff --entities="User" --name="AddPhoneToUser"

# Generate migration for all entities
php bin/aurum-cli.php migration diff --name="UpdateAllTables"

# Save to custom location
php bin/aurum-cli.php migration diff --output="custom-migration.php"
```

## Best Practices

### 1. Descriptive Names

Use clear, descriptive names for your migrations:

```bash
# Good
php bin/aurum-cli.php migration diff --name="AddIndexesToUserTable"
php bin/aurum-cli.php migration diff --name="CreateOrdersAndOrderItemsTables"

# Bad
php bin/aurum-cli.php migration diff --name="UpdateSchema"
php bin/aurum-cli.php migration diff --name="Changes"
```

### 2. Atomic Changes

Keep migrations focused on a single logical change:

```php
// Good - Single purpose
class Version20231201120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email verification fields to users table';
    }
    
    public function up(ConnectionInterface $connection): void
    {
        $connection->execute('ALTER TABLE users ADD COLUMN email_verified_at DATETIME');
        $connection->execute('ALTER TABLE users ADD COLUMN email_verification_token VARCHAR(255)');
    }
}
```

### 3. Reversible Migrations

Always implement the `down()` method:

```php
public function down(ConnectionInterface $connection): void
{
    $connection->execute('ALTER TABLE users DROP COLUMN email_verified_at');
    $connection->execute('ALTER TABLE users DROP COLUMN email_verification_token');
}
```

### 4. Test Migrations

Test both up and down migrations:

```php
public function testMigrationUpAndDown(): void
{
    $migration = new Version20231201120000();
    
    // Test up migration
    $migration->up($this->connection);
    
    // Verify changes
    $columns = $this->connection->fetchAll("PRAGMA table_info(users)");
    $columnNames = array_column($columns, 'name');
    $this->assertContains('email_verified_at', $columnNames);
    
    // Test down migration
    $migration->down($this->connection);
    
    // Verify rollback
    $columns = $this->connection->fetchAll("PRAGMA table_info(users)");
    $columnNames = array_column($columns, 'name');
    $this->assertNotContains('email_verified_at', $columnNames);
}
```

### 5. Backup Before Production

Always backup your database before running migrations in production:

```bash
# MySQL backup
mysqldump -u user -p database > backup_$(date +%Y%m%d_%H%M%S).sql

# SQLite backup
cp database.sqlite database_backup_$(date +%Y%m%d_%H%M%S).sqlite
```

## Environment-Specific Migrations

### Development Workflow

```bash
# 1. Modify entities
# 2. Generate migration
php bin/aurum-cli.php migration diff --name="AddUserPreferences"

# 3. Review generated migration
cat migrations/Version20231201120000.php

# 4. Run migration
php migration-cli.php migrate
```

### Production Deployment

```bash
# 1. Backup database
mysqldump -u user -p production_db > backup.sql

# 2. Run migrations
php migration-cli.php migrate

# 3. Verify deployment
php migration-cli.php status
```

## Troubleshooting

### Common Issues

**Migration fails with "table already exists"**
- Check if migration was partially applied
- Use conditional logic in migrations
- Verify migration status

**Cannot rollback migration**
- Ensure down() method is properly implemented
- Check for data dependencies
- Consider data preservation strategies

**Migration takes too long**
- Break large migrations into smaller ones
- Use batch processing for data migrations
- Consider maintenance windows

### Recovery Strategies

```php
// Mark migration as executed without running it
$migrationService->markAsExecuted('20231201120000');

// Mark migration as not executed
$migrationService->markAsNotExecuted('20231201120000');

// Reset migration table
$migrationService->reset(); // Use with caution!
```

The migration system provides a robust foundation for managing database schema evolution while maintaining data integrity and deployment consistency.
