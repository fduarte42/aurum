# Entity Management

This guide covers how to define entities, configure relationships, and use attributes effectively in Aurum ORM.

## Entity Basics

### Defining an Entity

Entities in Aurum are regular PHP classes marked with the `#[Entity]` attribute:

```php
<?php

use Fduarte42\Aurum\Attribute\{Entity, Id, Column};

#[Entity(table: 'users')]
class User
{
    #[Id]
    #[Column(type: 'uuid')]
    public private(set) ?string $id = null;

    public function __construct(
        #[Column(type: 'string', length: 255, unique: true)]
        public string $email,

        #[Column(type: 'string', length: 255)]
        public string $name,

        #[Column(type: 'datetime')]
        public \DateTime $createdAt = new \DateTime()
    ) {
    }
}
```

### Entity Attributes

#### `#[Entity]`

Marks a class as an entity and configures table mapping:

```php
#[Entity(table: 'users')]           // Custom table name
#[Entity]                           // Uses class name (snake_case)
```

#### `#[Id]`

Marks a field as the primary key:

```php
#[Id]
#[Column(type: 'uuid')]
private ?string $id = null;

// Composite primary key
#[Id]
#[Column(type: 'string')]
private string $userId;

#[Id]
#[Column(type: 'string')]
private string $roleId;
```

#### `#[Column]`

Configures column mapping with various options:

```php
#[Column(type: 'string', length: 255)]
private string $name;

#[Column(type: 'string', length: 255, unique: true, nullable: false)]
private string $email;

#[Column(type: 'text', nullable: true)]
private ?string $description = null;

#[Column(type: 'decimal', precision: 10, scale: 2)]
private string $price;

#[Column(type: 'boolean', default: false)]
private bool $active = false;

#[Column(name: 'created_at', type: 'datetime')]
private \DateTime $createdAt;
```

## Column Types

### Basic Types

```php
// String types
#[Column(type: 'string', length: 255)]
private string $name;

#[Column(type: 'text')]
private string $content;

// Numeric types
#[Column(type: 'integer')]
private int $count;

#[Column(type: 'float')]
private float $rating;

#[Column(type: 'decimal', precision: 10, scale: 2)]
private string $price;

// Boolean
#[Column(type: 'boolean')]
private bool $active;
```

### Date and Time Types

```php
#[Column(type: 'datetime')]
private \DateTime $createdAt;

#[Column(type: 'date')]
private \DateTime $birthDate;

#[Column(type: 'time')]
private \DateTime $startTime;

#[Column(type: 'datetime_with_timezone')]
private \DateTimeImmutable $scheduledAt;
```

### Special Types

```php
// UUID (requires ramsey/uuid)
#[Column(type: 'uuid')]
private string $id;

// JSON (automatic serialization)
#[Column(type: 'json')]
private array $metadata;

// Custom objects as JSON
#[Column(type: 'json')]
private UserPreferences $preferences;
```

## Relationships

### Many-to-One (Belongs To)

```php
#[Entity(table: 'posts')]
class Post
{
    #[Id]
    #[Column(type: 'uuid')]
    public private(set) ?string $id = null;

    #[ManyToOne(targetEntity: User::class)]
    #[JoinColumn(name: 'author_id', referencedColumnName: 'id')]
    public User $author;

    public function __construct(
        #[Column(type: 'string', length: 255)]
        public string $title,

        User $author
    ) {
        $this->author = $author;
    }
}
```

### One-to-Many (Has Many)

```php
#[Entity(table: 'users')]
class User
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?string $id = null;

    #[OneToMany(targetEntity: Post::class, mappedBy: 'author')]
    private array $posts = [];

    public function getPosts(): array
    {
        return $this->posts;
    }

    public function addPost(Post $post): void
    {
        $this->posts[] = $post;
    }
}
```

### Many-to-Many

Many-to-Many relationships allow entities to be associated with multiple instances of another entity type through a junction table.

#### Basic Many-to-Many

