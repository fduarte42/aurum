# Aurum ORM Documentation

Welcome to the comprehensive documentation for Aurum ORM - a modern PHP ORM designed with PHP 8.4+ features, clean architecture, and developer experience in mind.

## 📚 Documentation Overview

### Getting Started
- **[Getting Started Guide](getting-started.md)** - Installation, basic setup, and first steps
- **[Architecture Overview](architecture.md)** - Core components, design patterns, and how they work together

### Core Features
- **[Entity Management](entities.md)** - How to define entities, relationships, and use attributes
- **[QueryBuilder API](querybuilder-api.md)** - Complete API reference for the QueryBuilder with Many-to-Many support
- **[CLI Tools Guide](cli-tools.md)** - Complete guide to the unified `aurum-cli.php` tool
- **[Migration System](migrations.md)** - How to create, run, and manage database migrations
- **[Type System](type_system.md)** - Which types are available and how to use them

### Development
- **[Testing Guide](testing.md)** - How to run tests, write new tests, and testing best practices
- **[Contributing Guide](contributing.md)** - Guidelines for contributing to the project

## 🚀 Quick Start

### Installation

```bash
composer require fduarte42/aurum
```

### Basic Usage

```php
<?php
// Define an entity
#[Entity(table: 'users')]
class User
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?string $id = null;

    #[Column(type: 'string', length: 255, unique: true)]
    private string $email;

    public function __construct(string $email)
    {
        $this->email = $email;
    }
}

// Use the entity
$entityManager = ContainerBuilder::createEntityManager($config);

$user = new User('john@example.com');
$entityManager->persist($user);
$entityManager->flush();

// Query with automatic Many-to-Many joins
$adminUsers = $entityManager->createQueryBuilder('u')
    ->select('u', 'r')
    ->from(User::class, 'u')
    ->innerJoin('u.roles', 'r')  // Automatic junction table join!
    ->where('r.name = :role')
    ->setParameter('role', 'admin')
    ->getResult();
```

### CLI Tools

```bash
# Generate database schema
php bin/aurum-cli.php schema generate --entities="User,Post" --format=sql

# Create migrations
php bin/aurum-cli.php migration diff --namespace="App\Entity" --name="InitialSchema"

# Auto-discover all entities
php bin/aurum-cli.php schema generate
```

## 🏗️ Key Features

### Modern PHP
- **PHP 8.4+** with strict types and modern features
- **Attributes-based** configuration (no XML/YAML)
- **Type-safe** operations throughout

### Developer Experience
- **Unified CLI** for all schema and migration operations
- **Auto-discovery** of entities and relationships
- **Comprehensive testing** with SQLite in-memory databases
- **Clear error messages** and debugging support

### Architecture
- **Clean separation** of concerns
- **Dependency injection** container
- **Repository pattern** with custom repositories
- **Query builder** with fluent interface and **Many-to-Many automatic joins**
- **Unit of Work** pattern for change tracking

### Database Support
- **SQLite** (development and testing)
- **MySQL/MariaDB** (production)
- **Extensible** for other databases

## 📖 Documentation Sections

### 1. [Getting Started](getting-started.md)
Perfect for newcomers to Aurum. Covers:
- Installation and setup
- First entity definition
- Basic CRUD operations
- Configuration options
- Common patterns

### 2. [Architecture Overview](architecture.md)
Deep dive into how Aurum works:
- Core components (EntityManager, UnitOfWork, etc.)
- Design patterns used
- Performance considerations
- Extension points

### 3. [Entity Management](entities.md)
Complete guide to working with entities:
- Entity definition with attributes
- Column types and options
- Relationships (OneToMany, ManyToOne, **Many-to-Many**)
- **Many-to-Many QueryBuilder examples**
- Custom repositories
- Best practices

### 4. [QueryBuilder API Reference](querybuilder-api.md)
Complete API documentation for the QueryBuilder:
- **Many-to-Many automatic join resolution**
- Bidirectional relationship queries
- Custom JoinTable configuration support
- Performance optimization techniques
- Error handling and best practices

### 5. [CLI Tools Guide](cli-tools.md)
Master the powerful CLI tools:
- Schema generation in multiple formats
- **Many-to-Many junction table generation**
- Migration diff creation
- Namespace-based operations
- Auto-discovery features
- Integration with development workflow

### 6. [Migration System](migrations.md)
Database schema evolution:
- Creating and running migrations
- Schema builder integration
- Data migrations
- Best practices and troubleshooting

### 7. [Testing Guide](testing.md)
Comprehensive testing approach:
- Running the test suite
- Writing unit and integration tests
- Testing entities and repositories
- CLI command testing
- Performance testing

### 8. [Contributing Guide](contributing.md)
For contributors and maintainers:
- Development setup
- Code style guidelines
- Testing requirements
- Pull request process
- Community guidelines

## 🔧 CLI Tools Reference

### Schema Generation
```bash
# Generate for specific entities
php bin/aurum-cli.php schema generate --entities="User,Post" --format=schema-builder

# Generate for namespace
php bin/aurum-cli.php schema generate --namespace="App\Entity" --format=sql

# Auto-discover all entities
php bin/aurum-cli.php schema generate --format=both
```

### Migration Management
```bash
# Preview changes
php bin/aurum-cli.php migration diff --entities="User,Post" --preview

# Generate migration
php bin/aurum-cli.php migration diff --namespace="App\Entity" --name="UpdateSchema"

# Auto-discover and create migration
php bin/aurum-cli.php migration diff --name="UpdateAllEntities"
```

## 🧪 Testing

Aurum includes a comprehensive test suite:

```bash
# Run all tests
./vendor/bin/phpunit

# Run without coverage (faster)
./vendor/bin/phpunit --no-coverage

# Run specific test suites
./vendor/bin/phpunit tests/Unit/
./vendor/bin/phpunit tests/Integration/
```

**Test Results:**
- ✅ **644 tests, 1557 assertions**
- ✅ **All tests passing** (100% success rate)
- ✅ **Many-to-Many QueryBuilder tests** included
- ✅ SQLite in-memory databases for speed
- ✅ Comprehensive CLI testing

## 🤝 Contributing

We welcome contributions! Please see the [Contributing Guide](contributing.md) for:
- Development setup
- Code style guidelines
- Testing requirements
- Pull request process

## 📝 Examples

Check the `examples/` directory for working code samples:
- Basic entity usage
- Repository patterns
- Migration workflows
- CLI tool demonstrations

## 🆘 Getting Help

1. **Check the documentation** - Most questions are answered here
2. **Review examples** - See working code in the `examples/` directory
3. **Run tests** - Verify your setup with `./vendor/bin/phpunit`
4. **Check issues** - Search existing GitHub issues
5. **Create an issue** - For bugs or feature requests

## 🗺️ Roadmap

**Recently Completed:**
- ✅ **Many-to-Many relationship support** with automatic QueryBuilder joins
- ✅ **Schema-builder format** as default for migration diff
- ✅ **Comprehensive Many-to-Many documentation** and examples

**Future enhancements being considered:**
- Additional database platform support
- Event system for entity lifecycle
- Advanced caching strategies
- Performance optimizations
- Extended CLI features

## 📄 License

Aurum ORM is open-source software licensed under the [MIT License](../LICENSE). You are free to use, modify, and distribute this software in accordance with the terms of the MIT License.

---

**Ready to get started?** Begin with the [Getting Started Guide](getting-started.md) or jump to any section that interests you!
