<?php

declare(strict_types=1);

namespace Tests\Unit;

use Fduarte42\Aurum\Attribute\{Entity, Id, Column, ManyToOne, OneToMany};
use Fduarte42\Aurum\Connection\Connection;
use Fduarte42\Aurum\DependencyInjection\ContainerBuilder;
use Fduarte42\Aurum\EntityManager;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\UuidInterface;

// Test entities for auto-persist functionality
#[Entity(table: 'test_categories')]
class TestCategoryForAutoPersist
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?UuidInterface $id = null;

    #[Column(type: 'string')]
    private string $name;

    #[OneToMany(targetEntity: TestTaskForAutoPersist::class, mappedBy: 'category')]
    private array $tasks = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getId(): ?UuidInterface { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getTasks(): array { return $this->tasks; }
    
    public function addTask(TestTaskForAutoPersist $task): void
    {
        $this->tasks[] = $task;
        $task->setCategory($this);
    }
}

#[Entity(table: 'test_tasks')]
class TestTaskForAutoPersist
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?UuidInterface $id = null;

    #[Column(type: 'string')]
    private string $title;

    #[ManyToOne(targetEntity: TestCategoryForAutoPersist::class, inversedBy: 'tasks')]
    private ?TestCategoryForAutoPersist $category = null;

    public function __construct(string $title)
    {
        $this->title = $title;
    }

    public function getId(): ?UuidInterface { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function getCategory(): ?TestCategoryForAutoPersist { return $this->category; }
    public function setCategory(?TestCategoryForAutoPersist $category): void { $this->category = $category; }
}

class AutoPersistTest extends TestCase
{
    private EntityManager $entityManager;
    private Connection $connection;

    protected function setUp(): void
    {
        $config = [
            'connection' => [
                'driver' => 'sqlite',
                'path' => ':memory:'
            ]
        ];

        $this->entityManager = ContainerBuilder::createEntityManager($config);
        $this->connection = $this->entityManager->getConnection();

        // Create test tables
        $this->connection->execute('
            CREATE TABLE test_categories (
                id TEXT PRIMARY KEY,
                name TEXT NOT NULL
            )
        ');

        $this->connection->execute('
            CREATE TABLE test_tasks (
                id TEXT PRIMARY KEY,
                title TEXT NOT NULL,
                category_id TEXT,
                FOREIGN KEY (category_id) REFERENCES test_categories(id)
            )
        ');
    }

    public function testAutoPersistManyToOneRelationship(): void
    {
        // Create entities without persisting the category first
        $category = new TestCategoryForAutoPersist('Development');
        $task = new TestTaskForAutoPersist('Implement feature');
        $task->setCategory($category);

        $this->entityManager->beginTransaction();
        
        // Only persist the task - category should be auto-persisted
        $this->entityManager->persist($task);
        $this->entityManager->flush();
        $this->entityManager->commit();

        // Verify both entities were persisted
        $this->assertNotNull($task->getId());
        $this->assertNotNull($category->getId());

        // Verify the foreign key relationship was set correctly
        $sql = 'SELECT category_id FROM test_tasks WHERE id = ?';
        $result = $this->connection->fetchOne($sql, [$task->getId()->toString()]);
        $this->assertEquals($category->getId()->toString(), $result['category_id']);

        // Verify category was actually saved
        $sql = 'SELECT name FROM test_categories WHERE id = ?';
        $result = $this->connection->fetchOne($sql, [$category->getId()->toString()]);
        $this->assertEquals('Development', $result['name']);
    }

    public function testAutoPersistOneToManyRelationship(): void
    {
        // Create entities with OneToMany relationship
        $category = new TestCategoryForAutoPersist('Testing');
        $task1 = new TestTaskForAutoPersist('Write unit tests');
        $task2 = new TestTaskForAutoPersist('Write integration tests');
        
        $category->addTask($task1);
        $category->addTask($task2);

        $this->entityManager->beginTransaction();
        
        // Only persist the category - tasks should be auto-persisted
        $this->entityManager->persist($category);
        $this->entityManager->flush();
        $this->entityManager->commit();

        // Verify all entities were persisted
        $this->assertNotNull($category->getId());
        $this->assertNotNull($task1->getId());
        $this->assertNotNull($task2->getId());

        // Verify the foreign key relationships were set correctly
        $sql = 'SELECT category_id FROM test_tasks WHERE id = ?';
        
        $result1 = $this->connection->fetchOne($sql, [$task1->getId()->toString()]);
        $this->assertEquals($category->getId()->toString(), $result1['category_id']);
        
        $result2 = $this->connection->fetchOne($sql, [$task2->getId()->toString()]);
        $this->assertEquals($category->getId()->toString(), $result2['category_id']);
    }

    public function testAutoPersistDoesNotDuplicateAlreadyPersistedEntities(): void
    {
        // Create and persist category first
        $category = new TestCategoryForAutoPersist('Existing Category');
        
        $this->entityManager->beginTransaction();
        $this->entityManager->persist($category);
        $this->entityManager->flush();
        $this->entityManager->commit();

        $categoryId = $category->getId();

        // Now create a task that references the already-persisted category
        $task = new TestTaskForAutoPersist('New task');
        $task->setCategory($category);

        $this->entityManager->beginTransaction();
        $this->entityManager->persist($task);
        $this->entityManager->flush();
        $this->entityManager->commit();

        // Verify the category ID didn't change (wasn't re-persisted)
        $this->assertEquals($categoryId, $category->getId());

        // Verify only one category exists in the database
        $sql = 'SELECT COUNT(*) as count FROM test_categories';
        $result = $this->connection->fetchOne($sql);
        $this->assertEquals(1, $result['count']);
    }

    public function testAutoPersistWithNullRelationship(): void
    {
        // Create task without category
        $task = new TestTaskForAutoPersist('Standalone task');

        $this->entityManager->beginTransaction();
        $this->entityManager->persist($task);
        $this->entityManager->flush();
        $this->entityManager->commit();

        // Verify task was persisted with NULL category_id
        $sql = 'SELECT category_id FROM test_tasks WHERE id = ?';
        $result = $this->connection->fetchOne($sql, [$task->getId()->toString()]);
        $this->assertNull($result['category_id']);
    }

    public function testAutoPersistWithCircularReference(): void
    {
        // Create entities with circular reference
        $category = new TestCategoryForAutoPersist('Circular');
        $task = new TestTaskForAutoPersist('Circular task');
        
        // Set up circular reference
        $category->addTask($task);
        $task->setCategory($category);

        $this->entityManager->beginTransaction();
        
        // Persist one entity - the other should be auto-persisted without infinite loop
        $this->entityManager->persist($task);
        $this->entityManager->flush();
        $this->entityManager->commit();

        // Verify both entities were persisted
        $this->assertNotNull($task->getId());
        $this->assertNotNull($category->getId());

        // Verify the relationship is correct in the database
        $sql = 'SELECT category_id FROM test_tasks WHERE id = ?';
        $result = $this->connection->fetchOne($sql, [$task->getId()->toString()]);
        $this->assertEquals($category->getId()->toString(), $result['category_id']);
    }
}
