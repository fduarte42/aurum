# Aurum - Doctrine ORM-Inspired Database Abstraction Layer

A modern PHP 8.4+ database abstraction layer inspired by Doctrine ORM, featuring:

- **Advanced Type System** with automatic type inference from PHP property types
- **Many-to-Many Relationships** with comprehensive support and automatic QueryBuilder joins
- **Multiple Decimal Implementations** (BigDecimal, ext-decimal, string-based)
- **Specialized Date/Time Types** (date, time, datetime, timezone-aware datetime)
- **Native UUID support** with time-based UUID generation
- **LazyGhost proxy objects** for lazy loading
- **Multiple UnitOfWork support** with savepoint-based transactions
- **SQL-based query builder** with DQL-like join capabilities and auto-discovery
- **Unified CLI Tools** for schema generation and migration management
- **Database Migrations** with schema builder and dependency resolution
- **Attribute-based entity mapping**
- **SQLite and MariaDB compatibility**
- **Comprehensive Test Suite** (801 tests, 0 failures/warnings/deprecations)
- **Docker Development Support** for consistent testing environments
- **SOLID principles** and dependency injection ready

## Installation

```bash
composer require fduarte42/aurum
```

## Requirements

- **PHP 8.4+** with strict types support
- **ext-pdo** - PDO database abstraction layer
- **ext-pdo_sqlite** - SQLite database support
- **ext-pdo_mysql** - MySQL/MariaDB database support
- **ext-decimal** (optional) - Native decimal type support
- **brick/math** - BigDecimal support (automatically installed)
- **ramsey/uuid** - UUID generation and handling (automatically installed)

### Development Requirements

- **Docker** (recommended) - For consistent testing environment using `fduarte42/docker-php:8.4`
- **PHPUnit 11+** - For running tests
- **PHPStan** - For static analysis
- **PHP CodeSniffer** - For code style checking

## Test Suite Status

Aurum maintains a comprehensive and reliable test suite:

- **✅ 801 Tests** - Comprehensive coverage of all features
- **✅ 2,064 Assertions** - Thorough validation of functionality
- **✅ 0 Failures** - All tests pass consistently
- **✅ 0 Warnings** - Clean test output
- **✅ 0 Deprecations** - Future-proof codebase
- **✅ Docker Support** - Consistent testing across environments

The test suite includes unit tests, integration tests, and comprehensive coverage of:
- Entity relationships (including Many-to-Many)
- Migration system and schema builder
- CLI tools and commands
- Type system and conversions
- Query builder and auto-joins
- Database drivers and connections

## Quick Start

### 1. Define Your Entities

```php
<?php

use Fduarte42\Aurum\Attribute\{Entity, Id, Column, ManyToOne, OneToMany, ManyToMany, JoinTable, JoinColumn};
use Ramsey\Uuid\UuidInterface;
use Decimal\Decimal;

#[Entity(table: 'users')]
class User
{
    #[Id]
    #[Column(type: 'uuid')]
    public private(set) ?UuidInterface $id = null;

    #[OneToMany(targetEntity: Todo::class, mappedBy: 'user')]
    public array $todos = [];

    public function __construct(
        #[Column(type: 'string', length: 255, unique: true)]
        public string $email,

        #[Column(type: 'string', length: 255)]
        public string $name
    ) {
    }
}

#[Entity(table: 'todos')]
class Todo
{
    #[Id]
    #[Column(type: 'uuid')]
    public private(set) ?UuidInterface $id = null;

    #[ManyToOne(targetEntity: User::class, inversedBy: 'todos')]
    public ?User $user = null;

    public function __construct(
        #[Column(type: 'string', length: 255)]
        public string $title,

        #[Column(type: 'decimal_ext', precision: 10, scale: 2, nullable: true)]
        public ?Decimal $priority = null,

        #[Column(type: 'boolean')]
        public bool $completed = false
    ) {
    }
}

#[Entity(table: 'roles')]
class Role
{
    #[Id]
    #[Column(type: 'uuid')]
    public private(set) ?UuidInterface $id = null;

    #[ManyToMany(targetEntity: User::class, mappedBy: 'roles')]
    public array $users = [];

    public function __construct(
        #[Column(type: 'string', length: 100)]
        public string $name
    ) {
    }
}
```

