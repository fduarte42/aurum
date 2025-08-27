<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Integration;

use Fduarte42\Aurum\Connection\ConnectionFactory;
use Fduarte42\Aurum\DependencyInjection\ContainerBuilder;
use Fduarte42\Aurum\EntityManagerInterface;
use Fduarte42\Aurum\Tests\Fixtures\Todo;
use Fduarte42\Aurum\Tests\Fixtures\User;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

/**
 * Comprehensive TodoApp integration tests
 */
class TodoAppTest extends TestCase
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

    public function testCreateUser(): void
    {
        $user = new User('john@example.com', 'John Doe');
        
        $this->entityManager->beginTransaction();
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->entityManager->commit();

        $this->assertNotNull($user->id);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertEquals('John Doe', $user->name);
    }

    public function testCreateTodo(): void
    {
        $todo = new Todo('Buy groceries', 'Milk, bread, eggs');
        $todo->priority = BigDecimal::of('5.50');

        $this->entityManager->beginTransaction();
        $this->entityManager->persist($todo);
        $this->entityManager->flush();
        $this->entityManager->commit();

        $this->assertNotNull($todo->id);
        $this->assertEquals('Buy groceries', $todo->title);
        $this->assertEquals('Milk, bread, eggs', $todo->description);
        $this->assertFalse($todo->completed);
        $this->assertEquals('5.50', (string) $todo->priority);
    }

    public function testUserTodoRelationship(): void
    {
        $user = new User('jane@example.com', 'Jane Smith');
        $todo1 = new Todo('Task 1');
        $todo2 = new Todo('Task 2');

        $user->addTodo($todo1);
        $user->addTodo($todo2);

        $this->entityManager->beginTransaction();
        $this->entityManager->persist($user);
        $this->entityManager->persist($todo1);
        $this->entityManager->persist($todo2);
        $this->entityManager->flush();
        $this->entityManager->commit();

        // Verify relationships
        $this->assertSame($user, $todo1->user);
        $this->assertSame($user, $todo2->user);
        $this->assertCount(2, $user->todos);
    }

    public function testFindTodo(): void
    {
        $todo = new Todo('Find me', 'Test description');
        
        $this->entityManager->beginTransaction();
        $this->entityManager->persist($todo);
        $this->entityManager->flush();
        $this->entityManager->commit();

        $id = $todo->id;
        $this->entityManager->clear();

        $foundTodo = $this->entityManager->find(Todo::class, $id);
        
        $this->assertNotNull($foundTodo);
        $this->assertEquals('Find me', $foundTodo->title);
        $this->assertEquals('Test description', $foundTodo->description);
    }

    public function testTodoRepository(): void
    {
        $todo1 = new Todo('Todo 1');
        $todo2 = new Todo('Todo 2');
        $todo2->complete();

        $this->entityManager->beginTransaction();
        $this->entityManager->persist($todo1);
        $this->entityManager->persist($todo2);
        $this->entityManager->flush();
        $this->entityManager->commit();

        $todoRepo = $this->entityManager->getRepository(Todo::class);

        // Test findAll
        $allTodosIterator = $todoRepo->findAll();
        $allTodos = iterator_to_array($allTodosIterator);
        $this->assertCount(2, $allTodos);

        // Test findBy
        $completedTodosIterator = $todoRepo->findBy(['completed' => true]);
        $completedTodos = iterator_to_array($completedTodosIterator);
        $this->assertCount(1, $completedTodos);
        $this->assertEquals('Todo 2', $completedTodos[0]->title);

        // Test findOneBy
        $incompleteTodo = $todoRepo->findOneBy(['completed' => false]);
        $this->assertNotNull($incompleteTodo);
        $this->assertEquals('Todo 1', $incompleteTodo->title);

        // Test count
        $totalCount = $todoRepo->count();
        $this->assertEquals(2, $totalCount);

        $completedCount = $todoRepo->count(['completed' => true]);
        $this->assertEquals(1, $completedCount);
    }

    public function testQueryBuilder(): void
    {
        $user = new User('test@example.com', 'Test User');
        $todo1 = new Todo('High Priority', 'Important task');
        $todo1->priority = BigDecimal::of('10.00');
        $todo2 = new Todo('Low Priority', 'Less important');
        $todo2->priority = BigDecimal::of('1.00');

        $this->entityManager->beginTransaction();

        // First persist and flush the user to get an ID
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Now set the user on todos and persist them
        $user->addTodo($todo1);
        $user->addTodo($todo2);
        $todo1->user = $user;
        $todo2->user = $user;

        $this->entityManager->persist($todo1);
        $this->entityManager->persist($todo2);
        $this->entityManager->flush();
        $this->entityManager->commit();

        $todoRepo = $this->entityManager->getRepository(Todo::class);
        
        // Test query builder with joins
        $qb = $todoRepo->createQueryBuilder('t')
            ->innerJoin('users', 'u', 't.user_id = u.id')
            ->where('u.email = :email')
            ->andWhere('CAST(t.priority AS REAL) > CAST(:minPriority AS REAL)')
            ->orderBy('t.priority', 'DESC')
            ->setParameter('email', 'test@example.com')
            ->setParameter('minPriority', '5.00');

        $statement = $qb->getArrayResult();
        $results = [];
        foreach ($statement as $row) {
            $results[] = $row;
        }
        $this->assertCount(1, $results);
        $this->assertEquals('High Priority', $results[0]['title']);
    }

    public function testMultipleUnitOfWorks(): void
    {
        $user1 = new User('user1@example.com', 'User 1');
        $user2 = new User('user2@example.com', 'User 2');

        $this->entityManager->beginTransaction();

        // First unit of work
        $uow1 = $this->entityManager->createUnitOfWork();
        $this->entityManager->setUnitOfWork($uow1);
        $this->entityManager->persist($user1);
        $this->entityManager->flush();

        // Second unit of work
        $uow2 = $this->entityManager->createUnitOfWork();
        $this->entityManager->setUnitOfWork($uow2);
        $this->entityManager->persist($user2);
        $this->entityManager->flush();

        $this->entityManager->commit();

        // Verify both users were saved
        $userRepo = $this->entityManager->getRepository(User::class);
        $this->assertEquals(2, $userRepo->count());
    }

    public function testTransactionRollback(): void
    {
        $todo = new Todo('Will be rolled back');

        $this->entityManager->beginTransaction();
        $this->entityManager->persist($todo);
        $this->entityManager->flush();
        $this->entityManager->rollback();

        // Verify todo was not saved
        $todoRepo = $this->entityManager->getRepository(Todo::class);
        $this->assertEquals(0, $todoRepo->count());
    }

    public function testSavepointRollback(): void
    {
        $user = new User('savepoint@example.com', 'Savepoint User');
        $todo1 = new Todo('Will be saved');
        $todo2 = new Todo('Will be rolled back');

        $this->entityManager->beginTransaction();

        // Save user and first todo
        $this->entityManager->persist($user);
        $this->entityManager->persist($todo1);
        $this->entityManager->flush();

        // Create new unit of work with savepoint
        $uow2 = $this->entityManager->createUnitOfWork();
        $this->entityManager->setUnitOfWork($uow2);
        $this->entityManager->persist($todo2);
        
        try {
            $this->entityManager->flush();
            // Simulate an error and rollback the savepoint
            $uow2->rollbackToSavepoint();
        } catch (\Exception $e) {
            $uow2->rollbackToSavepoint();
        }

        $this->entityManager->commit();

        // Verify only user and first todo were saved
        $userRepo = $this->entityManager->getRepository(User::class);
        $todoRepo = $this->entityManager->getRepository(Todo::class);
        
        $this->assertEquals(1, $userRepo->count());
        $this->assertEquals(1, $todoRepo->count());

        $allTodos = iterator_to_array($todoRepo->findAll());
        $this->assertEquals('Will be saved', $allTodos[0]->title);
    }

    public function testDecimalPrecision(): void
    {
        $todo = new Todo('Decimal test');
        $todo->priority = BigDecimal::of('123.456789');

        $this->entityManager->beginTransaction();
        $this->entityManager->persist($todo);
        $this->entityManager->flush();
        $this->entityManager->commit();

        $id = $todo->id;
        $this->entityManager->clear();

        $foundTodo = $this->entityManager->find(Todo::class, $id);
        $this->assertEquals('123.456789', (string) $foundTodo->priority); // Preserves full precision
    }

    private function createSchema(): void
    {
        $connection = $this->entityManager->getConnection();

        // Create users table
        $connection->execute('
            CREATE TABLE users (
                id TEXT PRIMARY KEY,
                email TEXT UNIQUE NOT NULL,
                name TEXT NOT NULL,
                created_at TEXT NOT NULL
            )
        ');

        // Create todos table
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
