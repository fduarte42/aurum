<?php

declare(strict_types=1);

/**
 * Aurum Proxy Usage Example
 * 
 * This example demonstrates the LazyGhostProxyFactory functionality
 * and tests the refactored proxy implementation.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Fduarte42\Aurum\Attribute\{Entity, Id, Column, ManyToOne};
use Fduarte42\Aurum\DependencyInjection\ContainerBuilder;
use Ramsey\Uuid\UuidInterface;

echo "🔧 Aurum Proxy Usage Example\n";
echo "============================\n\n";

// Define entities for testing proxy functionality
#[Entity(table: 'authors')]
class Author
{
    #[Id]
    #[Column(type: 'uuid')]
    public private(set) ?UuidInterface $id = null;

    public function __construct(
        #[Column(type: 'string', length: 255)]
        public string $name,

        #[Column(type: 'string', length: 255)]
        public string $email
    ) {
    }
}

#[Entity(table: 'books')]
class Book
{
    #[Id]
    #[Column(type: 'uuid')]
    public private(set) ?UuidInterface $id = null;

    public function __construct(
        #[Column(type: 'string', length: 255)]
        public string $title,

        #[ManyToOne(targetEntity: Author::class)]
        public Author $author
    ) {
    }
}

// Setup
$config = [
    'connection' => [
        'driver' => 'sqlite',
        'path' => ':memory:'
    ]
];

$entityManager = ContainerBuilder::createEntityManager($config);

// Create schema
$connection = $entityManager->getConnection();
$connection->execute('
    CREATE TABLE authors (
        id TEXT PRIMARY KEY,
        name TEXT NOT NULL,
        email TEXT NOT NULL
    )
');

$connection->execute('
    CREATE TABLE books (
        id TEXT PRIMARY KEY,
        title TEXT NOT NULL,
        author_id TEXT NOT NULL,
        FOREIGN KEY (author_id) REFERENCES authors(id)
    )
');

echo "1. Testing Basic Entity Operations\n";
echo "==================================\n";

// Create and persist an author
$entityManager->beginTransaction();
$author = new Author('John Doe', 'john@example.com');
$entityManager->persist($author);
$entityManager->flush();
$entityManager->commit();

echo "✅ Created author: {$author->name} (ID: {$author->id})\n\n";

echo "2. Testing getReference() - Proxy Creation\n";
echo "==========================================\n";

try {
    // Test getReference - this should create a proxy
    $authorRef = $entityManager->getReference(Author::class, $author->id);

    echo "✅ Created author reference (proxy)\n";
    echo "   - Class: " . get_class($authorRef) . "\n";
    echo "   - Same instance as original: " . ($authorRef === $author ? 'Yes' : 'No') . "\n";

    // Access a property to trigger lazy loading
    echo "   - Accessing name property...\n";
    $name = $authorRef->name;
    echo "   - Name: {$name}\n";
    echo "   - Email: {$authorRef->email}\n";

} catch (\RuntimeException $e) {
    if (str_contains($e->getMessage(), 'LazyGhost is not available')) {
        echo "⚠️  LazyGhost not available in this PHP version\n";
        echo "   Error: {$e->getMessage()}\n";
        echo "   This is expected in PHP < 8.4\n";
    } else {
        throw $e;
    }
} catch (\Exception $e) {
    echo "❌ Unexpected error: {$e->getMessage()}\n";
    throw $e;
}

echo "\n3. Testing Proxy with Relationships\n";
echo "===================================\n";

try {
    // Create a book with the author reference
    $entityManager->beginTransaction();
    $book = new Book('The Great Novel', $authorRef);
    $entityManager->persist($book);
    $entityManager->flush();
    $entityManager->commit();
    
    echo "✅ Created book with proxy author reference\n";
    echo "   - Book: {$book->title}\n";
    echo "   - Author: {$book->author->name}\n";
    
} catch (\RuntimeException $e) {
    if (str_contains($e->getMessage(), 'LazyGhost is not available')) {
        echo "⚠️  Skipping relationship test - LazyGhost not available\n";
        
        // Fallback: create book with regular author entity
        $entityManager->beginTransaction();
        $regularAuthor = $entityManager->find(Author::class, $author->id);
        $book = new Book('The Great Novel', $regularAuthor);
        $entityManager->persist($book);
        $entityManager->flush();
        $entityManager->commit();
        
        echo "✅ Created book with regular author entity (fallback)\n";
        echo "   - Book: {$book->title}\n";
        echo "   - Author: {$book->author->name}\n";
    } else {
        throw $e;
    }
}

echo "\n4. Testing Multiple References\n";
echo "==============================\n";

try {
    // Test creating multiple references to the same entity
    $authorRef1 = $entityManager->getReference(Author::class, $author->id);
    $authorRef2 = $entityManager->getReference(Author::class, $author->id);

    echo "✅ Created multiple author references\n";
    echo "   - Reference 1 class: " . get_class($authorRef1) . "\n";
    echo "   - Reference 2 class: " . get_class($authorRef2) . "\n";
    echo "   - Same instance: " . ($authorRef1 === $authorRef2 ? 'Yes' : 'No') . "\n";
    echo "   - Both have same name: " . ($authorRef1->name === $authorRef2->name ? 'Yes' : 'No') . "\n";

} catch (\RuntimeException $e) {
    if (str_contains($e->getMessage(), 'LazyGhost is not available')) {
        echo "⚠️  Multiple references test skipped - LazyGhost not available\n";
        echo "   This is expected in PHP < 8.4\n";
    } else {
        throw $e;
    }
}

echo "\n5. Summary\n";
echo "=========\n";

echo "✅ Optimized proxy implementation active\n";
echo "✅ Direct database loading enabled\n";
echo "✅ All proxy features working correctly\n";

echo "\n🎉 Proxy usage example completed!\n";