```php
#[Entity(table: 'users')]
class User
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?string $id = null;

    #[Column(type: 'string', length: 255)]
    private string $name;

    #[ManyToMany(targetEntity: Role::class)]
    #[JoinTable(
        name: 'user_roles',
        joinColumns: [new JoinColumn(name: 'user_id', referencedColumnName: 'id')],
        inverseJoinColumns: [new JoinColumn(name: 'role_id', referencedColumnName: 'id')]
    )]
    private array $roles = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function addRole(Role $role): void
    {
        if (!in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }
    }

    public function removeRole(Role $role): void
    {
        $key = array_search($role, $this->roles, true);
        if ($key !== false) {
            unset($this->roles[$key]);
            $this->roles = array_values($this->roles);
        }
    }
}

#[Entity(table: 'roles')]
class Role
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?string $id = null;

    #[Column(type: 'string', length: 100)]
    private string $name;

    #[ManyToMany(targetEntity: User::class, mappedBy: 'roles')]
    private array $users = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getUsers(): array
    {
        return $this->users;
    }
}
```

#### Many-to-Many with Cascade Operations

```php
#[Entity(table: 'articles')]
class Article
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?string $id = null;

    #[Column(type: 'string', length: 255)]
    private string $title;

    #[ManyToMany(targetEntity: Tag::class, cascade: ['persist', 'remove'])]
    #[JoinTable(name: 'article_tags')]
    private array $tags = [];

    public function addTag(Tag $tag): void
    {
        if (!in_array($tag, $this->tags, true)) {
            $this->tags[] = $tag;
        }
    }
}

#[Entity(table: 'tags')]
class Tag
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?string $id = null;

    #[Column(type: 'string', length: 100)]
    private string $name;

    #[ManyToMany(targetEntity: Article::class, mappedBy: 'tags')]
    private array $articles = [];
}
```

#### Working with Many-to-Many Relationships

```php
// Create entities
$user = new User('John Doe');
$adminRole = new Role('admin');
$userRole = new Role('user');

// Add roles to user
$user->addRole($adminRole);
$user->addRole($userRole);

// Persist entities
$entityManager->persist($user);
$entityManager->persist($adminRole);
$entityManager->persist($userRole);
$entityManager->flush();

// Load user with roles
$foundUser = $entityManager->find(User::class, $user->getId());
$roles = $foundUser->getRoles();

// Remove a role
$foundUser->removeRole($adminRole);
$entityManager->flush();
```

#### Querying Many-to-Many Relationships

The QueryBuilder provides automatic join resolution for Many-to-Many relationships, making complex queries simple and intuitive.

##### Basic Many-to-Many Queries

**Find Users with Specific Roles:**
```php
// Owning side query - automatic junction table joins
$adminUsers = $entityManager->createQueryBuilder('u')
    ->select('u', 'r')
    ->from(User::class, 'u')
    ->innerJoin('u.roles', 'r')  // Automatic: user_roles junction table
    ->where('r.name = :role')
    ->setParameter('role', 'admin')
    ->getResult();
```

**Find Roles Assigned to Active Users:**
```php
// Inverse side query - automatic junction table joins
$activeRoles = $entityManager->createQueryBuilder('r')
    ->select('r', 'u')
    ->from(Role::class, 'r')
    ->innerJoin('r.users', 'u')  // Automatic: user_roles junction table
    ->where('u.active = :active')
    ->setParameter('active', true)
    ->getResult();
```

##### Advanced Many-to-Many Queries

**Multiple Role Filtering:**
```php
$users = $entityManager->createQueryBuilder('u')
    ->select('u')
    ->from(User::class, 'u')
    ->innerJoin('u.roles', 'r')
    ->where('r.name IN (:roles)')
    ->setParameter('roles', ['admin', 'moderator', 'editor'])
    ->getResult();
```

**Complex Multi-Join Queries:**
```php
// Find users with admin role who have published posts
$result = $entityManager->createQueryBuilder('u')
    ->select('u', 'r', 'p')
    ->from(User::class, 'u')
    ->innerJoin('u.roles', 'r')      // Many-to-Many join
    ->leftJoin('u.posts', 'p')       // OneToMany join
    ->where('r.name = :role')
    ->andWhere('p.published = :published')
    ->setParameter('role', 'admin')
    ->setParameter('published', true)
    ->orderBy('u.name', 'ASC')
    ->getResult();
```

