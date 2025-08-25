# Aurum Examples

This directory contains comprehensive examples demonstrating how to use Aurum's features.

## Basic Usage

- **`basic-usage.php`** - Complete example showing entity definition, relationships, and basic ORM operations
- **`migrations-usage.php`** - Full migration system demonstration with schema creation and data management

## Migration Examples

### CLI Tool
- **`migration-cli.php`** - Command-line interface for managing migrations
  ```bash
  php migration-cli.php generate "Create users table"
  php migration-cli.php migrate
  php migration-cli.php status
  ```

### Sample Migrations
The `sample-migrations/` directory contains real-world migration examples:

- **`Version20231201120000.php`** - Creating users table with proper constraints and indexes
- **`Version20231201130000.php`** - Creating posts table with foreign key relationships
- **`Version20231201140000.php`** - Adding profile fields to existing table
- **`Version20231201150000.php`** - Data migration example with legacy data cleanup

## Running the Examples

1. **Install dependencies:**
   ```bash
   composer install
   ```

2. **Run basic ORM example:**
   ```bash
   php examples/basic-usage.php
   ```

3. **Try the migration system:**
   ```bash
   php examples/migrations-usage.php
   ```

4. **Use the CLI tool:**
   ```bash
   php examples/migration-cli.php help
   php examples/migration-cli.php generate "My first migration"
   php examples/migration-cli.php migrate
   ```

## Key Features Demonstrated

### ORM Features
- Entity definition with attributes
- Relationships (OneToMany, ManyToOne)
- UUID primary keys
- Lazy loading with proxies
- Unit of Work pattern
- Repository pattern
- Query builder with auto-joins

### Migration Features
- Schema builder with fluent API
- Database-agnostic DDL
- Migration dependencies
- Transactional execution
- Rollback support
- Dry-run mode
- Data migrations
- Conditional logic
- Platform-specific optimizations

### Best Practices
- Proper error handling
- Transaction management
- Performance considerations
- Code organization
- Testing strategies

## Database Support

All examples work with both SQLite and MariaDB. The examples use SQLite by default for simplicity, but you can easily switch to MariaDB by changing the connection configuration:

```php
$config = [
    'connection' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'aurum_demo',
        'username' => 'root',
        'password' => 'password'
    ]
];
```

## File Structure

```
examples/
├── README.md                    # This file
├── basic-usage.php             # Basic ORM usage
├── migrations-usage.php        # Migration system demo
├── migration-cli.php           # CLI tool for migrations
└── sample-migrations/          # Example migration files
    ├── Version20231201120000.php  # Users table creation
    ├── Version20231201130000.php  # Posts table with FK
    ├── Version20231201140000.php  # Schema alteration
    └── Version20231201150000.php  # Data migration
```

## Next Steps

After exploring these examples:

1. **Read the main README** for complete API documentation
2. **Check the tests** in `tests/` for more usage patterns
3. **Review the source code** to understand the internals
4. **Build your own application** using Aurum!

## Need Help?

- Check the main README.md for detailed documentation
- Look at the test files for more examples
- Review the source code for implementation details
- Create an issue on GitHub for questions or bugs
