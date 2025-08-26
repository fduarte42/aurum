# Architecture Overview

Aurum ORM is designed with modern PHP principles, clean architecture, and developer experience in mind. This document explains the core components and how they work together.

## Core Components

### Entity Manager

The `EntityManager` is the central component that coordinates all ORM operations. It manages the lifecycle of entities and provides the main API for persistence operations.

```php
interface EntityManagerInterface
{
    public function persist(object $entity): void;
    public function remove(object $entity): void;
    public function flush(): void;
    public function find(string $className, mixed $id): ?object;
    public function getRepository(string $className): Repository;
    public function createQueryBuilder(string $alias): QueryBuilder;
}
```

**Key Responsibilities:**
- Entity lifecycle management
- Transaction coordination
- Repository factory
- Query builder creation

### Unit of Work

The Unit of Work pattern tracks changes to entities and coordinates database writes. It ensures data consistency and optimizes database operations.

```php
class UnitOfWork
{
    private array $identityMap = [];
    private array $scheduledInsertions = [];
    private array $scheduledUpdates = [];
    private array $scheduledDeletions = [];
}
```

**Features:**
- Identity map for entity uniqueness
- Change tracking for automatic updates
- Batch operations for performance
- Transaction management

### Metadata System

Aurum uses PHP 8 attributes to define entity metadata, which is processed by the `MetadataFactory`.

```php
#[Entity(table: 'users')]
class User
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?string $id = null;

    #[Column(type: 'string', length: 255, unique: true)]
    private string $email;

    #[ManyToOne(targetEntity: Role::class)]
    #[JoinColumn(name: 'role_id')]
    private ?Role $role = null;
}
```

**Supported Attributes:**
- `#[Entity]` - Mark a class as an entity
- `#[Id]` - Define primary key fields
- `#[Column]` - Configure column mapping
- `#[ManyToOne]`, `#[OneToMany]`, `#[ManyToMany]` - Define relationships
- `#[JoinColumn]`, `#[JoinTable]` - Configure relationship mapping

### Connection Layer

The connection layer provides database abstraction with support for multiple platforms.

```php
interface ConnectionInterface
{
    public function execute(string $sql, array $params = []): int;
    public function fetchAll(string $sql, array $params = []): array;
    public function fetchOne(string $sql, array $params = []): ?array;
    public function beginTransaction(): void;
    public function commit(): void;
    public function rollback(): void;
}
```

**Supported Databases:**
- SQLite (development and testing)
- MySQL/MariaDB (production)
- Extensible for other databases

### Repository Pattern

Repositories provide a clean interface for entity queries and can be extended for custom logic.

```php
class Repository
{
    public function find(mixed $id): ?object;
    public function findAll(): array;
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;
    public function findOneBy(array $criteria): ?object;
    public function count(array $criteria = []): int;
}
```

**Custom Repositories:**
```php
class UserRepository extends Repository
{
    public function findActiveUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.active = :active')
            ->setParameter('active', true)
            ->getResult();
    }
}
```

### Query Builder

The Query Builder provides a fluent interface for building complex SQL queries with automatic join resolution for all relationship types.

#### Basic Usage

```php
$users = $entityManager->createQueryBuilder('u')
    ->select('u.name, u.email')
    ->from(User::class, 'u')
    ->leftJoin('u.role', 'r')
    ->where('u.active = :active')
    ->andWhere('r.name = :role')
    ->setParameter('active', true)
    ->setParameter('role', 'admin')
    ->orderBy('u.name', 'ASC')
    ->getResult();
```

#### Automatic Join Resolution

The QueryBuilder automatically resolves join conditions for all relationship types:

**ManyToOne/OneToMany Relationships:**
```php
// Automatic join condition: u.role_id = r.id
$qb->from(User::class, 'u')->join('u.role', 'r');

// Automatic join condition: u.id = p.user_id
$qb->from(User::class, 'u')->join('u.posts', 'p');
```

**Many-to-Many Relationships:**
```php
// Automatic junction table joins:
// INNER JOIN user_roles ur ON u.id = ur.user_id
// INNER JOIN roles r ON ur.role_id = r.id
$qb->from(User::class, 'u')->join('u.roles', 'r');

// Inverse side - automatic junction table joins:
// INNER JOIN user_roles ur ON r.id = ur.role_id
// INNER JOIN users u ON ur.user_id = u.id
$qb->from(Role::class, 'r')->join('r.users', 'u');
```

#### Many-to-Many Query Examples

**Find Users with Specific Roles:**
```php
$adminUsers = $entityManager->createQueryBuilder('u')
    ->select('u', 'r')
    ->from(User::class, 'u')
    ->innerJoin('u.roles', 'r')
    ->where('r.name IN (:roles)')
    ->setParameter('roles', ['admin', 'moderator'])
    ->getResult();
```

**Find Roles with Active Users:**
```php
$activeRoles = $entityManager->createQueryBuilder('r')
    ->select('r', 'u')
    ->from(Role::class, 'r')
    ->innerJoin('r.users', 'u')
    ->where('u.active = :active')
    ->setParameter('active', true)
    ->getResult();
```

**Complex Many-to-Many with Multiple Joins:**
```php
$result = $entityManager->createQueryBuilder('u')
    ->select('u', 'r', 'p')
    ->from(User::class, 'u')
    ->innerJoin('u.roles', 'r')
    ->leftJoin('u.posts', 'p')
    ->where('r.name = :role')
    ->andWhere('p.published = :published')
    ->setParameter('role', 'author')
    ->setParameter('published', true)
    ->orderBy('u.name', 'ASC')
    ->getResult();
```

#### Performance Considerations for Many-to-Many Queries