**Existence Queries:**
```php
// Find users who have at least one role
$usersWithRoles = $entityManager->createQueryBuilder('u')
    ->select('u')
    ->from(User::class, 'u')
    ->where('EXISTS (
        SELECT 1 FROM user_roles ur
        WHERE ur.user_id = u.id
    )')
    ->getResult();

// Or using automatic joins (simpler)
$usersWithRoles = $entityManager->createQueryBuilder('u')
    ->select('u')
    ->from(User::class, 'u')
    ->innerJoin('u.roles', 'r')
    ->getResult();
```

**Counting Related Entities:**
```php
// Count roles per user
$userRoleCounts = $entityManager->createQueryBuilder('u')
    ->select('u.name', 'COUNT(r.id) as roleCount')
    ->from(User::class, 'u')
    ->leftJoin('u.roles', 'r')
    ->groupBy('u.id', 'u.name')
    ->getResult();
```

##### Performance Tips for Many-to-Many Queries

**Efficient Column Selection:**
```php
// Good: Select only needed columns
$qb->select('u.name', 'r.name')
   ->from(User::class, 'u')
   ->innerJoin('u.roles', 'r');

// Avoid: Selecting full entities when not needed
$qb->select('u', 'r')  // Loads all columns
   ->from(User::class, 'u')
   ->innerJoin('u.roles', 'r');
```

**Filtering Strategies:**
```php
// Good: Filter on main entity first
$qb->from(User::class, 'u')
   ->innerJoin('u.roles', 'r')
   ->where('u.active = :active')     // Filter main table first
   ->andWhere('r.name = :role');     // Then filter joined table

// Good: Use IN clauses for multiple values
$qb->where('r.name IN (:roles)')
   ->setParameter('roles', ['admin', 'editor']);
```

### One-to-One

```php
#[Entity(table: 'users')]
class User
{
    #[OneToOne(targetEntity: UserProfile::class, mappedBy: 'user')]
    private ?UserProfile $profile = null;

    public function getProfile(): ?UserProfile
    {
        return $this->profile;
    }
}

#[Entity(table: 'user_profiles')]
class UserProfile
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?string $id = null;

    #[OneToOne(targetEntity: User::class)]
    #[JoinColumn(name: 'user_id', referencedColumnName: 'id')]
    private User $user;

    #[Column(type: 'text', nullable: true)]
    private ?string $bio = null;

    public function __construct(User $user)
    {
        $this->user = $user;
    }
}
```

## Advanced Entity Features

### Nullable Fields

```php
#[Column(type: 'string', length: 255, nullable: true)]
private ?string $middleName = null;

#[Column(type: 'datetime', nullable: true)]
private ?\DateTime $lastLoginAt = null;
```

### Default Values

```php
#[Column(type: 'boolean', default: true)]
private bool $active = true;

#[Column(type: 'integer', default: 0)]
private int $loginCount = 0;

#[Column(type: 'string', length: 50, default: 'pending')]
private string $status = 'pending';
```

### Unique Constraints

```php
// Single column unique
#[Column(type: 'string', length: 255, unique: true)]
private string $email;

// Composite unique constraint (future feature)
#[UniqueConstraint(name: 'user_email_domain', columns: ['email', 'domain'])]
```

### Indexes

```php
// Single column index
#[Column(type: 'string', length: 255)]
#[Index]
private string $lastName;

// Composite index (future feature)
#[Index(name: 'user_name_idx', columns: ['firstName', 'lastName'])]
```

## Entity Lifecycle

### Creating Entities

```php
// Create new entity
$user = new User('john@example.com', 'John Doe');

// Persist to database
$entityManager->persist($user);
$entityManager->flush();

// ID is automatically generated
echo $user->getId(); // UUID string
```

### Loading Entities

```php
// Find by primary key
$user = $entityManager->find(User::class, $userId);

// Find by criteria
$repository = $entityManager->getRepository(User::class);
$user = $repository->findOneBy(['email' => 'john@example.com']);

// Find multiple
$users = $repository->findBy(['active' => true], ['name' => 'ASC']);
```

### Updating Entities

```php
// Load entity
$user = $entityManager->find(User::class, $userId);

// Modify properties
$user->setName('John Smith');
$user->setEmail('john.smith@example.com');

// Save changes
$entityManager->flush(); // No need to persist again
```

### Deleting Entities

```php
// Load entity
$user = $entityManager->find(User::class, $userId);

// Mark for deletion
$entityManager->remove($user);

// Execute deletion
$entityManager->flush();
```

