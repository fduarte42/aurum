# CLI Tools Guide

Aurum provides a powerful unified command-line interface through `bin/aurum-cli.php` that helps with schema generation, migration management, and development workflows.

## Overview

The Aurum CLI consolidates all database and schema operations into a single, easy-to-use tool:

```bash
php bin/aurum-cli.php <command> [subcommand] [options]
```

## Global Options

- `--help, -h` - Show help information
- `--version, -v` - Show version information  
- `--debug` - Show detailed error information

## Schema Generation

The schema command generates database schema code from your entity metadata.

### Basic Usage

```bash
# Generate SchemaBuilder code for specific entities
php bin/aurum-cli.php schema generate --entities="User,Post" --format=schema-builder

# Generate SQL DDL for all entities in a namespace
php bin/aurum-cli.php schema generate --namespace="App\Entity" --format=sql

# Auto-discover and generate schema for all entities
php bin/aurum-cli.php schema generate --format=both
```

### Schema Command Options

- `--entities=<list>` - Comma-separated list of entity classes
- `--namespace=<ns>` - Generate schema for all entities in namespace
- `--format=<format>` - Output format: `schema-builder`, `sql`, `both` (default: schema-builder)
- `--output=<file>` - Output file path (default: output to console)

### Entity Selection Methods

#### 1. Specific Entities

```bash
# Specify exact entity classes
php bin/aurum-cli.php schema generate --entities="User,Post,Category"

# Use fully qualified class names
php bin/aurum-cli.php schema generate --entities="App\Entity\User,App\Entity\Post"
```

#### 2. Namespace-based Selection

```bash
# Process all entities in a namespace
php bin/aurum-cli.php schema generate --namespace="App\Entity"

# Works with any namespace
php bin/aurum-cli.php schema generate --namespace="MyApp\Domain\Model"
```

#### 3. Auto-discovery

```bash
# Automatically find and process all registered entities
php bin/aurum-cli.php schema generate
```

### Output Formats

#### SchemaBuilder Format

Generates Laravel-style fluent syntax for use in migrations:

```php
Schema::create('users', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('email', 255)->unique();
    $table->string('name', 255);
    $table->timestamps();
});
```

#### SQL DDL Format

Generates raw SQL statements:

```sql
CREATE TABLE users (
    id CHAR(36) PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);
```

#### Both Formats

Generates both SchemaBuilder and SQL DDL output.

### Examples

```bash
# Generate schema for User entity and save to file
php bin/aurum-cli.php schema generate --entities="User" --format=sql --output=user_schema.sql

# Generate SchemaBuilder code for all entities in App\Entity namespace
php bin/aurum-cli.php schema generate --namespace="App\Entity" --format=schema-builder

# Auto-discover all entities and generate both formats
php bin/aurum-cli.php schema generate --format=both
```

## Migration Diff

The migration diff command compares your current database schema with your entity definitions and generates migration code.

### Basic Usage

```bash
# Preview migration diff without creating files
php bin/aurum-cli.php migration diff --entities="User,Post" --preview

# Generate migration file
php bin/aurum-cli.php migration diff --entities="User,Post" --name="UpdateUserSchema"

# Process all entities in a namespace
php bin/aurum-cli.php migration diff --namespace="App\Entity" --preview
```

### Migration Diff Options

- `--entities=<list>` - Comma-separated list of entity classes
- `--namespace=<ns>` - Generate diff for all entities in namespace
- `--name=<name>` - Migration name (generates migration file)
- `--output=<file>` - Output file path (custom migration file)
- `--preview` - Preview migration diff without creating files

### Output Modes

#### Preview Mode

Shows the migration diff without creating any files:

```bash
php bin/aurum-cli.php migration diff --preview
```

Output:
```
UP MIGRATION (Current -> Target)
========================================
    public function up(ConnectionInterface $connection): void
    {
        $connection->execute('ALTER TABLE users ADD COLUMN phone VARCHAR(20)');
    }

DOWN MIGRATION (Target -> Current)  
========================================
    public function down(ConnectionInterface $connection): void
    {
        $connection->execute('ALTER TABLE users DROP COLUMN phone');
    }
```

#### Migration File Generation

Creates an official migration file with version number:

```bash
php bin/aurum-cli.php migration diff --name="AddPhoneToUsers"
```

Creates: `migrations/Version20231201120000.php`

#### Custom Output File

Saves migration to a custom file:

```bash
php bin/aurum-cli.php migration diff --output=my-migration.php
```

### Examples

