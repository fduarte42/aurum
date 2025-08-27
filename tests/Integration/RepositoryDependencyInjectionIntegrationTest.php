<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Integration;

use Fduarte42\Aurum\DependencyInjection\ContainerBuilder;
use Fduarte42\Aurum\EntityManagerInterface;
use Fduarte42\Aurum\Repository\Repository;
use Fduarte42\Aurum\Tests\Fixtures\Todo;
use Fduarte42\Aurum\Tests\Fixtures\User;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class RepositoryDependencyInjectionIntegrationTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private ContainerInterface $container;

    protected function setUp(): void
    {
        $config = [
            'connection' => [
                'driver' => 'sqlite',
                'path' => ':memory:'
            ]
        ];

        $this->container = ContainerBuilder::createORM($config);
        $this->entityManager = $this->container->get(EntityManagerInterface::class);
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $this->entityManager->close();
        }
    }

    public function testRepositoryCreationWithDependencyInjection(): void
    {
        // Test that repositories are created correctly with DI
        $todoRepository = $this->entityManager->getRepository(Todo::class);
        $userRepository = $this->entityManager->getRepository(User::class);

        $this->assertInstanceOf(Repository::class, $todoRepository);
        $this->assertInstanceOf(Repository::class, $userRepository);
        $this->assertEquals(Todo::class, $todoRepository->getClassName());
        $this->assertEquals(User::class, $userRepository->getClassName());
    }

    public function testRepositoryFunctionalityWithDependencyInjection(): void
    {
        $todoRepository = $this->entityManager->getRepository(Todo::class);

        // Test basic repository functionality
        $todo = new Todo('Test Todo with DI');
        
        $this->entityManager->beginTransaction();
        $todoRepository->save($todo);
        $this->entityManager->commit();

        $this->assertNotNull($todo->getId());

        // Test finding the entity
        $found = $todoRepository->find($todo->getId());
        $this->assertNotNull($found);
        $this->assertEquals('Test Todo with DI', $found->getTitle());

        // Test count
        $count = $todoRepository->count([]);
        $this->assertEquals(1, $count);

        // Test findAll
        $allTodos = $todoRepository->findAll();
        $this->assertCount(1, $allTodos);
        $this->assertEquals('Test Todo with DI', $allTodos[0]->getTitle());
    }

    public function testCustomRepositoryWithDependencyInjection(): void
    {
        // Create a custom repository and register it in the container
        $this->container->set(CustomTodoRepository::class, function() {
            return new CustomTodoRepository();
        });

        // Manually create repository using factory to test custom repository
        $factory = new \Fduarte42\Aurum\Repository\RepositoryFactory($this->entityManager, $this->container);
        $customRepository = $factory->createRepository(Todo::class, CustomTodoRepository::class);

        $this->assertInstanceOf(CustomTodoRepository::class, $customRepository);
        $this->assertEquals(Todo::class, $customRepository->getClassName());
        $this->assertEquals('custom-method-result', $customRepository->customMethod());
    }

    public function testRepositoryWithCustomDependency(): void
    {
        // Register a custom service in the container
        $customService = new CustomService('test-value');
        $this->container->set(CustomService::class, $customService);

        // Create repository with custom dependency
        $factory = new \Fduarte42\Aurum\Repository\RepositoryFactory($this->entityManager, $this->container);
        $repository = $factory->createRepository(Todo::class, RepositoryWithCustomDependency::class);

        $this->assertInstanceOf(RepositoryWithCustomDependency::class, $repository);
        $this->assertEquals(Todo::class, $repository->getClassName());
        $this->assertSame($customService, $repository->getCustomService());
        $this->assertEquals('test-value', $repository->getCustomService()->getValue());
    }

    public function testBackwardCompatibilityWithTraditionalRepositories(): void
    {
        // Test that repository creation via factory still works (backward compatibility)
        $factory = new \Fduarte42\Aurum\Repository\RepositoryFactory($this->entityManager, $this->container);
        $traditionalRepository = $factory->createRepository(Todo::class);

        $this->assertEquals(Todo::class, $traditionalRepository->getClassName());

        // Test functionality
        $todo = new Todo('Traditional Repository Test');

        $this->entityManager->beginTransaction();
        $traditionalRepository->save($todo);
        $this->entityManager->commit();

        $found = $traditionalRepository->find($todo->getId());
        $this->assertNotNull($found);
        $this->assertEquals('Traditional Repository Test', $found->getTitle());
    }

    public function testEntityManagerContainerIntegration(): void
    {
        // Test that EntityManager has a container (it should be the SimpleContainer created by ContainerBuilder)
        $this->assertNotNull($this->entityManager->getContainer());
        $this->assertInstanceOf(\Psr\Container\ContainerInterface::class, $this->entityManager->getContainer());

        // Test setting a different container
        $newContainer = new \Fduarte42\Aurum\DependencyInjection\SimpleContainer([]);
        $this->entityManager->setContainer($newContainer);
        $this->assertSame($newContainer, $this->entityManager->getContainer());
    }

    private function createSchema(): void
    {
        $connection = $this->entityManager->getConnection();

        $connection->execute('
            CREATE TABLE users (
                id TEXT PRIMARY KEY,
                email TEXT UNIQUE NOT NULL,
                name TEXT NOT NULL,
                created_at TEXT NOT NULL
            )
        ');

        $connection->execute('
            CREATE TABLE todos (
                id TEXT PRIMARY KEY,
                title TEXT NOT NULL,
                description TEXT,
                completed INTEGER NOT NULL DEFAULT 0,
                priority TEXT,
                created_at TEXT NOT NULL,
                completed_at TEXT,
                user_id TEXT,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ');
    }
}

/**
 * Custom repository for testing
 */
class CustomTodoRepository extends Repository
{
    public function customMethod(): string
    {
        return 'custom-method-result';
    }
}

/**
 * Repository with custom dependency for testing
 */
class RepositoryWithCustomDependency extends Repository
{
    private CustomService $customService;

    public function __construct(CustomService $customService)
    {
        parent::__construct();
        $this->customService = $customService;
    }

    public function getCustomService(): CustomService
    {
        return $this->customService;
    }
}

/**
 * Custom service for testing dependency injection
 */
class CustomService
{
    public function __construct(private string $value)
    {
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