### Many-to-Many Relationships

Add Many-to-Many relationships to your entities:

```php
<?php

// Update the User entity to include roles
#[Entity(table: 'users')]
class User
{
    // ... existing properties ...

    #[ManyToMany(targetEntity: Role::class, inversedBy: 'users')]
    #[JoinTable(
        name: 'user_roles',
        joinColumns: [new JoinColumn(name: 'user_id', referencedColumnName: 'id')],
        inverseJoinColumns: [new JoinColumn(name: 'role_id', referencedColumnName: 'id')]
    )]
    public array $roles = [];

    public function addRole(Role $role): void
    {
        if (!in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }
    }

    public function removeRole(Role $role): void
    {
        $key = array_search($role, $this->roles, true);
        if ($key !== false) {
            unset($this->roles[$key]);
        }
    }
}
```

### 2. Setup the EntityManager

```php
<?php

use Fduarte42\Aurum\DependencyInjection\ContainerBuilder;

// Using the container builder
$config = [
    'connection' => [
        'driver' => 'sqlite',
        'path' => ':memory:'
    ]
];

$entityManager = ContainerBuilder::createEntityManager($config);

// Or for MariaDB
$config = [
    'connection' => [
        'driver' => 'mariadb',
        'host' => 'localhost',
        'database' => 'myapp',
        'username' => 'user',
        'password' => 'password'
    ]
];
```

### 3. Basic CRUD Operations

```php
<?php

// Create entities
$user = new User('john@example.com', 'John Doe');
$todo = new Todo('Buy groceries');
$todo->setPriority(new Decimal('5.50', 2));
$user->addTodo($todo);

// Persist and flush
$entityManager->beginTransaction();
$entityManager->persist($user);
$entityManager->persist($todo);
$entityManager->flush();
$entityManager->commit();

// Find entities
$foundUser = $entityManager->find(User::class, $user->getId());
$userRepo = $entityManager->getRepository(User::class);
$allUsers = $userRepo->findAll();
$activeUsers = $userRepo->findBy(['active' => true]);
```

## CLI Tools

Aurum provides a unified command-line interface for schema generation and migration management through `bin/aurum-cli.php`.

### Schema Generation

Generate database schema from your entities:

```bash
# Generate schema for specific entities
php bin/aurum-cli.php schema generate --entities="User,Post" --format=sql

# Generate schema-builder format (default)
php bin/aurum-cli.php schema generate --entities="User,Post" --format=schema-builder

# Auto-discover all entities in a namespace
php bin/aurum-cli.php schema generate --namespace="App\Entity"

# Auto-discover all entities (scans entire codebase)
php bin/aurum-cli.php schema generate
```

### Migration Management

Create and manage database migrations:

```bash
# Generate migration from entity changes
php bin/aurum-cli.php migration diff --entities="User,Post" --name="UpdateUserSchema"

# Generate migration from namespace
php bin/aurum-cli.php migration diff --namespace="App\Entity" --name="InitialSchema"

# Auto-discover entities for migration
php bin/aurum-cli.php migration diff --name="AutoDiscoveredChanges"
```

### Configuration

Create an `aurum.config.php` file in your project root (see `examples/aurum.config.php` for a template):

```php
<?php

return [
    'connection' => [
        'driver' => 'sqlite',
        'path' => ':memory:'
    ],
    'migrations' => [
        'directory' => __DIR__ . '/migrations',
        'namespace' => 'App\\Migrations'
    ],
    'entities' => [
        'paths' => [
            __DIR__ . '/src/Entity',
            __DIR__ . '/app/Models'
        ]
    ]
];
```

## Advanced Type System

Aurum features a sophisticated type system with automatic type inference and multiple implementations for common data types.

### Automatic Type Inference

The ORM can automatically infer types from PHP property type hints, reducing the need for explicit type declarations:

