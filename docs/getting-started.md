# Getting Started with Aurum ORM

Welcome to Aurum ORM! This guide will help you get up and running with Aurum, a modern PHP ORM inspired by Doctrine but designed with modern PHP features and simplicity in mind.

## Prerequisites

- PHP 8.4 or higher
- Composer
- PDO extension
- PDO SQLite extension (for development)
- PDO MySQL extension (for production with MySQL/MariaDB)

## Installation

Install Aurum via Composer:

```bash
composer require fduarte42/aurum
```

## Quick Start

### 1. Basic Configuration

Create a simple configuration for your database connection:

```php
<?php
// config.php
return [
    'connection' => [
        'driver' => 'sqlite',
        'path' => ':memory:'
    ]
];
```

For MySQL/MariaDB:

```php
<?php
// config.php
return [
    'connection' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'your_database',
        'username' => 'your_username',
        'password' => 'your_password',
        'port' => 3306
    ]
];
```

### 2. Define Your First Entity

Create a simple entity using PHP 8 attributes:

```php
<?php
// src/Entity/User.php

use Fduarte42\Aurum\Attribute\{Entity, Id, Column};

#[Entity(table: 'users')]
class User
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?string $id = null;

    #[Column(type: 'string', length: 255, unique: true)]
    private string $email;

    #[Column(type: 'string', length: 255)]
    private string $name;

    #[Column(type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct(string $email, string $name)
    {
        $this->email = $email;
        $this->name = $name;
        $this->createdAt = new \DateTime();
    }

    // Getters and setters
    public function getId(): ?string { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function getName(): string { return $this->name; }
    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    
    public function setEmail(string $email): void { $this->email = $email; }
    public function setName(string $name): void { $this->name = $name; }
}
```

### 3. Create the Database Schema

Use the Aurum CLI to generate your database schema:

```bash
# Generate schema for your entities
php bin/aurum-cli.php schema generate --entities="User" --format=sql --output=schema.sql

# Or generate SchemaBuilder code for migrations
php bin/aurum-cli.php schema generate --entities="User" --format=schema-builder
```

### 4. Initialize the Entity Manager

```php
<?php
// bootstrap.php

require_once 'vendor/autoload.php';

use Fduarte42\Aurum\DependencyInjection\ContainerBuilder;

$config = require 'config.php';
$entityManager = ContainerBuilder::createEntityManager($config);
```

### 5. Basic CRUD Operations

```php
<?php
// example.php

require_once 'bootstrap.php';

// Create a new user
$user = new User('john@example.com', 'John Doe');

// Persist and flush to database
$entityManager->persist($user);
$entityManager->flush();

echo "Created user with ID: " . $user->getId() . "\n";

// Find a user by ID
$foundUser = $entityManager->find(User::class, $user->getId());
echo "Found user: " . $foundUser->getName() . "\n";

// Get repository for more complex queries
$userRepository = $entityManager->getRepository(User::class);

// Find by email
$userByEmail = $userRepository->findOneBy(['email' => 'john@example.com']);

// Find all users
$allUsers = $userRepository->findAll();

// Update a user
$foundUser->setName('John Smith');
$entityManager->flush(); // No need to persist again for updates

// Delete a user
$entityManager->remove($foundUser);
$entityManager->flush();
```

### 6. Working with Relationships

Define relationships between entities using attributes:

```php
<?php
// Many-to-Many relationship example

#[Entity(table: 'users')]
class User
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?string $id = null;

    #[Column(type: 'string', length: 255)]
    private string $name;

    #[ManyToMany(targetEntity: Role::class)]
    #[JoinTable(name: 'user_roles')]
    private array $roles = [];

    public function addRole(Role $role): void
    {
        if (!in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }
    }
}

#[Entity(table: 'roles')]
class Role
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?string $id = null;

    #[Column(type: 'string', length: 100)]
    private string $name;

    #[ManyToMany(targetEntity: User::class, mappedBy: 'roles')]
    private array $users = [];
}

// Usage
$user = new User('john@example.com', 'John Doe');
$adminRole = new Role('admin');

$user->addRole($adminRole);

$entityManager->persist($user);
$entityManager->persist($adminRole);
$entityManager->flush();
```

