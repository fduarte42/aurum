# Testing Guide

This guide covers how to run tests, write new tests, and follow testing best practices with Aurum ORM.

## Running Tests

### Prerequisites

Ensure you have PHPUnit installed via Composer:

```bash
composer install --dev
```

### Basic Test Execution

```bash
# Run all tests
./vendor/bin/phpunit

# Run tests without coverage (faster)
./vendor/bin/phpunit --no-coverage

# Run specific test suite
./vendor/bin/phpunit tests/Unit/
./vendor/bin/phpunit tests/Integration/

# Run specific test class
./vendor/bin/phpunit tests/Unit/EntityManagerTest.php

# Run specific test method
./vendor/bin/phpunit --filter testPersistAndFlush tests/Unit/EntityManagerTest.php
```

### Test Configuration

Tests are configured in `phpunit.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php" colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
    <php>
        <env name="DB_DRIVER" value="sqlite"/>
        <env name="DB_PATH" value=":memory:"/>
        <env name="APP_ENV" value="testing"/>
    </php>
</phpunit>
```

## Test Database Configuration

All tests use SQLite in-memory databases for speed and isolation:

```php
protected function setUp(): void
{
    $config = [
        'connection' => [
            'driver' => 'sqlite',
            'path' => ':memory:'
        ]
    ];

    $this->entityManager = ContainerBuilder::createEntityManager($config);
    $this->createSchema();
}
```

### Benefits of In-Memory Testing

- **Fast**: No disk I/O operations
- **Isolated**: Each test gets a fresh database
- **Consistent**: No external dependencies
- **Parallel**: Tests can run concurrently

## Writing Unit Tests

### Basic Test Structure

```php
<?php

namespace Fduarte42\Aurum\Tests\Unit;

use Fduarte42\Aurum\DependencyInjection\ContainerBuilder;
use Fduarte42\Aurum\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class MyFeatureTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $config = [
            'connection' => [
                'driver' => 'sqlite',
                'path' => ':memory:'
            ]
        ];

        $this->entityManager = ContainerBuilder::createEntityManager($config);
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $this->entityManager->close();
        }
    }

    private function createSchema(): void
    {
        $connection = $this->entityManager->getConnection();
        
        // Create your test tables here
        $connection->execute('
            CREATE TABLE users (
                id TEXT PRIMARY KEY,
                email TEXT UNIQUE NOT NULL,
                name TEXT NOT NULL
            )
        ');
    }

    public function testSomething(): void
    {
        // Your test code here
        $this->assertTrue(true);
    }
}
```

### Testing Entity Operations

```php
public function testCreateAndFindUser(): void
{
    // Create entity
    $user = new User('john@example.com', 'John Doe');
    
    // Persist and flush
    $this->entityManager->persist($user);
    $this->entityManager->flush();
    
    // Verify ID was generated
    $this->assertNotNull($user->getId());
    
    // Find entity
    $foundUser = $this->entityManager->find(User::class, $user->getId());
    
    // Verify found entity
    $this->assertNotNull($foundUser);
    $this->assertEquals('john@example.com', $foundUser->getEmail());
    $this->assertEquals('John Doe', $foundUser->getName());
}
```

### Testing Repository Methods

```php
public function testRepositoryFindBy(): void
{
    // Create test data
    $user1 = new User('john@example.com', 'John Doe');
    $user2 = new User('jane@example.com', 'Jane Smith');
    
    $this->entityManager->persist($user1);
    $this->entityManager->persist($user2);
    $this->entityManager->flush();
    
    // Get repository
    $repository = $this->entityManager->getRepository(User::class);
    
    // Test findBy
    $users = $repository->findBy(['name' => 'John Doe']);
    $this->assertCount(1, $users);
    $this->assertEquals('john@example.com', $users[0]->getEmail());
    
    // Test findOneBy
    $user = $repository->findOneBy(['email' => 'jane@example.com']);
    $this->assertNotNull($user);
    $this->assertEquals('Jane Smith', $user->getName());
}
```

### Testing Query Builder

