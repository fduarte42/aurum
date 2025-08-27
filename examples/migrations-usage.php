<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Fduarte42\Aurum\Attribute\{Entity, Id, Column, ManyToOne, OneToMany};
use Fduarte42\Aurum\DependencyInjection\ContainerBuilder;
use Fduarte42\Aurum\Migration\MigrationService;
use Ramsey\Uuid\UuidInterface;

// ✅ Define entities first
#[Entity(table: 'users')]
class User
{
    #[Id]
    #[Column(type: 'uuid')]
    public private(set) ?UuidInterface $id = null;

    #[OneToMany(targetEntity: Post::class, mappedBy: 'author')]
    public array $posts = [];

    public function __construct(
        #[Column(type: 'string', length: 255, unique: true)]
        public string $email,

        #[Column(type: 'string', length: 255)]
        public string $name,

        #[Column(type: 'datetime')]
        public \DateTimeImmutable $createdAt = new \DateTimeImmutable()
    ) {
    }
}

#[Entity(table: 'posts')]
class Post
{
    #[Id]
    #[Column(type: 'uuid')]
    public private(set) ?UuidInterface $id = null;

    #[ManyToOne(targetEntity: User::class, inversedBy: 'posts')]
    public ?User $author = null;

    public function __construct(
        #[Column(type: 'string', length: 255)]
        public string $title,

        #[Column(type: 'text')]
        public string $content,

        #[Column(type: 'boolean')]
        public bool $published = false,

        #[Column(type: 'datetime')]
        public \DateTimeImmutable $createdAt = new \DateTimeImmutable()
    ) {
    }
}

echo "🚀 Aurum Migration System Demo\n";
echo "==============================\n\n";

// Setup with migrations support - using in-memory database
$config = [
    'connection' => [
        'driver' => 'sqlite',
        'path' => ':memory:'  // In-memory database - no files created!
    ],
    'migrations' => [
        'directory' => __DIR__ . '/migrations',
        'namespace' => 'DemoMigrations'
    ]
];

// Create container with migration support
$container = ContainerBuilder::createORM($config);
$entityManager = $container->get(\Fduarte42\Aurum\EntityManagerInterface::class);
$migrationService = $container->get(MigrationService::class);

// Enable verbose output
$migrationService->setVerbose(true);

echo "📊 Migration Status:\n";
$status = $migrationService->status();
echo "Current version: " . ($status['current_version'] ?? 'none') . "\n";
echo "Executed migrations: {$status['executed_migrations']}\n";
echo "Pending migrations: {$status['pending_migrations']}\n";
echo "Total migrations: {$status['total_migrations']}\n\n";

// Check if we need to create initial migrations
if ($status['total_migrations'] === 0) {
    echo "🔧 Creating initial migrations...\n";
    
    // Generate migration for users table
    $version1 = $migrationService->generate('Create users table');
    echo "Generated migration: {$version1}\n";

    // Add small delay to ensure different timestamps
    sleep(1);

    // Generate migration for posts table
    $version2 = $migrationService->generate('Create posts table');
    echo "Generated migration: {$version2}\n";
    
    // Modify the generated migrations to match our entities
    modifyUsersMigration($config['migrations']['directory'], $version1);
    modifyPostsMigration($config['migrations']['directory'], $version2);
    
    echo "\n";
}

echo "🏃 Running migrations...\n";
$migrationService->migrate();

echo "\n📋 Migration List:\n";
$migrations = $migrationService->list();
foreach ($migrations as $migration) {
    $status = $migration['executed'] ? '✅' : '⏳';
    echo "{$status} {$migration['version']}: {$migration['description']}\n";
}

echo "\n💾 Testing the database...\n";

// Create some test data
$user = new User('john@example.com', 'John Doe');
$post = new Post('Hello World', 'This is my first post!');
$post->setAuthor($user);
$post->setPublished(true);

$entityManager->beginTransaction();
try {
    $entityManager->persist($user);
    $entityManager->persist($post);
    $entityManager->flush();
    $entityManager->commit();
} catch (\Exception $e) {
    $entityManager->rollback();
    throw $e;
}

echo "Created user: {$user->getName()} ({$user->getEmail()})\n";
echo "Created post: {$post->getTitle()}\n";

// Query the data
$userRepo = $entityManager->getRepository(User::class);
$users = $userRepo->findAll();

echo "\n👥 Users in database:\n";
foreach ($users as $user) {
    echo "- {$user->getName()} ({$user->getEmail()})\n";
    foreach ($user->getPosts() as $post) {
        echo "  📝 {$post->getTitle()}\n";
    }
}

echo "\n🔄 Testing rollback...\n";
echo "Rolling back last migration...\n";
$migrationService->rollback();

echo "\n📊 Final Migration Status:\n";
$status = $migrationService->status();
echo "Current version: " . ($status['current_version'] ?? 'none') . "\n";
echo "Executed migrations: {$status['executed_migrations']}\n";
echo "Pending migrations: {$status['pending_migrations']}\n";

echo "\n✨ Demo completed!\n";

// Helper functions to modify generated migrations
function modifyUsersMigration(string $migrationsDir, string $version): void
{
    $filePath = $migrationsDir . "/Version{$version}.php";
    $content = file_get_contents($filePath);
    
    $upMethod = '
    public function up(ConnectionInterface $connection): void
    {
        $this->schemaBuilder->createTable(\'users\')
            ->uuidPrimaryKey()
            ->string(\'email\', [\'length\' => 255, \'not_null\' => true])
            ->string(\'name\', [\'length\' => 255, \'not_null\' => true])
            ->datetime(\'created_at\', [\'not_null\' => true])
            ->unique([\'email\'])
            ->create();
    }';
    
    $downMethod = '
    public function down(ConnectionInterface $connection): void
    {
        $this->schemaBuilder->dropTable(\'users\');
    }';
    
    $content = preg_replace('/public function up\(.*?\{.*?\}/s', $upMethod, $content);
    $content = preg_replace('/public function down\(.*?\{.*?\}/s', $downMethod, $content);
    
    file_put_contents($filePath, $content);
}

function modifyPostsMigration(string $migrationsDir, string $version): void
{
    $filePath = $migrationsDir . "/Version{$version}.php";
    $content = file_get_contents($filePath);
    
    $upMethod = '
    public function up(ConnectionInterface $connection): void
    {
        $this->schemaBuilder->createTable(\'posts\')
            ->uuidPrimaryKey()
            ->string(\'title\', [\'length\' => 255, \'not_null\' => true])
            ->text(\'content\', [\'not_null\' => true])
            ->boolean(\'published\', [\'default\' => false, \'not_null\' => true])
            ->datetime(\'created_at\', [\'not_null\' => true])
            ->uuid(\'author_id\', [\'nullable\' => true])
            ->foreign([\'author_id\'], \'users\', [\'id\'], [\'on_delete\' => \'SET NULL\'])
            ->index([\'published\'])
            ->index([\'created_at\'])
            ->create();
    }';
    
    $downMethod = '
    public function down(ConnectionInterface $connection): void
    {
        $this->schemaBuilder->dropTable(\'posts\');
    }';
    
    $content = preg_replace('/public function up\(.*?\{.*?\}/s', $upMethod, $content);
    $content = preg_replace('/public function down\(.*?\{.*?\}/s', $downMethod, $content);
    
    file_put_contents($filePath, $content);
}
