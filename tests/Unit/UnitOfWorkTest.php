<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit;

use Fduarte42\Aurum\Connection\ConnectionFactory;
use Fduarte42\Aurum\Exception\ORMException;
use Fduarte42\Aurum\Hydration\EntityHydrator;
use Fduarte42\Aurum\Metadata\MetadataFactory;
use Fduarte42\Aurum\Proxy\LazyGhostProxyFactory;
use Fduarte42\Aurum\Tests\Fixtures\Todo;
use Fduarte42\Aurum\Tests\Fixtures\User;
use Fduarte42\Aurum\Type\TypeRegistry;
use Fduarte42\Aurum\Type\TypeInference;
use Fduarte42\Aurum\UnitOfWork\UnitOfWork;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

class UnitOfWorkTest extends TestCase
{
    private UnitOfWork $unitOfWork;
    private $connection;
    private MetadataFactory $metadataFactory;

    protected function setUp(): void
    {
        $this->connection = ConnectionFactory::createSqliteConnection(':memory:');

        // Set up type system
        $typeRegistry = new TypeRegistry();
        $typeInference = new TypeInference($typeRegistry);
        $this->metadataFactory = new MetadataFactory($typeRegistry, $typeInference);

        $proxyFactory = new LazyGhostProxyFactory();
        $entityHydrator = new EntityHydrator($this->metadataFactory);

        $this->unitOfWork = new UnitOfWork(
            $this->connection,
            $this->metadataFactory,
            $proxyFactory,
            $entityHydrator,
            'test_uow'
        );
        
        $this->createSchema();
    }

    public function testGetSavepointName(): void
    {
        $this->assertEquals('uow_test_uow', $this->unitOfWork->getSavepointName());
    }

    public function testPersistNewEntity(): void
    {
        $user = new User('test@example.com', 'Test User');
        
        $this->unitOfWork->persist($user);
        
        $this->assertTrue($this->unitOfWork->contains($user));
        $this->assertContains($user, $this->unitOfWork->getScheduledInsertions());
        $this->assertNotNull($user->getId()); // UUID should be generated
    }

    public function testPersistExistingEntity(): void
    {
        $user = new User('test@example.com', 'Test User');
        
        // Persist twice
        $this->unitOfWork->persist($user);
        $this->unitOfWork->persist($user);
        
        // Should only be scheduled once
        $insertions = $this->unitOfWork->getScheduledInsertions();
        $this->assertCount(1, $insertions);
    }

    public function testRemoveUnmanagedEntity(): void
    {
        $user = new User('test@example.com', 'Test User');
        
        $this->expectException(ORMException::class);
        $this->expectExceptionMessage('Entity of type');
        $this->unitOfWork->remove($user);
    }

    public function testRemoveManagedEntity(): void
    {
        $user = new User('test@example.com', 'Test User');
        $this->unitOfWork->persist($user);
        
        $this->unitOfWork->remove($user);
        
        $this->assertContains($user, $this->unitOfWork->getScheduledDeletions());
        $this->assertNotContains($user, $this->unitOfWork->getScheduledInsertions());
    }

    public function testDetachEntity(): void
    {
        $user = new User('test@example.com', 'Test User');
        $this->unitOfWork->persist($user);
        
        $this->assertTrue($this->unitOfWork->contains($user));
        
        $this->unitOfWork->detach($user);
        
        $this->assertFalse($this->unitOfWork->contains($user));
        $this->assertNotContains($user, $this->unitOfWork->getScheduledInsertions());
    }

    public function testRefreshUnmanagedEntity(): void
    {
        $user = new User('test@example.com', 'Test User');
        
        $this->expectException(ORMException::class);
        $this->expectExceptionMessage('Entity of type');
        $this->unitOfWork->refresh($user);
    }

    public function testRefreshEntityWithoutId(): void
    {
        $user = new User('test@example.com', 'Test User');
        $this->unitOfWork->persist($user);
        
        // Clear the ID to simulate entity without ID
        $reflection = new \ReflectionClass($user);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($user, null);
        
        $this->expectException(ORMException::class);
        $this->expectExceptionMessage('Entity of type');
        $this->unitOfWork->refresh($user);
    }

    public function testFindNonexistentEntity(): void
    {
        $result = $this->unitOfWork->find(User::class, 'nonexistent-id');
        $this->assertNull($result);
    }

    public function testFindFromIdentityMap(): void
    {
        $user = new User('test@example.com', 'Test User');
        $this->unitOfWork->persist($user);
        
        // Should find from identity map
        $found = $this->unitOfWork->find(User::class, $user->getId());
        $this->assertSame($user, $found);
    }

    public function testFlushWithoutTransaction(): void
    {
        $user = new User('test@example.com', 'Test User');
        $this->unitOfWork->persist($user);
        
        $this->expectException(ORMException::class);
        $this->expectExceptionMessage('No active transaction found');
        $this->unitOfWork->flush();
    }

