# Contributing Guide

Thank you for your interest in contributing to Aurum ORM! This guide will help you get started with contributing to the project.

## Getting Started

### Prerequisites

- PHP 8.4 or higher
- Composer
- Git
- PDO SQLite extension (for testing)
- PDO MySQL extension (optional, for MariaDB/MySQL testing)

### Development Setup

1. **Fork and Clone**

```bash
# Fork the repository on GitHub, then clone your fork
git clone https://github.com/YOUR_USERNAME/aurum.git
cd aurum
```

2. **Install Dependencies**

```bash
# Install all dependencies including dev dependencies
composer install
```

3. **Run Tests**

```bash
# Run the full test suite
./vendor/bin/phpunit

# Run tests without coverage (faster)
./vendor/bin/phpunit --no-coverage
```

4. **Verify CLI Tools**

```bash
# Test the unified CLI
php bin/aurum-cli.php help
php bin/aurum-cli.php schema generate --help
```

## Development Workflow

### 1. Create a Feature Branch

```bash
# Create and switch to a new branch
git checkout -b feature/your-feature-name

# Or for bug fixes
git checkout -b fix/issue-description
```

### 2. Make Your Changes

- Write clean, well-documented code
- Follow existing code style and conventions
- Add tests for new functionality
- Update documentation as needed

### 3. Test Your Changes

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test suites
./vendor/bin/phpunit tests/Unit/
./vendor/bin/phpunit tests/Integration/

# Test CLI functionality
php bin/aurum-cli.php schema generate --entities="TestEntity"
```

### 4. Commit Your Changes

```bash
# Stage your changes
git add .

# Commit with a descriptive message
git commit -m "Add feature: description of what you added"

# Or for bug fixes
git commit -m "Fix: description of what you fixed"
```

### 5. Push and Create Pull Request

```bash
# Push your branch
git push origin feature/your-feature-name

# Create a pull request on GitHub
```

## Code Style Guidelines

### PHP Standards

- Follow PSR-12 coding standards
- Use strict types: `declare(strict_types=1);`
- Use PHP 8.4 features appropriately
- Prefer composition over inheritance
- Use meaningful variable and method names

### Example Code Style

```php
<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Example;

use Fduarte42\Aurum\SomeInterface;

/**
 * Example class demonstrating code style
 */
final class ExampleClass implements SomeInterface
{
    public function __construct(
        private readonly string $requiredParam,
        private readonly ?string $optionalParam = null
    ) {
    }

    public function doSomething(array $data): array
    {
        $result = [];
        
        foreach ($data as $item) {
            if ($this->isValid($item)) {
                $result[] = $this->processItem($item);
            }
        }
        
        return $result;
    }

    private function isValid(mixed $item): bool
    {
        return $item !== null && $item !== '';
    }

    private function processItem(mixed $item): string
    {
        return (string) $item;
    }
}
```

### Documentation Standards

- Use PHPDoc for all public methods
- Include parameter and return type documentation
- Add examples for complex functionality
- Keep documentation up-to-date with code changes

```php
/**
 * Finds entities by the given criteria
 *
 * @param array<string, mixed> $criteria Search criteria
 * @param array<string, string>|null $orderBy Sort order
 * @param int|null $limit Maximum number of results
 * @param int|null $offset Number of results to skip
 * @return array<object> Found entities
 *
 * @example
 * $users = $repository->findBy(['active' => true], ['name' => 'ASC'], 10);
 */