```bash
# Preview changes for specific entities
php bin/aurum-cli.php migration diff --entities="User,Post" --preview

# Generate migration for all entities in namespace
php bin/aurum-cli.php migration diff --namespace="App\Entity" --name="UpdateSchema"

# Auto-discover all entities and create migration
php bin/aurum-cli.php migration diff --name="UpdateAllEntities"

# Save migration to custom location
php bin/aurum-cli.php migration diff --entities="User" --output=migrations/custom_user_migration.php
```

## Configuration

### Project Configuration

Create an `aurum.config.php` file in your project root:

```php
<?php
return [
    'connection' => [
        'driver' => 'sqlite',
        'path' => 'database.sqlite'
    ],
    'migrations' => [
        'directory' => 'migrations',
        'namespace' => 'App\\Migrations',
        'table_name' => 'aurum_migrations'
    ]
];
```

### Environment-specific Configuration

Use different configurations for different environments:

```php
<?php
// aurum.config.php
$env = $_ENV['APP_ENV'] ?? 'development';

$configs = [
    'development' => [
        'connection' => [
            'driver' => 'sqlite',
            'path' => 'dev.sqlite'
        ]
    ],
    'testing' => [
        'connection' => [
            'driver' => 'sqlite', 
            'path' => ':memory:'
        ]
    ],
    'production' => [
        'connection' => [
            'driver' => 'mysql',
            'host' => $_ENV['DB_HOST'],
            'database' => $_ENV['DB_NAME'],
            'username' => $_ENV['DB_USER'],
            'password' => $_ENV['DB_PASS']
        ]
    ]
];

return $configs[$env] ?? $configs['development'];
```

## Advanced Usage

### Entity Class Resolution

The CLI automatically resolves entity class names using common conventions:

```bash
# These are all equivalent if you have App\Entity\User:
php bin/aurum-cli.php schema generate --entities="User"
php bin/aurum-cli.php schema generate --entities="App\Entity\User"
```

Resolution order:
1. Exact class name as provided
2. `App\Entity\{ClassName}`
3. `Entity\{ClassName}`
4. `Entities\{ClassName}`
5. `Model\{ClassName}`
6. `Models\{ClassName}`

### Namespace Discovery

When using `--namespace`, the CLI scans all loaded classes:

```bash
# Finds all entities in the namespace
php bin/aurum-cli.php schema generate --namespace="MyApp\Domain"
```

### Auto-discovery

When no entity selection is provided, the CLI automatically discovers all entities:

```bash
# Processes all registered entities
php bin/aurum-cli.php schema generate
php bin/aurum-cli.php migration diff --preview
```

## Integration with Development Workflow

### Development Workflow

1. **Create/modify entities**
2. **Preview changes**: `php bin/aurum-cli.php migration diff --preview`
3. **Generate migration**: `php bin/aurum-cli.php migration diff --name="DescriptiveChangeName"`
4. **Run migration**: Use your migration system to apply changes

### CI/CD Integration

```bash
# In your CI pipeline
php bin/aurum-cli.php migration diff --preview
# Fail if there are uncommitted schema changes
```

### IDE Integration

Many IDEs can be configured to run CLI commands:

- **PHPStorm**: Add as External Tools
- **VS Code**: Add as tasks in tasks.json
- **Vim/Neovim**: Create custom commands

## Troubleshooting

### Common Issues

**"No entities found"**
- Ensure your entities are autoloaded
- Check that entity classes have proper `#[Entity]` attributes
- Verify namespace spelling

**"Entity class not found"**
- Check class name spelling
- Ensure the class is autoloaded
- Try using the fully qualified class name

**"Database connection failed"**
- Verify database configuration
- Check that database exists and is accessible
- Ensure required PDO extensions are installed

### Debug Mode

Use `--debug` for detailed error information:

```bash
php bin/aurum-cli.php schema generate --entities="User" --debug
```

### Verbose Output

The CLI provides helpful feedback about what it's doing:

```bash
🔧 Found 3 entities: User, Post, Category
📊 Format: schema-builder

✅ Schema generation completed!
```

## Help System

### Command Help

Get help for specific commands:

```bash
# General help
php bin/aurum-cli.php help

# Schema command help
php bin/aurum-cli.php schema generate --help

# Migration diff help  
php bin/aurum-cli.php migration diff --help
```

### Examples in Help

Each command includes practical examples in its help output, making it easy to get started quickly.

The Aurum CLI is designed to be intuitive and powerful, supporting both simple use cases and complex development workflows.
