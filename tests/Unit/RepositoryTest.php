<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit;

use Fduarte42\Aurum\Connection\ConnectionFactory;
use Fduarte42\Aurum\DependencyInjection\ContainerBuilder;
use Fduarte42\Aurum\EntityManagerInterface;
use Fduarte42\Aurum\Repository\Repository;
use Fduarte42\Aurum\Tests\Fixtures\Todo;
use Fduarte42\Aurum\Tests\Fixtures\User;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

class RepositoryTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private Repository $todoRepository;
    private Repository $userRepository;

    protected function setUp(): void
    {
        $config = [
            'connection' => [
                'driver' => 'sqlite',
                'path' => ':memory:'
            ]
        ];

        $this->entityManager = ContainerBuilder::createEntityManager($config);
        $this->todoRepository = $this->entityManager->getRepository(Todo::class);
        $this->userRepository = $this->entityManager->getRepository(User::class);
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $this->entityManager->close();
        }
    }

    private function saveInTransaction(callable $callback): void
    {
        $this->entityManager->beginTransaction();
        $callback();
        $this->entityManager->commit();
    }

    public function testGetClassName(): void
    {
        $this->assertEquals(Todo::class, $this->todoRepository->getClassName());
        $this->assertEquals(User::class, $this->userRepository->getClassName());
    }

    public function testSaveAndDelete(): void
    {
        $todo = new Todo('Test Todo');

        $this->entityManager->beginTransaction();
        $this->todoRepository->save($todo);
        $this->entityManager->commit();

        $this->assertNotNull($todo->getId());

        $found = $this->todoRepository->find($todo->getId());
        $this->assertNotNull($found);
        $this->assertEquals('Test Todo', $found->getTitle());

        $this->entityManager->beginTransaction();
        $this->todoRepository->delete($todo);
        $this->entityManager->commit();

        $notFound = $this->todoRepository->find($todo->getId());
        $this->assertNull($notFound);
    }

    public function testFindWithPagination(): void
    {
        $this->saveInTransaction(function() {
            // Create multiple todos
            for ($i = 1; $i <= 10; $i++) {
                $todo = new Todo("Todo {$i}");
                $this->todoRepository->save($todo);
            }
        });
        
        // Test pagination
        $page1 = $this->todoRepository->findWithPagination([], null, 1, 3);
        $this->assertCount(3, $page1);
        
        $page2 = $this->todoRepository->findWithPagination([], null, 2, 3);
        $this->assertCount(3, $page2);
        
        $page4 = $this->todoRepository->findWithPagination([], null, 4, 3);
        $this->assertCount(1, $page4); // Only 1 item on page 4
    }

    public function testFindByField(): void
    {
        $this->saveInTransaction(function() {
            $todo1 = new Todo('Completed Todo');
            $todo1->setCompleted(true);
            $this->todoRepository->save($todo1);

            $todo2 = new Todo('Incomplete Todo');
            $this->todoRepository->save($todo2);
        });

        $completedTodos = $this->todoRepository->findByField('completed', true);
        $this->assertCount(1, $completedTodos);
        $this->assertEquals('Completed Todo', $completedTodos[0]->getTitle());
    }

    public function testFindOneByField(): void
    {
        $this->saveInTransaction(function() {
            $todo = new Todo('Unique Todo');
            $this->todoRepository->save($todo);
        });

        $found = $this->todoRepository->findOneByField('title', 'Unique Todo');
        $this->assertNotNull($found);
        $this->assertEquals('Unique Todo', $found->getTitle());

        $notFound = $this->todoRepository->findOneByField('title', 'Nonexistent');
        $this->assertNull($notFound);
    }

    public function testExists(): void
    {
        $this->saveInTransaction(function() {
            $todo = new Todo('Exists Todo');
            $this->todoRepository->save($todo);
        });

        $this->assertTrue($this->todoRepository->exists(['title' => 'Exists Todo']));
        $this->assertFalse($this->todoRepository->exists(['title' => 'Nonexistent']));
    }

    public function testFindByLike(): void
    {
        $this->saveInTransaction(function() {
            $todo1 = new Todo('Buy groceries');
            $todo2 = new Todo('Buy books');
            $todo3 = new Todo('Sell items');

            $this->todoRepository->save($todo1);
            $this->todoRepository->save($todo2);
            $this->todoRepository->save($todo3);
        });

        $buyTodos = $this->todoRepository->findByLike('title', 'Buy%');
        $this->assertCount(2, $buyTodos);
    }

    public function testFindByRange(): void
    {
        $this->saveInTransaction(function() {
            $todo1 = new Todo('Low Priority');
            $todo1->setPriority(BigDecimal::of('1.0'));

            $todo2 = new Todo('Medium Priority');
            $todo2->setPriority(BigDecimal::of('5.0'));

            $todo3 = new Todo('High Priority');
            $todo3->setPriority(BigDecimal::of('10.0'));

            $this->todoRepository->save($todo1);
            $this->todoRepository->save($todo2);
            $this->todoRepository->save($todo3);
        });

        $mediumTodos = $this->todoRepository->findByRange('priority', '3.0', '7.0');
        $this->assertCount(1, $mediumTodos);
        $this->assertEquals('Medium Priority', $mediumTodos[0]->getTitle());
    }

    public function testFindBySql(): void
    {
        $this->saveInTransaction(function() {
            $todo = new Todo('SQL Todo');
            $this->todoRepository->save($todo);
        });
        
        $results = $this->todoRepository->findBySql(
            'SELECT * FROM todos WHERE title = ?',
            ['SQL Todo']
        );
        
        $this->assertCount(1, $results);
        $this->assertEquals('SQL Todo', $results[0]->getTitle());
    }

    public function testFindOneBySql(): void
    {
        $this->saveInTransaction(function() {
            $todo = new Todo('SQL One Todo');
            $this->todoRepository->save($todo);
        });

        $result = $this->todoRepository->findOneBySql(
            'SELECT * FROM todos WHERE title = ?',
            ['SQL One Todo']
        );

        $this->assertNotNull($result);
        $this->assertEquals('SQL One Todo', $result->getTitle());

        $notFound = $this->todoRepository->findOneBySql(
            'SELECT * FROM todos WHERE title = ?',
            ['Nonexistent']
        );

        $this->assertNull($notFound);
    }

    public function testFindByWithOrderBy(): void
    {
        $this->saveInTransaction(function() {
            $todo1 = new Todo('B Todo');
            $todo2 = new Todo('A Todo');
            $todo3 = new Todo('C Todo');

            $this->todoRepository->save($todo1);
            $this->todoRepository->save($todo2);
            $this->todoRepository->save($todo3);
        });

        $todos = $this->todoRepository->findBy([], ['title' => 'ASC']);
        $this->assertEquals('A Todo', $todos[0]->getTitle());
        $this->assertEquals('B Todo', $todos[1]->getTitle());
        $this->assertEquals('C Todo', $todos[2]->getTitle());
    }

    public function testFindByWithLimitAndOffset(): void
    {
        $this->saveInTransaction(function() {
            for ($i = 1; $i <= 5; $i++) {
                $todo = new Todo("Todo {$i}");
                $this->todoRepository->save($todo);
            }
        });

        $todos = $this->todoRepository->findBy([], null, 2, 1);
        $this->assertCount(2, $todos);
    }

    public function testFindByWithArrayCriteria(): void
    {
        $this->saveInTransaction(function() {
            $todo1 = new Todo('Todo 1');
            $todo1->setCompleted(true);
            $todo2 = new Todo('Todo 2');
            $todo2->setCompleted(false);
            $todo3 = new Todo('Todo 3');
            $todo3->setCompleted(true);

            $this->todoRepository->save($todo1);
            $this->todoRepository->save($todo2);
            $this->todoRepository->save($todo3);
        });
        
        // Test with array values (IN clause) - use database values directly
        $completedTodos = $this->todoRepository->findBy(['completed' => [1]]);
        $this->assertCount(2, $completedTodos);
    }

    public function testFindByWithNullCriteria(): void
    {
        $this->saveInTransaction(function() {
            $todo1 = new Todo('Todo with description', 'Has description');
            $todo2 = new Todo('Todo without description');

            $this->todoRepository->save($todo1);
            $this->todoRepository->save($todo2);
        });

        $todosWithoutDescription = $this->todoRepository->findBy(['description' => null]);
        $this->assertCount(1, $todosWithoutDescription);
        $this->assertEquals('Todo without description', $todosWithoutDescription[0]->getTitle());
    }

    public function testCount(): void
    {
        $this->saveInTransaction(function() {
            for ($i = 1; $i <= 5; $i++) {
                $todo = new Todo("Todo {$i}");
                $this->todoRepository->save($todo);
            }
        });

        $count = $this->todoRepository->count([]);
        $this->assertEquals(5, $count);

        $completedCount = $this->todoRepository->count(['completed' => false]);
        $this->assertEquals(5, $completedCount); // All are incomplete by default
    }

    public function testFindByWithOrderByAlphabetical(): void
    {
        $this->saveInTransaction(function() {
            $todo1 = new Todo('Z Todo');
            $todo2 = new Todo('A Todo');
            $this->todoRepository->save($todo1);
            $this->todoRepository->save($todo2);
        });

        $todos = $this->todoRepository->findBy([], ['title' => 'ASC']);
        $this->assertEquals('A Todo', $todos[0]->getTitle());
        $this->assertEquals('Z Todo', $todos[1]->getTitle());
    }

    public function testFindByWithLimitOnly(): void
    {
        $this->saveInTransaction(function() {
            for ($i = 1; $i <= 5; $i++) {
                $todo = new Todo("Todo {$i}");
                $this->todoRepository->save($todo);
            }
        });

        $todos = $this->todoRepository->findBy([], null, 3);
        $this->assertCount(3, $todos);
    }

    public function testFindByWithOffsetOnly(): void
    {
        $this->saveInTransaction(function() {
            for ($i = 1; $i <= 5; $i++) {
                $todo = new Todo("Todo {$i}");
                $this->todoRepository->save($todo);
            }
        });

        $todos = $this->todoRepository->findBy([], null, 2, 2);
        $this->assertCount(2, $todos);
    }

    public function testRepositoryInheritance(): void
    {
        // Test that repository is properly instantiated
        $this->assertInstanceOf(\Fduarte42\Aurum\Repository\Repository::class, $this->todoRepository);
        $this->assertEquals(Todo::class, $this->todoRepository->getClassName());
    }

    public function testApplyCriteriaWithComplexConditions(): void
    {
        $this->saveInTransaction(function() {
            $todo1 = new Todo('Todo 1');
            $todo1->setCompleted(true);
            $todo1->setPriority(BigDecimal::of('5.0'));

            $todo2 = new Todo('Todo 2');
            $todo2->setCompleted(false);
            $todo2->setPriority(BigDecimal::of('3.0'));

            $this->todoRepository->save($todo1);
            $this->todoRepository->save($todo2);
        });

        // Test multiple criteria - use database values directly
        $todos = $this->todoRepository->findBy([
            'completed' => 1,  // Use database value directly
            'priority' => '5.00'  // Use exact decimal representation
        ]);

        $this->assertCount(1, $todos);
        $this->assertEquals('Todo 1', $todos[0]->getTitle());
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
