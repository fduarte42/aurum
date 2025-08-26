<?php

declare(strict_types=1);

/**
 * Aurum Schema Tool Usage Example
 * 
 * This example demonstrates how to use the Schema Tool to generate
 * database schema code from entity metadata.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Fduarte42\Aurum\Attribute\{Entity, Id, Column, ManyToOne, OneToMany};
use Fduarte42\Aurum\DependencyInjection\ContainerBuilder;
use Fduarte42\Aurum\Schema\SchemaGenerator;
use Fduarte42\Aurum\Metadata\MetadataFactory;
use Fduarte42\Aurum\Connection\ConnectionInterface;
use Ramsey\Uuid\UuidInterface;
use Decimal\Decimal;

echo "🔧 Aurum Schema Tool Usage Example\n";
echo "==================================\n\n";

// Define example entities
#[Entity(table: 'users')]
class User
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?UuidInterface $id = null;

    #[Column(type: 'string', length: 255, unique: true)]
    private string $email;

    #[Column(type: 'string', length: 255)]
    private string $name;

    #[Column(type: 'text', nullable: true)]
    private ?string $bio = null;

    #[Column(type: 'boolean')]
    private bool $active = true;

    #[Column(type: 'datetime')]
    private \DateTimeImmutable $createdAt;

    #[OneToMany(targetEntity: Post::class, mappedBy: 'author')]
    private array $posts = [];

    public function __construct(string $email, string $name)
    {
        $this->email = $email;
        $this->name = $name;
        $this->createdAt = new \DateTimeImmutable();
    }

    // Getters and setters...
    public function getId(): ?UuidInterface { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function getName(): string { return $this->name; }
    public function getBio(): ?string { return $this->bio; }
    public function setBio(?string $bio): void { $this->bio = $bio; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): void { $this->active = $active; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getPosts(): array { return $this->posts; }
    public function addPost(Post $post): void { $this->posts[] = $post; $post->setAuthor($this); }
}

#[Entity(table: 'posts')]
class Post
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?UuidInterface $id = null;

    #[Column(type: 'string', length: 255)]
    private string $title;

    #[Column(type: 'text')]
    private string $content;

    #[Column(type: 'boolean')]
    private bool $published = false;

    #[Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?Decimal $rating = null;

    #[Column(type: 'datetime')]
    private \DateTimeImmutable $createdAt;

    #[Column(type: 'datetime', nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ManyToOne(targetEntity: User::class, inversedBy: 'posts')]
    private ?User $author = null;

    public function __construct(string $title, string $content)
    {
        $this->title = $title;
        $this->content = $content;
        $this->createdAt = new \DateTimeImmutable();
    }

    // Getters and setters...
    public function getId(): ?UuidInterface { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): void { $this->title = $title; }
    public function getContent(): string { return $this->content; }
    public function setContent(string $content): void { $this->content = $content; }
    public function isPublished(): bool { return $this->published; }
    public function setPublished(bool $published): void { 
        $this->published = $published;
        if ($published && !$this->publishedAt) {
            $this->publishedAt = new \DateTimeImmutable();
        }
    }
    public function getRating(): ?Decimal { return $this->rating; }
    public function setRating(?Decimal $rating): void { $this->rating = $rating; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getPublishedAt(): ?\DateTimeImmutable { return $this->publishedAt; }
    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(?User $author): void { $this->author = $author; }
}

#[Entity(table: 'categories')]
class Category
{
    #[Id]
    #[Column(type: 'integer')]
    private ?int $id = null;

    #[Column(type: 'string', length: 100, unique: true)]
    private string $name;

    #[Column(type: 'string', length: 255, unique: true)]
    private string $slug;

    #[Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[Column(type: 'integer')]
    private int $sortOrder = 0;

    public function __construct(string $name, string $slug)
    {
        $this->name = $name;
        $this->slug = $slug;
    }

    // Getters and setters...
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): void { $this->slug = $slug; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): void { $this->description = $description; }
    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $sortOrder): void { $this->sortOrder = $sortOrder; }
}

// Setup configuration
$config = [
    'connection' => [
        'driver' => 'sqlite',
        'path' => ':memory:'
    ]
];

try {
    // Create container and services
    $container = ContainerBuilder::createORM($config);
    $metadataFactory = $container->get(MetadataFactory::class);
    $connection = $container->get(ConnectionInterface::class);
    
    // Create schema generator
    $schemaGenerator = new SchemaGenerator($metadataFactory, $connection);
    
    // Define entities to generate schema for
    $entityClasses = [User::class, Post::class, Category::class];
    
    echo "📊 Generating schema for entities:\n";
    foreach ($entityClasses as $entityClass) {
        echo "  - " . basename($entityClass) . "\n";
    }
    echo "\n";

    // Example 1: Generate SchemaBuilder code
    echo str_repeat('=', 60) . "\n";
    echo "EXAMPLE 1: SchemaBuilder Code Generation\n";
    echo str_repeat('=', 60) . "\n";
    
    $schemaBuilderCode = $schemaGenerator->generateSchemaBuilderCode($entityClasses);
    echo $schemaBuilderCode;
    
    echo "\n💡 This code can be used in migrations or for schema setup.\n";
    echo "💡 Save it to a file with: --output=schema-builder.php\n\n";

    // Example 2: Generate SQL DDL for SQLite
    echo str_repeat('=', 60) . "\n";
    echo "EXAMPLE 2: SQL DDL Generation (SQLite)\n";
    echo str_repeat('=', 60) . "\n";
    
    $sqlDdl = $schemaGenerator->generateSqlDdl($entityClasses);
    echo $sqlDdl;
    
    echo "\n💡 This SQL can be executed directly on SQLite database.\n";
    echo "💡 Save it to a file with: --output=schema.sql\n\n";

    echo "\n" . str_repeat('=', 60) . "\n";
    echo "CLI USAGE EXAMPLES\n";
    echo str_repeat('=', 60) . "\n";
    echo "# Generate SchemaBuilder code for specific entities:\n";
    echo "php bin/aurum-cli.php schema generate --entities=\"User,Post,Category\" --format=schema-builder\n\n";

    echo "# Generate SQL DDL and save to file:\n";
    echo "php bin/aurum-cli.php schema generate --entities=\"User,Post\" --format=sql --output=schema.sql\n\n";

    echo "# Generate both formats:\n";
    echo "php bin/aurum-cli.php schema generate --entities=\"User,Post,Category\" --format=both\n\n";

    echo "# Generate schema for all entities in a namespace:\n";
    echo "php bin/aurum-cli.php schema generate --namespace=\"App\\Entity\" --format=sql\n\n";

    echo "# Auto-discover and generate schema for all entities:\n";
    echo "php bin/aurum-cli.php schema generate --format=both\n\n";

    echo "# Show help:\n";
    echo "php bin/aurum-cli.php schema generate --help\n\n";

    echo "✅ Schema Tool examples completed successfully!\n";
    echo "\n💡 Key Features Demonstrated:\n";
    echo "  - SchemaBuilder code generation (Laravel-style fluent syntax)\n";
    echo "  - SQL DDL generation for SQLite and MariaDB\n";
    echo "  - Support for various column types (UUID, string, text, decimal, datetime, etc.)\n";
    echo "  - Automatic handling of primary keys, unique constraints, and indexes\n";
    echo "  - Platform-specific SQL generation\n";
    echo "  - CLI tool for easy usage\n\n";

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
