<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Integration;

use Fduarte42\Aurum\Attribute\{Entity, Id, Column, ManyToMany, JoinTable, JoinColumn};
use Fduarte42\Aurum\DependencyInjection\ContainerBuilder;
use Fduarte42\Aurum\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

// Test entities for Many-to-Many relationships
#[Entity(table: 'test_users')]
class TestUser
{
    #[Id]
    #[Column(type: 'uuid')]
    public private(set) ?string $id = null;

    #[ManyToMany(targetEntity: TestRole::class)]
    #[JoinTable(
        name: 'user_roles',
        joinColumns: [new JoinColumn(name: 'user_id', referencedColumnName: 'id')],
        inverseJoinColumns: [new JoinColumn(name: 'role_id', referencedColumnName: 'id')]
    )]
    public array $roles = [];

    public function __construct(
        #[Column(type: 'string', length: 255)]
        public string $name = ''
    ) {
    }
    
    public function addRole(TestRole $role): void
    {
        if (!in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }
    }
    
    public function removeRole(TestRole $role): void
    {
        $key = array_search($role, $this->roles, true);
        if ($key !== false) {
            unset($this->roles[$key]);
            $this->roles = array_values($this->roles);
        }
    }
}

#[Entity(table: 'test_roles')]
class TestRole
{
    #[Id]
    #[Column(type: 'uuid')]
    public private(set) ?string $id = null;

    #[ManyToMany(targetEntity: TestUser::class, mappedBy: 'roles')]
    public array $users = [];

    public function __construct(
        #[Column(type: 'string', length: 100)]
        public string $name = ''
    ) {
    }
}

class ManyToManyTest extends TestCase
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
        
        // Create test tables
        $connection->execute('
            CREATE TABLE test_users (
                id TEXT PRIMARY KEY,
                name TEXT NOT NULL
            )
        ');
        
        $connection->execute('
            CREATE TABLE test_roles (
                id TEXT PRIMARY KEY,
                name TEXT NOT NULL
            )
        ');
        
        $connection->execute('
            CREATE TABLE user_roles (
                user_id TEXT NOT NULL,
                role_id TEXT NOT NULL,
                PRIMARY KEY (user_id, role_id),
                FOREIGN KEY (user_id) REFERENCES test_users(id),
                FOREIGN KEY (role_id) REFERENCES test_roles(id)
            )
        ');
    }

    public function testManyToManyPersistence(): void
    {
        // Create entities
        $user = new TestUser('John Doe');
        $adminRole = new TestRole('admin');
        $userRole = new TestRole('user');

        // Add roles to user
        $user->addRole($adminRole);
        $user->addRole($userRole);

        // Persist entities
        $this->entityManager->persist($user);
        $this->entityManager->persist($adminRole);
        $this->entityManager->persist($userRole);
        $this->entityManager->flush();

        // Verify entities were persisted
        $this->assertNotNull($user->id);
        $this->assertNotNull($adminRole->id);
        $this->assertNotNull($userRole->id);

        // Verify associations were created
        $connection = $this->entityManager->getConnection();
        $associations = $connection->fetchAll('SELECT * FROM user_roles WHERE user_id = ?', [$user->id]);

        $this->assertCount(2, $associations);

        $roleIds = array_column($associations, 'role_id');
        $this->assertContains($adminRole->id, $roleIds);
        $this->assertContains($userRole->id, $roleIds);
    }

    public function testManyToManyLoading(): void
    {
        $this->markTestSkipped('Many-to-Many association loading is not yet implemented');

        // Create and persist entities
        $user = new TestUser('Jane Smith');
        $role1 = new TestRole('editor');
        $role2 = new TestRole('reviewer');

        $user->addRole($role1);
        $user->addRole($role2);

        $this->entityManager->persist($user);
        $this->entityManager->persist($role1);
        $this->entityManager->persist($role2);
        $this->entityManager->flush();

        // Clear entity manager to force database fetch
        $this->entityManager->clear();

        // Load user and verify roles
        $foundUser = $this->entityManager->find(TestUser::class, $user->id);
        $this->assertNotNull($foundUser);
        $this->assertEquals('Jane Smith', $foundUser->name);

        $roles = $foundUser->roles;
        $this->assertCount(2, $roles);

        $roleNames = array_map(fn($role) => $role->name, $roles);
        $this->assertContains('editor', $roleNames);
        $this->assertContains('reviewer', $roleNames);
    }

    public function testManyToManyRemoval(): void
    {
        $this->markTestSkipped('Many-to-Many association removal tracking is not yet implemented');

        // Create and persist entities
        $user = new TestUser('Bob Wilson');
        $role1 = new TestRole('manager');
        $role2 = new TestRole('developer');

        $user->addRole($role1);
        $user->addRole($role2);

        $this->entityManager->persist($user);
        $this->entityManager->persist($role1);
        $this->entityManager->persist($role2);
        $this->entityManager->flush();

        // Remove one role
        $user->removeRole($role1);
        $this->entityManager->flush();

        // Verify association was removed
        $connection = $this->entityManager->getConnection();
        $associations = $connection->fetchAll('SELECT * FROM user_roles WHERE user_id = ?', [$user->id]);

        $this->assertCount(1, $associations);
        $this->assertEquals($role2->id, $associations[0]['role_id']);
    }

    public function testBidirectionalManyToMany(): void
    {
        $this->markTestSkipped('Bidirectional Many-to-Many association loading is not yet implemented');

        // Create entities
        $user1 = new TestUser('Alice');
        $user2 = new TestUser('Bob');
        $adminRole = new TestRole('admin');

        // Add role to both users
        $user1->addRole($adminRole);
        $user2->addRole($adminRole);

        // Persist entities
        $this->entityManager->persist($user1);
        $this->entityManager->persist($user2);
        $this->entityManager->persist($adminRole);
        $this->entityManager->flush();

        // Clear and reload
        $this->entityManager->clear();

        $foundRole = $this->entityManager->find(TestRole::class, $adminRole->id);
        $this->assertNotNull($foundRole);

        // Verify inverse side (role -> users)
        $users = $foundRole->users;
        $this->assertCount(2, $users);

        $userNames = array_map(fn($user) => $user->name, $users);
        $this->assertContains('Alice', $userNames);
        $this->assertContains('Bob', $userNames);
    }
}