public function findBy(
    array $criteria,
    ?array $orderBy = null,
    ?int $limit = null,
    ?int $offset = null
): array {
    // Implementation...
}
```

## Testing Guidelines

### Test Structure

All tests should follow this structure:

```php
<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    protected function setUp(): void
    {
        // Set up test dependencies
    }

    protected function tearDown(): void
    {
        // Clean up resources
    }

    public function testSomethingSpecific(): void
    {
        // Arrange
        $input = 'test data';
        
        // Act
        $result = $this->subject->doSomething($input);
        
        // Assert
        $this->assertEquals('expected result', $result);
    }
}
```

### Test Categories

#### Unit Tests (`tests/Unit/`)

- Test individual classes in isolation
- Use mocks for dependencies
- Fast execution (< 1 second per test)
- Use SQLite in-memory databases

```php
public function testEntityManagerPersist(): void
{
    $entity = new TestEntity();
    
    $this->entityManager->persist($entity);
    $this->entityManager->flush();
    
    $this->assertNotNull($entity->getId());
}
```

#### Integration Tests (`tests/Integration/`)

- Test multiple components working together
- Use real database connections
- Test complete workflows

```php
public function testCompleteUserWorkflow(): void
{
    // Create user
    $user = new User('test@example.com', 'Test User');
    $this->entityManager->persist($user);
    $this->entityManager->flush();
    
    // Create related entities
    $post = new Post('Test Post', $user);
    $this->entityManager->persist($post);
    $this->entityManager->flush();
    
    // Query and verify
    $foundUser = $this->entityManager->find(User::class, $user->getId());
    $this->assertCount(1, $foundUser->getPosts());
}
```

### Test Best Practices

1. **Use descriptive test names**
   ```php
   // Good
   public function testUserCanBeCreatedWithValidEmailAndName(): void
   
   // Bad
   public function testUser(): void
   ```

2. **Test edge cases**
   ```php
   public function testFindWithNonExistentIdReturnsNull(): void
   public function testFindByWithEmptyArrayReturnsEmptyArray(): void
   ```

3. **Use data providers for multiple scenarios**
   ```php
   /**
    * @dataProvider validEmailProvider
    */
   public function testEmailValidation(string $email): void
   {
       $user = new User($email, 'Test User');
       $this->assertEquals($email, $user->getEmail());
   }

   public function validEmailProvider(): array
   {
       return [
           ['user@example.com'],
           ['test.email@domain.co.uk'],
           ['user+tag@example.org'],
       ];
   }
   ```

## Adding New Features

### 1. Core Components

When adding new core components:

- Add comprehensive unit tests
- Update relevant documentation
- Consider backward compatibility
- Add integration tests if needed

### 2. CLI Commands

When adding new CLI commands:

- Extend `AbstractCommand`
- Add comprehensive help text
- Include practical examples
- Add unit tests for command logic
- Test with real entities

```php
class NewCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'new:command';
    }

    public function getDescription(): string
    {
        return 'Description of what this command does';
    }

    public function getHelp(): string
    {
        return "Detailed help text with examples...";
    }

    public function execute(array $options): int
    {
        // Implementation
        return 0; // Success
    }
}
```

### 3. Database Types

When adding new database types:

- Implement `TypeInterface`
- Add comprehensive tests
- Update type registry
- Document usage examples

```php
class CustomType implements TypeInterface
{
    public function getName(): string
    {
        return 'custom';
    }

    public function convertToPHPValue(mixed $value): mixed
    {
        // Convert database value to PHP value
    }

    public function convertToDatabaseValue(mixed $value): mixed
    {
        // Convert PHP value to database value
    }

    // Other required methods...
}
```

## Documentation

### Updating Documentation

When making changes that affect user-facing functionality:

1. Update relevant documentation files in `docs/`
2. Add code examples for new features
3. Update CLI help text
4. Consider adding examples to `examples/` directory

### Documentation Structure

- `docs/getting-started.md` - Basic setup and usage
- `docs/architecture.md` - Technical architecture details
- `docs/cli-tools.md` - CLI tool documentation
- `docs/testing.md` - Testing guidelines
- `docs/migrations.md` - Migration system
- `docs/entities.md` - Entity management
- `docs/contributing.md` - This file

## Pull Request Guidelines

### Before Submitting

- [ ] All tests pass
- [ ] Code follows style guidelines
- [ ] Documentation is updated
- [ ] Commit messages are clear
- [ ] No merge conflicts

### Pull Request Template

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
- [ ] Unit tests added/updated
- [ ] Integration tests added/updated
- [ ] Manual testing completed

## Documentation
- [ ] Documentation updated
- [ ] Examples added/updated
- [ ] CLI help updated

## Checklist
- [ ] Code follows style guidelines
- [ ] Self-review completed
- [ ] Tests pass locally
```

## Reporting Issues

### Bug Reports

When reporting bugs, include:

- PHP version
- Aurum version
- Database type and version
- Minimal code example
- Expected vs actual behavior
- Error messages/stack traces

### Feature Requests

When requesting features:

- Describe the use case
- Explain why it's needed
- Provide examples of desired API
- Consider backward compatibility

## Community Guidelines

### Code of Conduct

- Be respectful and inclusive
- Focus on constructive feedback
- Help others learn and grow
- Maintain professional communication

### Getting Help

- Check existing documentation first
- Search existing issues
- Provide minimal reproducible examples
- Be patient and respectful

## Release Process

### Versioning

Aurum follows semantic versioning:

- **Major** (X.0.0): Breaking changes
- **Minor** (0.X.0): New features, backward compatible
- **Patch** (0.0.X): Bug fixes, backward compatible

### Release Checklist

- [ ] All tests pass
- [ ] Documentation updated
- [ ] CHANGELOG.md updated
- [ ] Version bumped
- [ ] Git tag created

## 📄 License

By contributing to Aurum ORM, you agree that your contributions will be licensed under the [MIT License](../LICENSE), the same license that covers the project.

Thank you for contributing to Aurum ORM! Your contributions help make this project better for everyone.