**Junction Table Joins:**
- Many-to-Many queries generate two JOIN operations (source→junction, junction→target)
- Use `INNER JOIN` for required relationships, `LEFT JOIN` for optional ones
- Consider adding indexes on junction table foreign key columns for better performance

**Best Practices:**
```php
// Good: Specific column selection reduces data transfer
$qb->select('u.name', 'r.name')
   ->from(User::class, 'u')
   ->innerJoin('u.roles', 'r');

// Good: Filter early to reduce junction table scan
$qb->from(User::class, 'u')
   ->innerJoin('u.roles', 'r')
   ->where('u.active = :active')  // Filter on main table first
   ->andWhere('r.name = :role');  // Then filter on joined table

// Consider: Use EXISTS for existence checks instead of joins
$qb->from(User::class, 'u')
   ->where('EXISTS (
       SELECT 1 FROM user_roles ur
       INNER JOIN roles r ON ur.role_id = r.id
       WHERE ur.user_id = u.id AND r.name = :role
   )');
```

**Features:**
- Fluent interface for query construction
- **Automatic join condition resolution for all relationship types**
- **Many-to-Many junction table handling**
- **Bidirectional relationship support**
- Parameter binding for security
- Support for complex WHERE clauses
- Ordering and pagination
- Subquery support
- **Custom JoinTable configuration support**

### Type System

Aurum includes a comprehensive type system for handling data conversion between PHP and database formats.

**Built-in Types:**
- `string`, `integer`, `boolean`, `float`
- `datetime`, `date`, `time`
- `uuid` (with Ramsey UUID support)
- `decimal` (with Brick Math support)
- `json` (automatic serialization)
- `text` (for large text fields)

**Custom Types:**
```php
class MoneyType implements TypeInterface
{
    public function convertToPHPValue(mixed $value): ?Money
    {
        return $value ? Money::fromString($value) : null;
    }

    public function convertToDatabaseValue(mixed $value): ?string
    {
        return $value?->toString();
    }
}
```

## Design Patterns

### Dependency Injection

Aurum uses a simple dependency injection container for service management.

```php
$container = ContainerBuilder::createORM($config);
$entityManager = $container->get(EntityManagerInterface::class);
$migrationService = $container->get(MigrationService::class);
```

### Proxy Pattern

Lazy loading is implemented using proxy objects that load data on first access.

```php
// This doesn't hit the database until you access a property
$user = $entityManager->getReference(User::class, $userId);

// Now the database is queried
echo $user->getName();
```

### Observer Pattern

The Unit of Work uses the observer pattern to track entity changes automatically.

```php
$user = $entityManager->find(User::class, $id);
$user->setName('New Name'); // Change is tracked automatically
$entityManager->flush(); // UPDATE query is generated
```

## Performance Considerations

### Identity Map

The identity map ensures that each entity is loaded only once per request, improving performance and preventing inconsistencies.

```php
$user1 = $entityManager->find(User::class, 1);
$user2 = $entityManager->find(User::class, 1);
// $user1 === $user2 (same object instance)
```

### Batch Operations

The Unit of Work batches database operations for better performance.

```php
for ($i = 0; $i < 1000; $i++) {
    $user = new User("user{$i}@example.com", "User {$i}");
    $entityManager->persist($user);
}
// Single flush executes all INSERTs in batches
$entityManager->flush();
```

### Lazy Loading

Related entities are loaded on-demand to avoid unnecessary queries.

```php
$user = $entityManager->find(User::class, 1);
// Posts are not loaded yet
$posts = $user->getPosts(); // Now posts are loaded
```

## Extension Points

### Custom Repositories

Extend the base repository for domain-specific queries:

```php
class UserRepository extends Repository
{
    public function findByRole(string $roleName): array
    {
        return $this->createQueryBuilder('u')
            ->join('u.role', 'r')
            ->where('r.name = :role')
            ->setParameter('role', $roleName)
            ->getResult();
    }
}
```

### Event System

While not yet implemented, Aurum is designed to support lifecycle events:

```php
// Future feature
#[PrePersist]
public function setCreatedAt(): void
{
    $this->createdAt = new \DateTime();
}
```

### Custom Types

Register custom types for specialized data handling:

```php
// Future feature
TypeRegistry::register('money', MoneyType::class);
```

## Testing Architecture

Aurum is designed with testing in mind:

- All tests use SQLite in-memory databases for speed
- Comprehensive unit test coverage
- Integration tests for real-world scenarios
- CLI tools are fully testable

## Migration System

The migration system provides version control for your database schema:

```php
class Version20231201120000 extends AbstractMigration
{
    public function up(ConnectionInterface $connection): void
    {
        $connection->execute('CREATE TABLE users (...)');
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->execute('DROP TABLE users');
    }
}
```

## CLI Integration

The unified CLI provides powerful tools for development:

```bash
# Generate schema
php bin/aurum-cli.php schema generate --entities="User,Post"

# Create migrations
php bin/aurum-cli.php migration diff --namespace="App\Entity"
```

## Framework Integration

Aurum is designed to integrate easily with any PHP framework:

### Laravel Integration

```php
// In a Laravel service provider
$this->app->singleton(EntityManagerInterface::class, function ($app) {
    return ContainerBuilder::createEntityManager($app['config']['aurum']);
});
```

### Symfony Integration

```php
// In services.yaml
services:
    Fduarte42\Aurum\EntityManagerInterface:
        factory: ['Fduarte42\Aurum\DependencyInjection\ContainerBuilder', 'createEntityManager']
        arguments: ['%aurum_config%']
```

## Security Considerations

- All queries use parameter binding to prevent SQL injection
- Transactions ensure data consistency
- Connection pooling and proper resource cleanup
- No dynamic SQL generation from user input

This architecture provides a solid foundation for building robust, maintainable applications while keeping the API simple and intuitive.