```php
public function testQueryBuilder(): void
{
    // Create test data
    $user1 = new User('john@example.com', 'John Doe');
    $user2 = new User('jane@example.com', 'Jane Smith');
    
    $this->entityManager->persist($user1);
    $this->entityManager->persist($user2);
    $this->entityManager->flush();
    
    // Test query builder
    $users = $this->entityManager->createQueryBuilder('u')
        ->select('u.name, u.email')
        ->from(User::class, 'u')
        ->where('u.name LIKE :name')
        ->setParameter('name', 'John%')
        ->getResult();
    
    $this->assertCount(1, $users);
    $this->assertEquals('John Doe', $users[0]['name']);
}
```

### Testing Relationships

```php
public function testOneToManyRelationship(): void
{
    // Create entities with relationship
    $user = new User('john@example.com', 'John Doe');
    $post1 = new Post('First Post', 'Content 1', $user);
    $post2 = new Post('Second Post', 'Content 2', $user);
    
    $this->entityManager->persist($user);
    $this->entityManager->persist($post1);
    $this->entityManager->persist($post2);
    $this->entityManager->flush();
    
    // Clear entity manager to force database fetch
    $this->entityManager->clear();
    
    // Fetch user and verify relationship
    $foundUser = $this->entityManager->find(User::class, $user->getId());
    $posts = $foundUser->getPosts();
    
    $this->assertCount(2, $posts);
    $this->assertEquals('First Post', $posts[0]->getTitle());
    $this->assertEquals('Second Post', $posts[1]->getTitle());
}
```

## Testing Migrations

### Migration Test Structure

```php
class MigrationTest extends TestCase
{
    private ConnectionInterface $connection;
    private MigrationManager $migrationManager;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/aurum_migration_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        $this->connection = ConnectionFactory::createSqliteConnection(':memory:');
        
        $configuration = new MigrationConfiguration($this->tempDir, 'TestMigrations');
        $this->migrationManager = new MigrationManager($this->connection, $configuration);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }
}
```

### Testing Migration Execution

```php
public function testMigrationExecution(): void
{
    // Create migration file
    $migrationContent = '<?php
    class Version20231201120000 extends AbstractMigration
    {
        public function up(ConnectionInterface $connection): void
        {
            $connection->execute("CREATE TABLE test_table (id INTEGER PRIMARY KEY)");
        }
        
        public function down(ConnectionInterface $connection): void
        {
            $connection->execute("DROP TABLE test_table");
        }
    }';
    
    file_put_contents($this->tempDir . '/Version20231201120000.php', $migrationContent);
    
    // Execute migration
    $this->migrationManager->migrate();
    
    // Verify table was created
    $tables = $this->connection->fetchAll("SELECT name FROM sqlite_master WHERE type='table'");
    $tableNames = array_column($tables, 'name');
    $this->assertContains('test_table', $tableNames);
}
```

## Testing CLI Commands

### CLI Test Structure

```php
class CliCommandTest extends TestCase
{
    public function testSchemaGeneration(): void
    {
        $command = new SchemaCommand([
            'connection' => [
                'driver' => 'sqlite',
                'path' => ':memory:'
            ]
        ]);
        
        $options = [
            'entities' => 'TestEntity',
            'format' => 'schema-builder'
        ];
        
        $result = $command->execute($options);
        $this->assertEquals(0, $result); // Success exit code
    }
}
```

### Mocking for CLI Tests

```php
public function testEntityResolver(): void
{
    $metadataFactory = $this->createMock(MetadataFactory::class);
    $metadataFactory
        ->expects($this->once())
        ->method('getMetadataFor')
        ->with('TestEntity')
        ->willReturn($this->createMock(EntityMetadataInterface::class));

    $resolver = new EntityResolver($metadataFactory);
    $result = $resolver->resolveEntities(['entities' => 'TestEntity']);
    
    $this->assertEquals(['TestEntity'], $result);
}
```

## Integration Tests

### Full Workflow Testing

