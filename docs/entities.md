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
    private ?string $id = null;

    #[Column(type: 'string', length: 255, unique: true)]
    private string $email;

    #[Column(type: 'string', length: 255)]
    private string $name;

    #[Column(type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct(string $email, string $name)
    {
        $this->email = $email;
        $this->name = $name;
        $this->createdAt = new \DateTime();
    }

    // Getters and setters...
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
    private ?string $id = null;

    #[Column(type: 'string', length: 255)]
    private string $title;

    #[ManyToOne(targetEntity: User::class)]
    #[JoinColumn(name: 'author_id', referencedColumnName: 'id')]
    private User $author;

    public function __construct(string $title, User $author)
    {
        $this->title = $title;
        $this->author = $author;
    }

    public function getAuthor(): User
    {
        return $this->author;
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

```php
#[Entity(table: 'users')]
class User
{
    #[ManyToMany(targetEntity: Role::class)]
    #[JoinTable(
        name: 'user_roles',
        joinColumns: [new JoinColumn(name: 'user_id', referencedColumnName: 'id')],
        inverseJoinColumns: [new JoinColumn(name: 'role_id', referencedColumnName: 'id')]
    )]
    private array $roles = [];

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
}
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