### 7. Querying Many-to-Many Relationships

Use the QueryBuilder to easily query across Many-to-Many relationships:

```php
// Find all users with admin role
$adminUsers = $entityManager->createQueryBuilder('u')
    ->select('u', 'r')
    ->from(User::class, 'u')
    ->innerJoin('u.roles', 'r')  // Automatic junction table join!
    ->where('r.name = :role')
    ->setParameter('role', 'admin')
    ->getResult();

// Find all roles for active users
$activeRoles = $entityManager->createQueryBuilder('r')
    ->select('r')
    ->from(Role::class, 'r')
    ->innerJoin('r.users', 'u')  // Inverse side join
    ->where('u.active = :active')
    ->setParameter('active', true)
    ->getResult();

// Complex query: Users with multiple roles
$powerUsers = $entityManager->createQueryBuilder('u')
    ->select('u')
    ->from(User::class, 'u')
    ->innerJoin('u.roles', 'r')
    ->where('r.name IN (:roles)')
    ->setParameter('roles', ['admin', 'moderator'])
    ->getResult();
```

**Key Benefits:**
- **Automatic Junction Table Handling**: No need to manually specify junction table joins
- **Bidirectional Support**: Query from either side of the relationship
- **Clean Syntax**: Simple `join('u.roles', 'r')` syntax
- **Performance Optimized**: Generates efficient SQL with proper indexes

## Next Steps

Now that you have the basics working, explore these topics:

1. **[Entity Relationships](entities.md#relationships)** - Learn how to define relationships between entities
2. **[Query Builder](architecture.md#query-builder)** - Build complex database queries
3. **[Migrations](migrations.md)** - Manage database schema changes
4. **[CLI Tools](cli-tools.md)** - Use the powerful command-line tools
5. **[Testing](testing.md)** - Write tests for your application

## Common Patterns

### Repository Pattern

Create custom repositories for complex queries:

```php
<?php
// src/Repository/UserRepository.php

use Fduarte42\Aurum\Repository\Repository;

class UserRepository extends Repository
{
    public function findActiveUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.active = :active')
            ->setParameter('active', true)
            ->getResult();
    }

    public function findByEmailDomain(string $domain): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.email LIKE :domain')
            ->setParameter('domain', '%@' . $domain)
            ->getResult();
    }
}
```

### Service Layer

Organize your business logic in services:

```php
<?php
// src/Service/UserService.php

class UserService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function createUser(string $email, string $name): User
    {
        $user = new User($email, $name);
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        return $user;
    }

    public function getUserStats(): array
    {
        $repository = $this->entityManager->getRepository(User::class);
        
        return [
            'total' => $repository->count([]),
            'active' => $repository->count(['active' => true]),
            'recent' => $repository->findBy([], ['createdAt' => 'DESC'], 10)
        ];
    }
}
```

## Configuration Options

### Full Configuration Example

```php
<?php
return [
    'connection' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'myapp',
        'username' => 'user',
        'password' => 'password',
        'port' => 3306,
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    ],
    'metadata' => [
        'cache' => true,
        'cache_dir' => 'var/cache/metadata'
    ],
    'migrations' => [
        'directory' => 'migrations',
        'namespace' => 'App\\Migrations',
        'table_name' => 'doctrine_migration_versions'
    ]
];
```

## Troubleshooting

### Common Issues

**Database Connection Errors**
- Verify your database credentials
- Ensure the database exists
- Check that required PDO extensions are installed

**Entity Not Found**
- Make sure your entity classes are autoloaded
- Verify the namespace and class name
- Check that attributes are properly defined

**Schema Issues**
- Run schema generation to see expected structure
- Use migration diff to identify schema changes needed

### Getting Help

- Check the [Architecture Overview](architecture.md) for deeper understanding
- Review [examples/](../examples/) for working code samples
- See [Testing Guide](testing.md) for testing patterns

## What's Next?

Continue with the [Architecture Overview](architecture.md) to understand how Aurum works under the hood, or jump to [CLI Tools](cli-tools.md) to learn about the powerful command-line utilities.
