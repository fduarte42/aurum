<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Integration;

use Fduarte42\Aurum\DependencyInjection\ContainerBuilder;
use Fduarte42\Aurum\EntityManagerInterface;
use Fduarte42\Aurum\Tests\Fixtures\Todo;
use PHPUnit\Framework\TestCase;

/**
 * Integration test demonstrating the refactored query result methods
 */
class QueryResultRefactoringTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $config = [
            'connection' => [
                'driver' => 'sqlite',
                'path' => ':memory:'
            ]
        ];

        $this->entityManager = ContainerBuilder::createEntityManager($config);
        $this->createSchema();
    }

    private function createSchema(): void
    {
        $connection = $this->entityManager->getConnection();

        $this->entityManager->beginTransaction();
        $connection->execute('
            CREATE TABLE todos (
                id TEXT PRIMARY KEY,
                title TEXT NOT NULL,
                description TEXT,
                completed INTEGER DEFAULT 0,
                priority REAL,
                created_at TEXT,
                completed_at TEXT,
                user_id TEXT
            )
        ');
        $this->entityManager->commit();
    }

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            // Clear all data for next test
            $this->entityManager->beginTransaction();
            $this->entityManager->getConnection()->execute('DELETE FROM todos');
            $this->entityManager->commit();
            $this->entityManager->clear();
        }
        parent::tearDown();
    }

    public function testGetArrayResultReturnsRawData(): void
    {
        // Create test data
        $this->entityManager->beginTransaction();
        $todo = new Todo('Test Todo ' . uniqid());
        $this->entityManager->persist($todo);
        $this->entityManager->flush();
        $this->entityManager->commit();

        // Test getArrayResult() returns Iterator with raw data
        $qb = $this->entityManager->getRepository(Todo::class)->createQueryBuilder('t');
        $iterator = $qb->getArrayResult();

        $this->assertInstanceOf(\Iterator::class, $iterator);

        $results = [];
        foreach ($iterator as $row) {
            $results[] = $row;
        }
        
        $this->assertCount(1, $results);
        $this->assertIsArray($results[0]);
        $this->assertStringStartsWith('Test Todo', $results[0]['title']);
    }

    public function testGetResultReturnsDetachedEntities(): void
    {
        // Create test data
        $this->entityManager->beginTransaction();
        $todo = new Todo('Detached Todo ' . uniqid());
        $this->entityManager->persist($todo);
        $this->entityManager->flush();
        $this->entityManager->commit();

        // Test getResult() returns detached entity objects
        $qb = $this->entityManager->getRepository(Todo::class)->createQueryBuilder('t');
        $entityIterator = $qb->getResult();

        $this->assertInstanceOf(\Iterator::class, $entityIterator);

        // Convert iterator to array for testing
        $entities = [];
        foreach ($entityIterator as $entity) {
            $entities[] = $entity;
        }

        $this->assertCount(1, $entities);
        $this->assertInstanceOf(Todo::class, $entities[0]);
        $this->assertStringStartsWith('Detached Todo', $entities[0]->getTitle());

        // Verify entities are NOT managed by UnitOfWork
        $this->assertFalse($this->entityManager->contains($entities[0]));
        
        // Modify the detached entity - changes should not be tracked
        $entities[0]->title = 'Modified Title';
        
        // Flush should not persist changes to detached entities
        $this->entityManager->beginTransaction();
        $this->entityManager->flush();
        $this->entityManager->commit();
        
        // Verify changes were not persisted
        $this->entityManager->clear();
        $persistedTodo = $this->entityManager->find(Todo::class, $todo->getId());
        $this->assertStringStartsWith('Detached Todo', $persistedTodo->getTitle()); // Original title
    }

    public function testRepositoryMethodsReturnManagedEntities(): void
    {
        // Create test data with unique title
        $this->entityManager->beginTransaction();
        $todo = new Todo('Managed Todo ' . uniqid());
        $this->entityManager->persist($todo);
        $this->entityManager->flush();
        $this->entityManager->commit();

        // Clear to ensure fresh load and avoid identity map conflicts
        $this->entityManager->clear();

        // Test Repository methods return managed entities
        $repository = $this->entityManager->getRepository(Todo::class);
        $entityIterator = $repository->findAll();

        // Convert iterator to array for testing
        $entities = [];
        foreach ($entityIterator as $entity) {
            $entities[] = $entity;
            // Verify entities ARE managed by UnitOfWork
            $this->assertTrue($this->entityManager->contains($entity));
        }

        $this->assertCount(1, $entities);
        $this->assertInstanceOf(Todo::class, $entities[0]);
        $this->assertStringStartsWith('Managed Todo', $entities[0]->getTitle());
        
        // Modify the managed entity - changes should be tracked
        $entities[0]->title = 'Modified Managed Title';
        
        // Flush should persist changes to managed entities
        $this->entityManager->beginTransaction();
        $this->entityManager->flush();
        $this->entityManager->commit();
        
        // Verify changes were persisted
        $this->entityManager->clear();
        $persistedTodo = $this->entityManager->find(Todo::class, $todo->getId());
        $this->assertEquals('Modified Managed Title', $persistedTodo->getTitle());
    }

    public function testManageMethodAttachesDetachedEntities(): void
    {
        // Create test data
        $this->entityManager->beginTransaction();
        $todo = new Todo('Attachable Todo');
        $this->entityManager->persist($todo);
        $this->entityManager->flush();
        $this->entityManager->commit();

        // Get detached entities using QueryBuilder
        $qb = $this->entityManager->getRepository(Todo::class)->createQueryBuilder('t');
        $entityIterator = $qb->getResult();

        // Convert iterator to array for testing
        $detachedEntities = [];
        foreach ($entityIterator as $entity) {
            $detachedEntities[] = $entity;
        }

        $this->assertCount(1, $detachedEntities);
        $detachedTodo = $detachedEntities[0];
        
        // Verify entity is detached
        $this->assertFalse($this->entityManager->contains($detachedTodo));
        
        // Use manage() to attach the entity
        $managedTodo = $this->entityManager->manage($detachedTodo);
        
        // Verify entity is now managed
        $this->assertTrue($this->entityManager->contains($managedTodo));
        
        // Modify and persist changes
        $managedTodo->title = 'Attached and Modified';
        
        $this->entityManager->beginTransaction();
        $this->entityManager->flush();
        $this->entityManager->commit();
        
        // Verify changes were persisted
        $this->entityManager->clear();
        $persistedTodo = $this->entityManager->find(Todo::class, $todo->getId());
        $this->assertEquals('Attached and Modified', $persistedTodo->getTitle());
    }

    public function testGetResultWithoutEntityClassThrowsException(): void
    {
        // Create a QueryBuilder without entity class context
        $qb = new \Fduarte42\Aurum\Query\QueryBuilder($this->entityManager->getConnection());

        $this->expectException(\Fduarte42\Aurum\Exception\ORMException::class);
        $this->expectExceptionMessage('Cannot hydrate entities: root entity class and metadata factory must be set');

        $qb->select('*')->from('todos', 't')->getResult();
    }

    public function testWorkflowDemonstration(): void
    {
        // Create test data
        $this->entityManager->beginTransaction();
        for ($i = 1; $i <= 3; $i++) {
            $todo = new Todo("Todo {$i}");
            $this->entityManager->persist($todo);
        }
        $this->entityManager->flush();
        $this->entityManager->commit();

        // Scenario 1: Read-only access with detached entities
        $qb = $this->entityManager->getRepository(Todo::class)->createQueryBuilder('t');
        $entityIterator = $qb->where('t.title LIKE :pattern')
                           ->setParameter('pattern', 'Todo%')
                           ->getResult();

        // Convert iterator to array for testing
        $readOnlyTodos = [];
        foreach ($entityIterator as $todo) {
            $readOnlyTodos[] = $todo;
            $this->assertFalse($this->entityManager->contains($todo));
        }

        $this->assertCount(3, $readOnlyTodos);

        // Scenario 2: Selective attachment for modification
        $todoToModify = $readOnlyTodos[0];
        $managedTodo = $this->entityManager->manage($todoToModify);
        $managedTodo->title = 'Modified Todo 1';
        
        $this->entityManager->beginTransaction();
        $this->entityManager->flush();
        $this->entityManager->commit();
        
        // Scenario 3: Traditional repository usage (managed entities)
        $this->entityManager->clear();
        $managedTodoIterator = $this->entityManager->getRepository(Todo::class)->findAll();

        // Convert iterator to array for testing
        $managedTodos = [];
        foreach ($managedTodoIterator as $todo) {
            $managedTodos[] = $todo;
            $this->assertTrue($this->entityManager->contains($todo));
        }

        $this->assertCount(3, $managedTodos);

        // Verify the modification was persisted
        $modifiedTodo = array_filter($managedTodos, fn($t) => $t->getTitle() === 'Modified Todo 1');
        $this->assertCount(1, $modifiedTodo);
    }
}
