# Aurum - Doctrine ORM-Inspired Database Abstraction Layer

A modern PHP 8.4+ database abstraction layer inspired by Doctrine ORM, featuring:

- **Advanced Type System** with automatic type inference from PHP property types
- **Multiple Decimal Implementations** (BigDecimal, ext-decimal, string-based)
- **Specialized Date/Time Types** (date, time, datetime, timezone-aware datetime)
- **Native UUID support** with time-based UUID generation
- **LazyGhost proxy objects** for lazy loading
- **Multiple UnitOfWork support** with savepoint-based transactions
- **SQL-based query builder** with DQL-like join capabilities
- **Attribute-based entity mapping**
- **SQLite and MariaDB compatibility**
- **SOLID principles** and dependency injection ready

## Installation

```bash
composer require fduarte42/aurum
```

## Requirements

- PHP 8.4+
- ext-pdo
- ext-pdo_sqlite
- ext-pdo_mysql
- ext-decimal (optional, for Decimal type support)
- brick/math (for BigDecimal support)

## Quick Start

### 1. Define Your Entities

```php
<?php

use Fduarte42\Aurum\Attribute\{Entity, Id, Column, ManyToOne, OneToMany};
use Ramsey\Uuid\UuidInterface;
use Decimal\Decimal;

#[Entity(table: 'users')]
class User
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?UuidInterface $id = null;

    #[Column(type: 'string', length: 255, unique: true)]
    private string $email;

    #[Column(type: 'string', length: 255)]
    private string $name;

    #[OneToMany(targetEntity: Todo::class, mappedBy: 'user')]
    private array $todos = [];

    public function __construct(string $email, string $name)
    {
        $this->email = $email;
        $this->name = $name;
    }

    // Getters and setters...
}

#[Entity(table: 'todos')]
class Todo
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?UuidInterface $id = null;

    #[Column(type: 'string', length: 255)]
    private string $title;

    #[Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?Decimal $priority = null;

    #[Column(type: 'boolean')]
    private bool $completed = false;

    #[ManyToOne(targetEntity: User::class, inversedBy: 'todos')]
    private ?User $user = null;

    public function __construct(string $title)
    {
        $this->title = $title;
    }

    // Getters and setters...
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
        'path' => 'app.db'
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
    private ?UuidInterface $id = null;

    #[Column] // Type inferred as 'string' with length 255
    private string $name;

    #[Column] // Type inferred as 'decimal' from BigDecimal
    private BigDecimal $price;

    #[Column] // Type inferred as 'integer'
    private int $stock;

    #[Column] // Type inferred as 'boolean'
    private bool $active = true;

    #[Column] // Type inferred as 'datetime'
    private \DateTimeImmutable $createdAt;
}
```

### Multiple Decimal Implementations

Choose the decimal implementation that best fits your needs:

```php
<?php

#[Entity(table: 'financial_data')]
class FinancialData
{
    #[Column(type: 'decimal', precision: 15, scale: 4)]
    private BigDecimal $amount; // Using brick/math

    #[Column(type: 'decimal_ext', precision: 10, scale: 2)]
    private Decimal $tax; // Using ext-decimal

    #[Column(type: 'decimal_string', precision: 8, scale: 3)]
    private string $commission; // String-based for maximum precision
}
```

### Specialized Date/Time Types

Different date/time types for different use cases:

```php
<?php

#[Entity(table: 'events')]
class Event
{
    #[Column(type: 'date')] // Date only (Y-m-d)
    private \DateTimeImmutable $eventDate;

    #[Column(type: 'time')] // Time only (H:i:s)
    private \DateTimeImmutable $startTime;

    #[Column(type: 'datetime')] // Standard datetime
    private \DateTimeImmutable $createdAt;

    #[Column(type: 'datetime_tz')] // Timezone-aware (stored as JSON)
    private \DateTimeImmutable $scheduledAt;
}
```

### Supported Types

| Type | PHP Type | Database Storage | Description |
|------|----------|------------------|-------------|
| `string` | `string` | VARCHAR/TEXT | Variable length strings |
| `text` | `string` | TEXT | Large text content |
| `integer` | `int` | INTEGER | Whole numbers |
| `float` | `float` | REAL/DOUBLE | Floating point numbers |
| `boolean` | `bool` | INTEGER/TINYINT | Boolean values |
| `json` | `array` | JSON/TEXT | JSON data |
| `uuid` | `UuidInterface` | CHAR(36)/TEXT | UUID values |
| `decimal` | `BigDecimal` | DECIMAL/TEXT | High precision decimals (brick/math) |
| `decimal_ext` | `Decimal` | DECIMAL/TEXT | High precision decimals (ext-decimal) |
| `decimal_string` | `string` | DECIMAL/TEXT | String-based decimals |
| `date` | `DateTimeInterface` | DATE/TEXT | Date only |
| `time` | `DateTimeInterface` | TIME/TEXT | Time only |
| `datetime` | `DateTimeInterface` | DATETIME/TEXT | Date and time |
| `datetime_tz` | `DateTimeInterface` | JSON/TEXT | Timezone-aware datetime |

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

$results = $qb->getResult();
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

        return $this->hydrateResults($qb->getResult());
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

## Supported Data Types

- `string` - VARCHAR/TEXT
- `integer` - INTEGER
- `float` - REAL/FLOAT
- `boolean` - INTEGER (0/1)
- `decimal` - TEXT (stored as string, converted to Decimal objects)
- `uuid` - TEXT (stored as string, converted to UUID objects)
- `datetime` - TEXT (ISO 8601 format)
- `date` - TEXT (ISO 8601 format)
- `time` - TEXT (ISO 8601 format)
- `json` - TEXT (JSON encoded/decoded)

## Testing

Run the test suite:

```bash
composer test
```

Run with coverage:

```bash
composer test -- --coverage-html coverage
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

Aurum includes a powerful migration system inspired by Doctrine Migrations, allowing you to version control your database schema changes.

### Quick Start with Migrations

```php
<?php

use Fduarte42\Aurum\DependencyInjection\ContainerBuilder;
use Fduarte42\Aurum\Migration\MigrationService;

// Setup with migrations support
$config = [
    'connection' => [
        'driver' => 'sqlite',
        'path' => 'app.db'
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

## License

MIT License. See LICENSE file for details.