    public function testFlushWithInsertions(): void
    {
        $user = new User('test@example.com', 'Test User');
        $this->unitOfWork->persist($user);
        
        $this->connection->beginTransaction();
        $this->unitOfWork->flush();
        $this->connection->commit();
        
        // Verify user was inserted
        $result = $this->connection->fetchOne(
            'SELECT * FROM users WHERE id = ?',
            [$user->getId()->toString()]
        );
        $this->assertNotNull($result);
        $this->assertEquals('Test User', $result['name']);
    }

    public function testFlushWithUpdates(): void
    {
        // First insert a user
        $user = new User('test@example.com', 'Original Name');
        $this->unitOfWork->persist($user);
        
        $this->connection->beginTransaction();
        $this->unitOfWork->flush();
        
        // Now update the user
        $user->setName('Updated Name');
        $this->unitOfWork->flush();
        $this->connection->commit();
        
        // Verify user was updated
        $result = $this->connection->fetchOne(
            'SELECT * FROM users WHERE id = ?',
            [$user->getId()->toString()]
        );
        $this->assertEquals('Updated Name', $result['name']);
    }

    public function testFlushWithDeletions(): void
    {
        // First insert a user
        $user = new User('test@example.com', 'Test User');
        $this->unitOfWork->persist($user);
        
        $this->connection->beginTransaction();
        $this->unitOfWork->flush();
        
        // Now delete the user
        $this->unitOfWork->remove($user);
        $this->unitOfWork->flush();
        $this->connection->commit();
        
        // Verify user was deleted
        $result = $this->connection->fetchOne(
            'SELECT * FROM users WHERE id = ?',
            [$user->getId()->toString()]
        );
        $this->assertNull($result);
    }

    public function testCreateSavepointWithoutTransaction(): void
    {
        // Should not create savepoint if no transaction
        $this->unitOfWork->createSavepoint();
        
        // No exception should be thrown, but savepoint won't be created
        $this->assertTrue(true);
    }

    public function testCreateSavepointWithTransaction(): void
    {
        $this->connection->beginTransaction();
        $this->unitOfWork->createSavepoint();
        
        // Should not throw exception
        $this->assertTrue(true);
        
        $this->connection->rollback();
    }

    public function testRollbackToSavepoint(): void
    {
        // Test basic savepoint functionality without UnitOfWork complexity
        $this->connection->beginTransaction();

        // Insert a user directly
        $userId = 'test-savepoint-id';
        $this->connection->execute(
            'INSERT INTO users (id, email, name, created_at) VALUES (?, ?, ?, ?)',
            [$userId, 'test@example.com', 'Test User', '2023-01-01 12:00:00']
        );

        // Create savepoint
        $this->connection->createSavepoint('test_sp');

        // Update the user
        $this->connection->execute(
            'UPDATE users SET name = ? WHERE id = ?',
            ['Changed Name', $userId]
        );

        // Rollback to savepoint
        $this->connection->rollbackToSavepoint('test_sp');

        $this->connection->commit();

        // Verify rollback worked - name should be back to original
        $result = $this->connection->fetchOne(
            'SELECT * FROM users WHERE id = ?',
            [$userId]
        );

        $this->assertNotNull($result);
        $this->assertEquals('Test User', $result['name']);
    }

    public function testGetManagedEntities(): void
    {
        $user1 = new User('test1@example.com', 'User 1');
        $user2 = new User('test2@example.com', 'User 2');
        
        $this->unitOfWork->persist($user1);
        $this->unitOfWork->persist($user2);
        
        $managed = $this->unitOfWork->getManagedEntities();
        $this->assertCount(2, $managed);
        $this->assertContains($user1, $managed);
        $this->assertContains($user2, $managed);
    }

    public function testClearWithoutSavepoint(): void
    {
        $user = new User('test@example.com', 'Test User');
        $this->unitOfWork->persist($user);
        
        $this->unitOfWork->clear();
        
        $this->assertFalse($this->unitOfWork->contains($user));
        $this->assertEmpty($this->unitOfWork->getManagedEntities());
    }

    public function testGetScheduledUpdates(): void
    {
        $user = new User('test@example.com', 'Test User');
        $this->unitOfWork->persist($user);

        $this->connection->beginTransaction();
        $this->unitOfWork->flush();

        // Modify the user to trigger an update
        $user->setName('Updated Name');

        // The user should be detected as needing an update when we flush again
        $this->unitOfWork->flush();

        $this->connection->commit();

        // Since we already flushed, there should be no pending updates
        $updates = $this->unitOfWork->getScheduledUpdates();
        $this->assertEmpty($updates);
    }

    public function testGetScheduledDeletions(): void
    {
        $user = new User('test@example.com', 'Test User');
        $this->unitOfWork->persist($user);

        $this->connection->beginTransaction();
        $this->unitOfWork->flush();

        $this->unitOfWork->remove($user);

        $deletions = $this->unitOfWork->getScheduledDeletions();
        $this->assertContains($user, $deletions);

        $this->connection->commit();
    }