```php
<?php

#[Entity(table: 'products')]
class Product
{
    #[Id]
    #[Column] // Type inferred as 'uuid' from UuidInterface
    public private(set) ?UuidInterface $id = null;

    public function __construct(
        #[Column] // Type inferred as 'string' with length 255
        public string $name,

        #[Column] // Type inferred as 'decimal' from BigDecimal
        public BigDecimal $price,

        #[Column] // Type inferred as 'integer'
        public int $stock,

        #[Column] // Type inferred as 'boolean'
        public bool $active = true,

        #[Column] // Type inferred as 'datetime'
        public \DateTimeImmutable $createdAt = new \DateTimeImmutable()
    ) {
    }
}
```

### Multiple Decimal Implementations

Choose the decimal implementation that best fits your needs:

```php
<?php

#[Entity(table: 'financial_data')]
class FinancialData
{
    public function __construct(
        #[Column(type: 'decimal', precision: 15, scale: 4)]
        public BigDecimal $amount, // Using brick/math

        #[Column(type: 'decimal_ext', precision: 10, scale: 2)]
        public Decimal $tax, // Using ext-decimal

        #[Column(type: 'decimal_string', precision: 8, scale: 3)]
        public string $commission // String-based for maximum precision
    ) {
    }
}
```

### Specialized Date/Time Types

Different date/time types for different use cases:

```php
<?php

#[Entity(table: 'events')]
class Event
{
    public function __construct(
        #[Column(type: 'date')] // Date only (Y-m-d)
        public \DateTimeImmutable $eventDate,

        #[Column(type: 'time')] // Time only (H:i:s)
        public \DateTimeImmutable $startTime,

        #[Column(type: 'datetime')] // Standard datetime
        public \DateTimeImmutable $createdAt,

        #[Column(type: 'datetime_tz')] // Timezone-aware (stored as JSON)
        public \DateTimeImmutable $scheduledAt
    ) {
    }
}
```

### Supported Types

| Type | PHP Type | Database Storage                                                    | Description |
|------|----------|---------------------------------------------------------------------|-------------|
| `string` | `string` | VARCHAR/TEXT                                                        | Variable length strings |
| `text` | `string` | TEXT                                                                | Large text content |
| `integer` | `int` | INTEGER                                                             | Whole numbers |
| `float` | `float` | REAL/DOUBLE                                                         | Floating point numbers |
| `boolean` | `bool` | INTEGER/TINYINT                                                     | Boolean values |
| `json` | `array` | JSON/TEXT                                                           | JSON data |
| `uuid` | `UuidInterface` | CHAR(36)/TEXT                                                       | UUID values |
| `decimal` | `BigDecimal` | DECIMAL/TEXT                                                        | High precision decimals (brick/math) |
| `decimal_ext` | `Decimal` | DECIMAL/TEXT                                                        | High precision decimals (ext-decimal) |
| `decimal_string` | `string` | DECIMAL/TEXT                                                        | String-based decimals |
| `date` | `DateTimeInterface` | DATE/TEXT                                                           | Date only |
| `time` | `DateTimeInterface` | TIME/TEXT                                                           | Time only |
| `datetime` | `DateTimeInterface` | DATETIME/TEXT                                                       | Date and time |
| `datetime_tz` | `DateTimeInterface` | utc:&nbsp;DATETIME<br>local:&nbsp;DATETIME<br>timezone:&nbsp;VARCHAR(50) | Timezone-aware datetime |

## Advanced Features

### Multiple UnitOfWork with Savepoints

```php
<?php

$entityManager->beginTransaction();

// First unit of work
$uow1 = $entityManager->createUnitOfWork();
$entityManager->setUnitOfWork($uow1);
$entityManager->persist($user1);
$entityManager->flush(); // Creates savepoint automatically

// Second unit of work
$uow2 = $entityManager->createUnitOfWork();
$entityManager->setUnitOfWork($uow2);
$entityManager->persist($user2);

try {
    $entityManager->flush();
} catch (Exception $e) {
    $uow2->rollbackToSavepoint(); // Rollback only this UoW
}

$entityManager->commit(); // Commits all successful UoWs
```

### Query Builder with Joins

