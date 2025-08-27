<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Integration;

use Fduarte42\Aurum\DependencyInjection\ContainerBuilder;
use Fduarte42\Aurum\EntityManagerInterface;
use Fduarte42\Aurum\Query\EntityResultIterator;
use Fduarte42\Aurum\Tests\Fixtures\Todo;
use PHPUnit\Framework\TestCase;

/**
 * Test demonstrating the memory efficiency of the EntityResultIterator
 */
class EntityIteratorMemoryTest extends TestCase
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

    public function testIteratorReturnsCorrectType(): void
    {
        // Create test data
        $this->entityManager->beginTransaction();
        for ($i = 1; $i <= 5; $i++) {
            $todo = new Todo("Todo {$i}");
            $this->entityManager->persist($todo);
        }
        $this->entityManager->flush();
        $this->entityManager->commit();

        // Test that getResult() returns an iterator
        $qb = $this->entityManager->getRepository(Todo::class)->createQueryBuilder('t');
        $result = $qb->getResult();
        
        $this->assertInstanceOf(\Iterator::class, $result);
        $this->assertInstanceOf(EntityResultIterator::class, $result);
    }

    public function testIteratorYieldsEntitiesOneAtATime(): void
    {
        // Create test data
        $this->entityManager->beginTransaction();
        for ($i = 1; $i <= 3; $i++) {
            $todo = new Todo("Iterator Todo {$i}");
            $this->entityManager->persist($todo);
        }
        $this->entityManager->flush();
        $this->entityManager->commit();

        // Test iteration
        $qb = $this->entityManager->getRepository(Todo::class)->createQueryBuilder('t');
        $iterator = $qb->getResult();
        
        $count = 0;
        foreach ($iterator as $key => $entity) {
            $this->assertIsInt($key);
            $this->assertEquals($count, $key);
            $this->assertInstanceOf(Todo::class, $entity);
            $this->assertStringStartsWith('Iterator Todo', $entity->getTitle());
            $this->assertFalse($this->entityManager->contains($entity)); // Detached
            $count++;
        }
        
        $this->assertEquals(3, $count);
    }

    public function testIteratorCannotBeRewound(): void
    {
        // Create test data
        $this->entityManager->beginTransaction();
        $todo = new Todo("Rewind Test Todo");
        $this->entityManager->persist($todo);
        $this->entityManager->flush();
        $this->entityManager->commit();

        // Test that iterator can only be used once (like PDOStatement)
        $qb = $this->entityManager->getRepository(Todo::class)->createQueryBuilder('t');
        $iterator = $qb->getResult();

        // First iteration
        $firstCount = 0;
        foreach ($iterator as $entity) {
            $firstCount++;
        }
        $this->assertEquals(1, $firstCount);

        // Second iteration should throw an error because PDOStatement iterator doesn't support rewinding
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Iterator does not support rewinding');

        foreach ($iterator as $entity) {
            // This should throw an error
        }
    }

    public function testIteratorToArrayConversion(): void
    {
        // Create test data
        $this->entityManager->beginTransaction();
        for ($i = 1; $i <= 3; $i++) {
            $todo = new Todo("Array Todo {$i}");
            $this->entityManager->persist($todo);
        }
        $this->entityManager->flush();
        $this->entityManager->commit();

        // Test converting iterator to array
        $qb = $this->entityManager->getRepository(Todo::class)->createQueryBuilder('t');
        $iterator = $qb->getResult();
        
        $array = iterator_to_array($iterator);
        
        $this->assertIsArray($array);
        $this->assertCount(3, $array);
        
        foreach ($array as $entity) {
            $this->assertInstanceOf(Todo::class, $entity);
            $this->assertStringStartsWith('Array Todo', $entity->getTitle());
        }
    }

    public function testIteratorWithEmptyResult(): void
    {
        // No test data created - empty result set
        
        $qb = $this->entityManager->getRepository(Todo::class)->createQueryBuilder('t');
        $iterator = $qb->where('t.title = :title')
                     ->setParameter('title', 'NonExistent')
                     ->getResult();
        
        $count = 0;
        foreach ($iterator as $entity) {
            $count++;
        }
        
        $this->assertEquals(0, $count);
    }

    public function testIteratorMemoryEfficiency(): void
    {
        // This test demonstrates that entities are created on-demand
        // In a real scenario with large datasets, this would show significant memory savings
        
        // Create test data
        $this->entityManager->beginTransaction();
        for ($i = 1; $i <= 10; $i++) {
            $todo = new Todo("Memory Test Todo {$i}");
            $this->entityManager->persist($todo);
        }
        $this->entityManager->flush();
        $this->entityManager->commit();

        $qb = $this->entityManager->getRepository(Todo::class)->createQueryBuilder('t');
        $iterator = $qb->getResult();
        
        // Measure memory before iteration
        $memoryBefore = memory_get_usage();
        
        $processedCount = 0;
        foreach ($iterator as $entity) {
            // Process entity (in real scenario, this might be heavy processing)
            $this->assertInstanceOf(Todo::class, $entity);
            $processedCount++;
            
            // In a real scenario with large datasets, memory usage would remain relatively constant
            // because only one entity is in memory at a time
        }
        
        $memoryAfter = memory_get_usage();
        
        $this->assertEquals(10, $processedCount);
        
        // Memory usage should be reasonable (this is a simple test, but demonstrates the concept)
        $memoryDiff = $memoryAfter - $memoryBefore;
        $this->assertLessThan(1024 * 1024, $memoryDiff); // Less than 1MB for 10 simple entities
    }
}