    public function testRefreshWithValidEntity(): void
    {
        $user = new User('test@example.com', 'Original Name');
        $this->unitOfWork->persist($user);

        $this->connection->beginTransaction();
        $this->unitOfWork->flush();

        // Modify in memory
        $user->setName('Modified Name');
        $this->assertEquals('Modified Name', $user->getName());

        // Refresh should reload from database
        $this->unitOfWork->refresh($user);
        $this->assertEquals('Original Name', $user->getName());

        $this->connection->commit();
    }

    public function testFindExistingEntity(): void
    {
        $user = new User('test@example.com', 'Test User');
        $this->unitOfWork->persist($user);

        $this->connection->beginTransaction();
        $this->unitOfWork->flush();
        $this->connection->commit();

        $userId = $user->getId();
        $this->unitOfWork->clear();

        $found = $this->unitOfWork->find(User::class, $userId);
        $this->assertNotNull($found);
        $this->assertEquals('test@example.com', $found->getEmail());
    }

    public function testChangeDetection(): void
    {
        $user = new User('test@example.com', 'Original Name');
        $this->unitOfWork->persist($user);

        $this->connection->beginTransaction();
        $this->unitOfWork->flush();

        // Modify the user
        $user->setName('Updated Name');

        // The change should be detected when we flush again
        $this->unitOfWork->flush();

        $this->connection->commit();

        // Verify the change was persisted
        $result = $this->connection->fetchOne(
            'SELECT * FROM users WHERE id = ?',
            [$user->getId()->toString()]
        );
        $this->assertEquals('Updated Name', $result['name']);
    }

    public function testExtractEntityData(): void
    {
        $user = new User('test@example.com', 'Test User');

        // Use reflection to test private extractEntityData method
        $reflection = new \ReflectionClass($this->unitOfWork);
        $method = $reflection->getMethod('extractEntityData');
        $method->setAccessible(true);

        $data = $method->invoke($this->unitOfWork, $user);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertEquals('test@example.com', $data['email']);
        $this->assertEquals('Test User', $data['name']);
    }

    private function createSchema(): void
    {
        $this->connection->execute('
            CREATE TABLE users (
                id TEXT PRIMARY KEY,
                email TEXT UNIQUE NOT NULL,
                name TEXT NOT NULL,
                created_at TEXT NOT NULL
            )
        ');

        $this->connection->execute('
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

    public function testGetScheduledInsertions(): void
    {
        $user = new User('test@example.com', 'Test User');

        // Initially no scheduled insertions
        $this->assertEmpty($this->unitOfWork->getScheduledInsertions());

        // Persist entity
        $this->unitOfWork->persist($user);

        // Should have scheduled insertion
        $insertions = $this->unitOfWork->getScheduledInsertions();
        $this->assertCount(1, $insertions);
        $this->assertSame($user, $insertions[0]);
    }

    public function testReleaseSavepoint(): void
    {
        $this->connection->beginTransaction();

        // Create savepoint
        $this->unitOfWork->createSavepoint();

        // Release savepoint
        $this->unitOfWork->releaseSavepoint();

        $this->connection->commit();
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function testReleaseSavepointWithoutSavepoint(): void
    {
        // Should not throw exception when releasing non-existent savepoint
        $this->unitOfWork->releaseSavepoint();
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function testPrivateComputeChangeSets(): void
    {
        $user = new User('test@example.com', 'Test User');

        // Persist and flush to get it in the identity map
        $this->connection->beginTransaction();
        $this->unitOfWork->persist($user);
        $this->unitOfWork->flush();

        // Modify the entity
        $user->setName('Updated Name');

        // Use reflection to test private computeChangeSets method
        $reflection = new \ReflectionClass($this->unitOfWork);
        $method = $reflection->getMethod('computeChangeSets');
        $method->setAccessible(true);
        $method->invoke($this->unitOfWork);

        // Should have scheduled update
        $updates = $this->unitOfWork->getScheduledUpdates();
        $this->assertCount(1, $updates);
        $this->assertSame($user, $updates[0]);

        $this->connection->rollback();
    }

    public function testPrivatePopulateEntity(): void
    {
        $user = new User('empty@example.com', 'Empty User');

        $data = [
            'email' => 'populated@example.com',
            'name' => 'Populated User'
        ];

        // Use reflection to test private populateEntity method
        $reflection = new \ReflectionClass($this->unitOfWork);
        $method = $reflection->getMethod('populateEntity');
        $method->setAccessible(true);
        $method->invoke($this->unitOfWork, $user, $data);

        // Verify entity was populated
        $this->assertEquals('populated@example.com', $user->getEmail());
        $this->assertEquals('Populated User', $user->getName());
    }
}