```php
<?php

$todoRepo = $entityManager->getRepository(Todo::class);

$qb = $todoRepo->createQueryBuilder('t')
    ->innerJoin('users', 'u', 't.user_id = u.id')
    ->where('u.email = :email')
    ->andWhere('t.priority > :minPriority')
    ->orderBy('t.priority', 'DESC')
    ->setParameter('email', 'john@example.com')
    ->setParameter('minPriority', '5.00');

// Get raw array data (PDOStatement iterator)
$statement = $qb->getArrayResult();

// Or get hydrated entity objects (detached/unmanaged)
$todos = $qb->getResult();
```

### Lazy Loading with Proxies

```php
<?php

// Get a reference without loading from database
$userRef = $entityManager->getReference(User::class, $userId);

// The user is loaded only when accessed
$name = $userRef->getName(); // Triggers lazy loading
```

### Custom Repository

```php
<?php

class TodoRepository extends Repository
{
    public function findHighPriorityTodos(Decimal $minPriority): array
    {
        return $this->findBy(['priority' => ['>=', $minPriority]]);
    }

    public function findCompletedTodosForUser(User $user): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.user_id = :userId')
            ->andWhere('t.completed = :completed')
            ->setParameter('userId', $user->getId())
            ->setParameter('completed', true);

        return $this->hydrateResults($qb->getArrayResult());
    }
}
```

## Dependency Injection Integration

### With PSR-11 Container

```php
<?php

use Fduarte42\Aurum\DependencyInjection\ORMServiceProvider;

$container = new YourPSR11Container();
$serviceProvider = new ORMServiceProvider($config);
$serviceProvider->register($container);

$entityManager = $container->get(EntityManagerInterface::class);
```

### With Laravel

```php
<?php

// In a service provider
public function register()
{
    $this->app->singleton(EntityManagerInterface::class, function ($app) {
        return ContainerBuilder::createEntityManager($config);
    });
}
```

## Additional Resources

- **[Complete Documentation](docs/)** - Comprehensive guides and API reference
- **[Examples](examples/)** - Working code examples and tutorials
- **[CLI Tools Guide](docs/cli-tools.md)** - Detailed CLI documentation
- **[Migration System](docs/migrations.md)** - Advanced migration features
- **[Testing Guide](docs/testing.md)** - Testing best practices and setup

## Testing

### Local Testing

Run the test suite:

```bash
composer test
```

Run with coverage:

```bash
composer test -- --coverage-html coverage
```

### Docker Testing (Recommended)

For consistent testing across environments, use Docker:

```bash
# Run tests in Docker container
docker run --rm -v $(pwd):/var/www/html fduarte42/docker-php:8.4 vendor/bin/phpunit

# Run tests with coverage
docker run --rm -v $(pwd):/var/www/html fduarte42/docker-php:8.4 vendor/bin/phpunit --coverage-html coverage

# Run specific test suites
docker run --rm -v $(pwd):/var/www/html fduarte42/docker-php:8.4 vendor/bin/phpunit tests/Unit
docker run --rm -v $(pwd):/var/www/html fduarte42/docker-php:8.4 vendor/bin/phpunit tests/Integration

# Run with verbose output
docker run --rm -v $(pwd):/var/www/html fduarte42/docker-php:8.4 vendor/bin/phpunit --testdox
```

### Development with Docker

Set up a development environment:

```bash
# Start interactive PHP container
docker run -it --rm -v $(pwd):/var/www/html fduarte42/docker-php:8.4 bash

# Inside container, run commands
composer install
vendor/bin/phpunit
php bin/aurum-cli.php schema generate
```

## Architecture

The ORM follows SOLID principles with clear separation of concerns:

- **Connection Layer**: Database abstraction with transaction/savepoint support
- **Metadata Layer**: Attribute-based entity mapping
- **UnitOfWork**: Change tracking and transaction management
- **Proxy Layer**: Lazy loading with LazyGhost objects
- **Repository Layer**: Data access patterns
- **Query Builder**: SQL generation with join support

## Database Migrations

Aurum includes a powerful migration system inspired by Doctrine Migrations, allowing you to version control your database schema changes. The system integrates seamlessly with the CLI tools for easy management.

### Quick Start with CLI

The easiest way to work with migrations is through the CLI:

```bash
# Generate migration from entity changes
php bin/aurum-cli.php migration diff --entities="User,Post" --name="CreateUserAndPost"

# Generate migration from namespace
php bin/aurum-cli.php migration diff --namespace="App\Entity" --name="InitialSchema"

# Auto-discover all entities
php bin/aurum-cli.php migration diff --name="AutoDiscoveredChanges"
```

