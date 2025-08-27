<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Integration;

use Fduarte42\Aurum\DependencyInjection\ContainerBuilder;
use Fduarte42\Aurum\EntityManagerInterface;
use Fduarte42\Aurum\Repository\ManagedEntityIterator;
use Fduarte42\Aurum\Tests\Fixtures\Todo;
use PHPUnit\Framework\TestCase;

/**
 * Test demonstrating the memory efficiency and functionality of Repository iterators
 */
class RepositoryIteratorTest extends TestCase
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
            $this->entityManager->clear();
        }
        parent::tearDown();
    }

    public function testRepositoryMethodsReturnIterators(): void
    {
        // Create test data
        $this->entityManager->beginTransaction();
        for ($i = 1; $i <= 3; $i++) {
            $todo = new Todo("Iterator Todo {$i}");
            $this->entityManager->persist($todo);
        }
        $this->entityManager->flush();
        $this->entityManager->commit();

        $repository = $this->entityManager->getRepository(Todo::class);

        // Test findAll returns iterator
        $allIterator = $repository->findAll();
        $this->assertInstanceOf(\Iterator::class, $allIterator);
        $this->assertInstanceOf(ManagedEntityIterator::class, $allIterator);

        // Test findBy returns iterator
        $byIterator = $repository->findBy(['title' => 'Iterator Todo 1']);
        $this->assertInstanceOf(\Iterator::class, $byIterator);
        $this->assertInstanceOf(ManagedEntityIterator::class, $byIterator);

        // Test findBySql returns iterator
        $sqlIterator = $repository->findBySql('SELECT * FROM todos WHERE title LIKE ?', ['Iterator%']);
        $this->assertInstanceOf(\Iterator::class, $sqlIterator);
        $this->assertInstanceOf(ManagedEntityIterator::class, $sqlIterator);
    }

    public function testIteratorYieldsManagedEntities(): void
    {
        // Create test data
        $this->entityManager->beginTransaction();
        for ($i = 1; $i <= 3; $i++) {
            $todo = new Todo("Managed Todo {$i}");
            $this->entityManager->persist($todo);
        }
        $this->entityManager->flush();
        $this->entityManager->commit();

        $repository = $this->entityManager->getRepository(Todo::class);
        $iterator = $repository->findAll();
        
        $count = 0;
        foreach ($iterator as $key => $entity) {
            $this->assertIsInt($key);
            $this->assertEquals($count, $key);
            $this->assertInstanceOf(Todo::class, $entity);
            $this->assertStringStartsWith('Managed Todo', $entity->getTitle());
            
            // Verify entities are managed by UnitOfWork
            $this->assertTrue($this->entityManager->contains($entity));
            $count++;
        }
        
        $this->assertEquals(3, $count);
    }

    public function testConvenienceMethodsReturnArrays(): void
    {
        // Create test data
        $this->entityManager->beginTransaction();
        for ($i = 1; $i <= 2; $i++) {
            $todo = new Todo("Array Todo {$i}");
            $this->entityManager->persist($todo);
        }
        $this->entityManager->flush();
        $this->entityManager->commit();

        $repository = $this->entityManager->getRepository(Todo::class);

        // Test convenience methods return arrays
        $allArray = $repository->findAllAsArray();
        $this->assertIsArray($allArray);
        $this->assertCount(2, $allArray);

        $byArray = $repository->findByAsArray(['title' => 'Array Todo 1']);
        $this->assertIsArray($byArray);
        $this->assertCount(1, $byArray);

        $sqlArray = $repository->findBySqlAsArray('SELECT * FROM todos WHERE title LIKE ?', ['Array%']);
        $this->assertIsArray($sqlArray);
        $this->assertCount(2, $sqlArray);

        // Verify all entities are managed
        foreach ($allArray as $entity) {
            $this->assertTrue($this->entityManager->contains($entity));
        }
    }

    public function testIteratorMemoryEfficiency(): void
    {
        // Create test data
        $this->entityManager->beginTransaction();
        for ($i = 1; $i <= 10; $i++) {
            $todo = new Todo("Memory Test Todo {$i}");
            $this->entityManager->persist($todo);
        }
        $this->entityManager->flush();
        $this->entityManager->commit();

        $repository = $this->entityManager->getRepository(Todo::class);
        $iterator = $repository->findAll();
        
        // Measure memory before iteration
        $memoryBefore = memory_get_usage();
        
        $processedCount = 0;
        foreach ($iterator as $entity) {
            // Process entity (in real scenario, this might be heavy processing)
            $this->assertInstanceOf(Todo::class, $entity);
            $this->assertTrue($this->entityManager->contains($entity));
            $processedCount++;
            
            // In a real scenario with large datasets, memory usage would remain relatively constant
            // because entities are hydrated and managed one at a time
        }
        
        $memoryAfter = memory_get_usage();
        
        $this->assertEquals(10, $processedCount);
        
        // Memory usage should be reasonable (this is a simple test, but demonstrates the concept)
        $memoryDiff = $memoryAfter - $memoryBefore;
        $this->assertLessThan(1024 * 1024, $memoryDiff); // Less than 1MB for 10 simple entities
    }

    public function testIteratorToArrayConversion(): void
    {
        // Create test data
        $this->entityManager->beginTransaction();
        for ($i = 1; $i <= 3; $i++) {
            $todo = new Todo("Convert Todo {$i}");
            $this->entityManager->persist($todo);
        }
        $this->entityManager->flush();
        $this->entityManager->commit();

        $repository = $this->entityManager->getRepository(Todo::class);
        $iterator = $repository->findAll();
        
        // Test manual conversion to array
        $array = iterator_to_array($iterator);
        
        $this->assertIsArray($array);
        $this->assertCount(3, $array);
        
        foreach ($array as $entity) {
            $this->assertInstanceOf(Todo::class, $entity);
            $this->assertStringStartsWith('Convert Todo', $entity->getTitle());
            $this->assertTrue($this->entityManager->contains($entity));
        }

        // Test using the convenience method
        $convenienceArray = $repository->findAllAsArray();
        $this->assertEquals($array, $convenienceArray);
    }

    public function testSingleEntityMethodsUnchanged(): void
    {
        // Create test data
        $this->entityManager->beginTransaction();
        $todo = new Todo("Single Todo");
        $this->entityManager->persist($todo);
        $this->entityManager->flush();
        $this->entityManager->commit();

        $repository = $this->entityManager->getRepository(Todo::class);

        // Test that single entity methods still return single entities
        $foundTodo = $repository->find($todo->getId());
        $this->assertInstanceOf(Todo::class, $foundTodo);
        $this->assertEquals('Single Todo', $foundTodo->getTitle());

        $oneByTodo = $repository->findOneBy(['title' => 'Single Todo']);
        $this->assertInstanceOf(Todo::class, $oneByTodo);
        $this->assertEquals('Single Todo', $oneByTodo->getTitle());

        $oneBySqlTodo = $repository->findOneBySql('SELECT * FROM todos WHERE title = ?', ['Single Todo']);
        $this->assertInstanceOf(Todo::class, $oneBySqlTodo);
        $this->assertEquals('Single Todo', $oneBySqlTodo->getTitle());

        // Verify all are managed
        $this->assertTrue($this->entityManager->contains($foundTodo));
        $this->assertTrue($this->entityManager->contains($oneByTodo));
        $this->assertTrue($this->entityManager->contains($oneBySqlTodo));
    }
}
