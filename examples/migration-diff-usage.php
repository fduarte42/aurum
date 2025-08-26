<?php

declare(strict_types=1);

/**
 * Aurum Migration Diff Usage Example
 * 
 * This example demonstrates how to use the Migration Diff system to compare
 * current database schema with target schema and generate migration code.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Fduarte42\Aurum\Attribute\{Entity, Id, Column};
use Fduarte42\Aurum\DependencyInjection\ContainerBuilder;
use Fduarte42\Aurum\Schema\{SchemaDiffer, SchemaIntrospector};
use Fduarte42\Aurum\Metadata\MetadataFactory;
use Fduarte42\Aurum\Connection\ConnectionInterface;
use Ramsey\Uuid\UuidInterface;

echo "🔧 Aurum Migration Diff Usage Example\n";
echo "=====================================\n\n";

// Define initial entity (what we have in the database)
#[Entity(table: 'users')]
class UserV1
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?UuidInterface $id = null;

    #[Column(type: 'string', length: 255, unique: true)]
    private string $email;

    #[Column(type: 'string', length: 255)]
    private string $name;

    #[Column(type: 'datetime')]
    private \DateTimeImmutable $createdAt;

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
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}

// Define updated entity (what we want the database to look like)
#[Entity(table: 'users')]
class UserV2
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?UuidInterface $id = null;

    #[Column(type: 'string', length: 255, unique: true)]
    private string $email;

    #[Column(type: 'string', length: 100)] // Changed length from 255 to 100
    private string $firstName; // Renamed from 'name' to 'firstName'

    #[Column(type: 'string', length: 100)] // New field
    private string $lastName;

    #[Column(type: 'text', nullable: true)] // New field
    private ?string $bio = null;

    #[Column(type: 'boolean')] // New field
    private bool $active = true;

    #[Column(type: 'datetime')]
    private \DateTimeImmutable $createdAt;

    #[Column(type: 'datetime', nullable: true)] // New field
    private ?\DateTimeImmutable $lastLoginAt = null;

    public function __construct(string $email, string $firstName, string $lastName)
    {
        $this->email = $email;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->createdAt = new \DateTimeImmutable();
    }

    // Getters and setters...
    public function getId(): ?UuidInterface { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function getFirstName(): string { return $this->firstName; }
    public function setFirstName(string $firstName): void { $this->firstName = $firstName; }
    public function getLastName(): string { return $this->lastName; }
    public function setLastName(string $lastName): void { $this->lastName = $lastName; }
    public function getBio(): ?string { return $this->bio; }
    public function setBio(?string $bio): void { $this->bio = $bio; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): void { $this->active = $active; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getLastLoginAt(): ?\DateTimeImmutable { return $this->lastLoginAt; }
    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): void { $this->lastLoginAt = $lastLoginAt; }
}

// Setup configuration
$config = [
    'connection' => [
        'driver' => 'sqlite',
        'path' => 'example_migration_diff.db' // Use a real file so we can persist data
    ]
];

try {
    // Create container and services
    $container = ContainerBuilder::createORM($config);
    $metadataFactory = $container->get(MetadataFactory::class);
    $connection = $container->get(ConnectionInterface::class);
    
    // Create schema introspector and differ
    $introspector = new SchemaIntrospector($connection);
    $schemaDiffer = new SchemaDiffer($metadataFactory, $introspector, $connection);
    
    echo "📊 Setting up example scenario...\n\n";
    
    // Step 1: Create initial database schema (UserV1)
    echo "Step 1: Creating initial database schema (UserV1)\n";
    echo str_repeat('-', 50) . "\n";
    
    // Drop table if it exists
    try {
        $connection->execute('DROP TABLE IF EXISTS users');
    } catch (\Exception $e) {
        // Ignore if table doesn't exist
    }
    
    // Create initial table structure
    $connection->execute("
        CREATE TABLE users (
            id TEXT PRIMARY KEY,
            email TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            created_at TEXT NOT NULL
        )
    ");
    
    // Create unique index on email
    $connection->execute("
        CREATE UNIQUE INDEX idx_users_email ON users(email)
    ");
    
    echo "✅ Initial schema created with columns: id, email, name, created_at\n\n";
    
    // Step 2: Show current database structure
    echo "Step 2: Current database structure\n";
    echo str_repeat('-', 50) . "\n";
    
    $currentTables = $introspector->getTables();
    echo "Tables: " . implode(', ', $currentTables) . "\n";
    
    if (in_array('users', $currentTables)) {
        $userColumns = $introspector->getTableColumns('users');
        echo "Users table columns:\n";
        foreach ($userColumns as $column) {
            echo "  - {$column['name']} ({$column['type']}) " . 
                 ($column['nullable'] ? 'NULL' : 'NOT NULL') . 
                 ($column['primary_key'] ? ' PRIMARY KEY' : '') . "\n";
        }
        
        $userIndexes = $introspector->getTableIndexes('users');
        if (!empty($userIndexes)) {
            echo "Users table indexes:\n";
            foreach ($userIndexes as $index) {
                echo "  - {$index['name']} on [" . implode(', ', $index['columns']) . "]" . 
                     ($index['unique'] ? ' UNIQUE' : '') . "\n";
            }
        }
    }
    echo "\n";
    
    // Step 3: Generate migration diff to UserV2
    echo "Step 3: Generating migration diff (UserV1 -> UserV2)\n";
    echo str_repeat('-', 50) . "\n";
    
    $diff = $schemaDiffer->generateMigrationDiff([UserV2::class]);
    
    echo "UP MIGRATION (Current -> Target):\n";
    echo str_repeat('=', 40) . "\n";
    if (!empty(trim($diff['up']))) {
        echo "    public function up(ConnectionInterface \$connection): void\n";
        echo "    {\n";
        echo $diff['up'];
        echo "    }\n";
    } else {
        echo "    // No changes needed\n";
    }
    
    echo "\nDOWN MIGRATION (Target -> Current):\n";
    echo str_repeat('=', 40) . "\n";
    if (!empty(trim($diff['down']))) {
        echo "    public function down(ConnectionInterface \$connection): void\n";
        echo "    {\n";
        echo $diff['down'];
        echo "    }\n";
    } else {
        echo "    // No changes needed\n";
    }
    
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "CLI USAGE EXAMPLES\n";
    echo str_repeat('=', 60) . "\n";
    echo "# Preview migration diff without creating files:\n";
    echo "php bin/aurum-cli.php migration diff --entities=\"User\" --preview\n\n";

    echo "# Generate migration file:\n";
    echo "php bin/aurum-cli.php migration diff --entities=\"User\" --name=\"UpdateUserSchema\"\n\n";

    echo "# Generate diff for all entities in a namespace:\n";
    echo "php bin/aurum-cli.php migration diff --namespace=\"App\\Entity\" --preview\n\n";

    echo "# Auto-discover and generate migration for all entities:\n";
    echo "php bin/aurum-cli.php migration diff --name=\"UpdateAllEntities\"\n\n";

    echo "# Save to custom file:\n";
    echo "php bin/aurum-cli.php migration diff --entities=\"User\" --output=my-migration.php\n\n";

    echo "# Show help:\n";
    echo "php bin/aurum-cli.php migration diff --help\n\n";
    
    echo "✅ Migration Diff examples completed successfully!\n";
    echo "\n💡 Key Features Demonstrated:\n";
    echo "  - Database schema introspection (reading current structure)\n";
    echo "  - Schema comparison between current DB and target entities\n";
    echo "  - Generation of up/down migration methods using SchemaBuilder syntax\n";
    echo "  - Detection of added/removed/modified columns\n";
    echo "  - Detection of added/removed indexes\n";
    echo "  - CLI tool for easy migration generation\n\n";
    
    echo "💡 Changes detected in this example:\n";
    echo "  - Column 'name' needs to be renamed to 'firstName' and resized\n";
    echo "  - New columns: 'lastName', 'bio', 'active', 'lastLoginAt'\n";
    echo "  - All changes are handled with proper SchemaBuilder syntax\n\n";
    
    // Clean up
    echo "🧹 Cleaning up example database file...\n";
    if (file_exists('example_migration_diff.db')) {
        unlink('example_migration_diff.db');
        echo "✅ Cleanup completed.\n";
    }

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    
    // Clean up on error
    if (file_exists('example_migration_diff.db')) {
        unlink('example_migration_diff.db');
    }
    
    exit(1);
}