### Programmatic Usage

You can also work with migrations programmatically:

```php
<?php

use Fduarte42\Aurum\DependencyInjection\ContainerBuilder;
use Fduarte42\Aurum\Migration\MigrationService;

// Setup with migrations support
$config = [
    'connection' => [
        'driver' => 'sqlite',
        'path' => ':memory:'
    ],
    'migrations' => [
        'directory' => __DIR__ . '/migrations',
        'namespace' => 'App\\Migrations'
    ]
];

$container = ContainerBuilder::createORM($config);
$migrationService = $container->get(MigrationService::class);

// Generate a new migration
$version = $migrationService->generate('Create users table');

// Run all pending migrations
$migrationService->migrate();

// Check migration status
$status = $migrationService->status();
echo "Current version: {$status['current_version']}\n";
echo "Pending migrations: {$status['pending_migrations']}\n";
```

### Migration Features

- **Database-agnostic schema builder** with fluent API
- **Automatic dependency resolution** between migrations
- **Transactional execution** with rollback support
- **Dry-run mode** for testing migrations
- **SQLite and MariaDB support** with platform-specific optimizations
- **Migration tracking** in dedicated table
- **Verbose output** for debugging

### Creating Migrations

Generate a new migration file:

```php
$migrationService = $container->get(MigrationService::class);
$version = $migrationService->generate('Create posts table');
```

This creates a migration file like `Version20231201120000.php`:

```php
<?php

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
        return 'Create posts table';
    }

    public function up(ConnectionInterface $connection): void
    {
        // Create table using schema builder
        $this->schemaBuilder->createTable('posts')
            ->uuidPrimaryKey()
            ->string('title', ['length' => 255, 'not_null' => true])
            ->text('content')
            ->boolean('published', ['default' => false])
            ->timestamps()
            ->unique(['title'])
            ->index(['published'])
            ->create();
    }

    public function down(ConnectionInterface $connection): void
    {
        $this->schemaBuilder->dropTable('posts');
    }
}
```

### Schema Builder API

The schema builder provides a fluent API for database operations:

```php
// Create table with various column types
$this->schemaBuilder->createTable('users')
    ->id()                                    // Auto-increment primary key
    ->uuidPrimaryKey('uuid_id')              // UUID primary key
    ->string('email', ['length' => 255])     // VARCHAR column
    ->text('bio')                            // TEXT column
    ->boolean('active', ['default' => true]) // BOOLEAN with default
    ->decimal('price', ['precision' => 10, 'scale' => 2]) // DECIMAL
    ->datetime('created_at')                 // DATETIME column
    ->timestamps()                           // created_at + updated_at

    // Indexes and constraints
    ->unique(['email'])                      // Unique constraint
    ->index(['active', 'created_at'])        // Composite index
    ->foreign(['category_id'], 'categories', ['id']) // Foreign key

    ->create();

// Alter existing table
$this->schemaBuilder->alterTable('users')
    ->string('phone', ['nullable' => true])
    ->dropColumn('old_field')
    ->renameColumn('name', 'full_name')
    ->alter();
```

### Migration Dependencies

Specify dependencies between migrations:

```php
public function getDependencies(): array
{
    return ['20231201120000']; // Must run after this migration
}
```

### Advanced Migration Features

#### Conditional Logic

```php
public function up(ConnectionInterface $connection): void
{
    // Skip if condition is met
    $this->skipIf(
        $this->tableExists('users'),
        'Users table already exists'
    );

    // Abort if condition is not met
    $this->abortIf(
        !$this->tableExists('categories'),
        'Categories table must exist first'
    );

    // Warn about potential issues
    $this->warnIf(
        $this->getRowCount('users') > 10000,
        'Large table migration may take time'
    );
}
```

#### Raw SQL Execution

```php
public function up(ConnectionInterface $connection): void
{
    // Execute raw SQL when needed
    $this->addSql('CREATE INDEX CONCURRENTLY idx_users_email ON users(email)');

    // With parameters
    $this->addSql('UPDATE users SET status = ? WHERE created_at < ?', [
        'inactive',
        date('Y-m-d', strtotime('-1 year'))
    ]);
}
```