```php
class TodoAppIntegrationTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $config = [
            'connection' => [
                'driver' => 'sqlite',
                'path' => ':memory:'
            ]
        ];

        $this->entityManager = ContainerBuilder::createEntityManager($config);
        $this->createSchema();
    }

    public function testCompleteUserWorkflow(): void
    {
        // Create user
        $user = new User('john@example.com', 'John Doe');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        // Create posts for user
        $post1 = new Post('First Post', 'Content', $user);
        $post2 = new Post('Second Post', 'More content', $user);
        
        $this->entityManager->persist($post1);
        $this->entityManager->persist($post2);
        $this->entityManager->flush();
        
        // Query posts by user
        $posts = $this->entityManager->createQueryBuilder('p')
            ->select('p')
            ->from(Post::class, 'p')
            ->join('p.author', 'u')
            ->where('u.email = :email')
            ->setParameter('email', 'john@example.com')
            ->getResult();
        
        $this->assertCount(2, $posts);
        
        // Update user
        $user->setName('John Smith');
        $this->entityManager->flush();
        
        // Verify update
        $updatedUser = $this->entityManager->find(User::class, $user->getId());
        $this->assertEquals('John Smith', $updatedUser->getName());
        
        // Delete post
        $this->entityManager->remove($post1);
        $this->entityManager->flush();
        
        // Verify deletion
        $remainingPosts = $this->entityManager->getRepository(Post::class)->findAll();
        $this->assertCount(1, $remainingPosts);
    }
}
```

## Test Best Practices

### 1. Use Descriptive Test Names

```php
// Good
public function testUserCanBeCreatedWithValidEmailAndName(): void

// Bad  
public function testUser(): void
```

### 2. Follow AAA Pattern

```php
public function testUserCreation(): void
{
    // Arrange
    $email = 'john@example.com';
    $name = 'John Doe';
    
    // Act
    $user = new User($email, $name);
    $this->entityManager->persist($user);
    $this->entityManager->flush();
    
    // Assert
    $this->assertNotNull($user->getId());
    $this->assertEquals($email, $user->getEmail());
    $this->assertEquals($name, $user->getName());
}
```

### 3. Test Edge Cases

```php
public function testFindWithNonExistentId(): void
{
    $user = $this->entityManager->find(User::class, 'non-existent-id');
    $this->assertNull($user);
}

public function testRepositoryCountWithEmptyCriteria(): void
{
    $repository = $this->entityManager->getRepository(User::class);
    $count = $repository->count([]);
    $this->assertEquals(0, $count);
}
```

### 4. Use Data Providers for Multiple Scenarios

```php
/**
 * @dataProvider validEmailProvider
 */
public function testUserCreationWithValidEmails(string $email): void
{
    $user = new User($email, 'Test User');
    $this->entityManager->persist($user);
    $this->entityManager->flush();
    
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

### 5. Clean Up Resources

```php
protected function tearDown(): void
{
    if (isset($this->entityManager)) {
        $this->entityManager->close();
    }
    
    // Clean up temporary files
    if (isset($this->tempDir) && is_dir($this->tempDir)) {
        $this->removeDirectory($this->tempDir);
    }
}
```

## Continuous Integration

### GitHub Actions Example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.4'
        extensions: pdo, pdo_sqlite, pdo_mysql
        
    - name: Install dependencies
      run: composer install --prefer-dist --no-progress
      
    - name: Run tests
      run: ./vendor/bin/phpunit --coverage-clover=coverage.xml
      
    - name: Upload coverage
      uses: codecov/codecov-action@v1
```

## Performance Testing

### Benchmarking Entity Operations

```php
public function testBulkInsertPerformance(): void
{
    $start = microtime(true);
    
    for ($i = 0; $i < 1000; $i++) {
        $user = new User("user{$i}@example.com", "User {$i}");
        $this->entityManager->persist($user);
    }
    
    $this->entityManager->flush();
    
    $duration = microtime(true) - $start;
    $this->assertLessThan(5.0, $duration, 'Bulk insert should complete within 5 seconds');
}
```

This testing approach ensures your Aurum ORM application is robust, reliable, and maintainable.