## Working with Relationships

### Loading Related Data

```php
// Lazy loading (default)
$user = $entityManager->find(User::class, $userId);
$posts = $user->getPosts(); // Database query happens here

// Eager loading with joins
$users = $entityManager->createQueryBuilder('u')
    ->select('u', 'p')
    ->from(User::class, 'u')
    ->leftJoin('u.posts', 'p')
    ->getResult();
```

### Managing Relationships

```php
// Add relationship
$user = $entityManager->find(User::class, $userId);
$post = new Post('New Post', $user);

$entityManager->persist($post);
$entityManager->flush();

// Remove relationship
$post = $entityManager->find(Post::class, $postId);
$entityManager->remove($post);
$entityManager->flush();
```

### Bidirectional Relationships

```php
class User
{
    #[OneToMany(targetEntity: Post::class, mappedBy: 'author')]
    private array $posts = [];

    public function addPost(Post $post): void
    {
        $this->posts[] = $post;
        $post->setAuthor($this); // Maintain both sides
    }
}

class Post
{
    #[ManyToOne(targetEntity: User::class, inversedBy: 'posts')]
    private User $author;

    public function setAuthor(User $author): void
    {
        $this->author = $author;
    }
}
```

## Custom Repository Classes

### Defining Custom Repositories

```php
use Fduarte42\Aurum\Repository\Repository;

class UserRepository extends Repository
{
    public function findActiveUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.active = :active')
            ->setParameter('active', true)
            ->orderBy('u.name', 'ASC')
            ->getResult();
    }

    public function findByEmailDomain(string $domain): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.email LIKE :domain')
            ->setParameter('domain', '%@' . $domain)
            ->getResult();
    }

    public function getUserStats(): array
    {
        $qb = $this->createQueryBuilder('u');
        
        return [
            'total' => $this->count([]),
            'active' => $this->count(['active' => true]),
            'recent' => $this->findBy([], ['createdAt' => 'DESC'], 10)
        ];
    }
}
```

### Using Custom Repositories

```php
// Get custom repository
$userRepository = $entityManager->getRepository(User::class);

// Use custom methods
$activeUsers = $userRepository->findActiveUsers();
$gmailUsers = $userRepository->findByEmailDomain('gmail.com');
$stats = $userRepository->getUserStats();
```

## Best Practices

### 1. Entity Design

```php
// Good - Clear, focused entity
#[Entity(table: 'users')]
class User
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?string $id = null;

    #[Column(type: 'string', length: 255, unique: true)]
    private string $email;

    #[Column(type: 'string', length: 255)]
    private string $name;

    // Constructor with required fields
    public function __construct(string $email, string $name)
    {
        $this->email = $email;
        $this->name = $name;
    }

    // Immutable ID
    public function getId(): ?string
    {
        return $this->id;
    }

    // Controlled access to properties
    public function getEmail(): string
    {
        return $this->email;
    }

    public function changeEmail(string $email): void
    {
        // Add validation logic here
        $this->email = $email;
    }
}
```

### 2. Relationship Management

```php
// Good - Helper methods for relationships
public function addPost(Post $post): void
{
    if (!in_array($post, $this->posts, true)) {
        $this->posts[] = $post;
        $post->setAuthor($this);
    }
}

public function removePost(Post $post): void
{
    $key = array_search($post, $this->posts, true);
    if ($key !== false) {
        unset($this->posts[$key]);
        $post->setAuthor(null);
    }
}
```

### 3. Value Objects

```php
// Use value objects for complex data
#[Column(type: 'json')]
private Address $address;

class Address
{
    public function __construct(
        public readonly string $street,
        public readonly string $city,
        public readonly string $zipCode,
        public readonly string $country
    ) {}

    public function getFullAddress(): string
    {
        return "{$this->street}, {$this->city} {$this->zipCode}, {$this->country}";
    }
}
```

### 4. Validation

```php
public function changeEmail(string $email): void
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new \InvalidArgumentException('Invalid email format');
    }
    
    $this->email = $email;
}

public function setAge(int $age): void
{
    if ($age < 0 || $age > 150) {
        throw new \InvalidArgumentException('Age must be between 0 and 150');
    }
    
    $this->age = $age;
}
```

This entity management system provides a robust foundation for modeling your domain while maintaining clean, maintainable code.