#### Non-transactional Migrations

```php
public function isTransactional(): bool
{
    // Disable transactions for operations that don't support them
    return false;
}
```

### Running Migrations

```php
// Run all pending migrations
$migrationService->migrate();

// Migrate to specific version
$migrationService->migrateToVersion('20231201120000');

// Rollback last migration
$migrationService->rollback();

// Rollback to specific version
$migrationService->rollbackToVersion('20231201120000');

// Dry run (test without executing)
$migrationService->setDryRun(true)->migrate();

// Verbose output
$migrationService->setVerbose(true)->migrate();
```

### Migration Status and Information

```php
// Get migration status
$status = $migrationService->status();
echo "Current version: {$status['current_version']}\n";
echo "Executed: {$status['executed_migrations']}\n";
echo "Pending: {$status['pending_migrations']}\n";

// List all migrations
$migrations = $migrationService->list();
foreach ($migrations as $migration) {
    $status = $migration['executed'] ? '✅' : '⏳';
    echo "{$status} {$migration['version']}: {$migration['description']}\n";
}
```

### Integration with EntityManager

Access migrations through the EntityManager:

```php
$entityManager = ContainerBuilder::createEntityManager($config);
$migrationManager = $entityManager->getMigrationManager();

// Use migration manager
$migrationManager->migrate();
```

### Configuration Options

```php
$config = [
    'migrations' => [
        'directory' => __DIR__ . '/migrations',    // Migration files directory
        'namespace' => 'App\\Migrations',          // PHP namespace
        'table_name' => 'doctrine_migrations',     // Tracking table name
        'template' => __DIR__ . '/migration.tpl'   // Custom template file
    ]
];
```

### Best Practices

1. **Always provide rollback logic** in the `down()` method
2. **Use descriptive migration names** that explain what they do
3. **Test migrations** in development before production
4. **Use dependencies** to ensure proper execution order
5. **Be careful with data migrations** - they're often irreversible
6. **Use conditional logic** to handle different environments
7. **Keep migrations small** and focused on single changes
8. **Use the schema builder** instead of raw SQL when possible

See `examples/migrations-usage.php` and `examples/sample-migrations/` for complete examples.

## Recent Improvements

### Test Suite Cleanup & Stability

**🧪 Test Suite Enhancements:**
- **✅ Achieved 100% clean test suite** - 801 tests, 0 failures/warnings/deprecations
- **🔧 Fixed deprecation warnings** in QueryBuilder nullable parameters (PHP 8.4 compatibility)
- **🛠️ Enhanced array handling** in database quote method with proper JSON conversion
- **🔍 Fixed undefined array key warnings** in Many-to-Many persistence operations
- **🐳 Added Docker environment detection** for reliable filesystem permission tests
- **📊 Comprehensive test coverage** across all features and edge cases

**🚀 Feature Additions:**
- **🔗 Many-to-Many relationship support** with automatic QueryBuilder joins and bidirectional functionality
- **⚡ Unified CLI tools** (`bin/aurum-cli.php`) for schema generation and migration management
- **🔍 Auto-discovery features** for entities and relationships across namespaces
- **📋 Schema-builder format** as default for migration diff commands
- **🐳 Docker development support** with `fduarte42/docker-php:8.4` image

**🛡️ Stability & Reliability:**
- **🔒 Environment-aware testing** with proper skipping of unreliable tests in containers
- **🎯 Improved error handling** for edge cases and type conversions
- **📈 Enhanced code quality** with comprehensive static analysis and code style checks
- **🔄 Backward compatibility** maintained throughout all improvements

### Documentation & Developer Experience

- **📚 Comprehensive documentation** with detailed examples and best practices
- **🎯 Clear API reference** for all features including Many-to-Many relationships
- **🔧 Improved CLI tool documentation** with practical examples
- **🐳 Docker setup guides** for consistent development environments
- **✅ Test suite status reporting** for transparency and confidence

## License

Aurum ORM is open-source software licensed under the [MIT License](LICENSE). You are free to use, modify, and distribute this software in accordance with the terms of the MIT License.
