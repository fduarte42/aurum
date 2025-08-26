<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Fduarte42\Aurum\Attribute\{Entity, Id, Column, ManyToOne, OneToMany};
use Fduarte42\Aurum\DependencyInjection\ContainerBuilder;
use Ramsey\Uuid\UuidInterface;
use Brick\Math\BigDecimal;
use Decimal\Decimal;

echo "🔧 Aurum Type System Demo\n";
echo "========================\n\n";

// ✅ Example 1: Type Inference - No explicit types needed!
#[Entity(table: 'products')]
class Product
{
    #[Id]
    #[Column] // Type inferred as 'uuid' from UuidInterface
    private ?UuidInterface $id = null;

    #[Column] // Type inferred as 'string' with length 255
    private string $name;

    #[Column] // Type inferred as 'decimal' from BigDecimal
    private BigDecimal $price;

    #[Column] // Type inferred as 'integer'
    private int $stock;

    #[Column] // Type inferred as 'boolean'
    private bool $active = true;

    #[Column] // Type inferred as 'datetime'
    private \DateTimeImmutable $createdAt;

    public function __construct(string $name, BigDecimal $price, int $stock)
    {
        $this->name = $name;
        $this->price = $price;
        $this->stock = $stock;
        $this->createdAt = new \DateTimeImmutable();
    }

    // Getters...
    public function getId(): ?UuidInterface { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getPrice(): BigDecimal { return $this->price; }
    public function getStock(): int { return $this->stock; }
    public function isActive(): bool { return $this->active; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setActive(bool $active): void { $this->active = $active; }
}

// ✅ Example 2: Different Decimal Types
#[Entity(table: 'financial_records')]
class FinancialRecord
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?UuidInterface $id = null;

    #[Column(type: 'decimal', precision: 15, scale: 4)] // BigDecimal (brick/math)
    private BigDecimal $amount;

    #[Column(type: 'decimal_ext', precision: 10, scale: 2)] // ext-decimal
    private ?Decimal $tax = null;

    #[Column(type: 'decimal_string', precision: 8, scale: 3)] // String-based decimal
    private string $commission;

    public function __construct(BigDecimal $amount, string $commission)
    {
        $this->amount = $amount;
        $this->commission = $commission;
    }

    // Getters...
    public function getId(): ?UuidInterface { return $this->id; }
    public function getAmount(): BigDecimal { return $this->amount; }
    public function getTax(): ?Decimal { return $this->tax; }
    public function getCommission(): string { return $this->commission; }
    public function setTax(?Decimal $tax): void { $this->tax = $tax; }
}

// ✅ Example 3: Date/Time Types
#[Entity(table: 'events')]
class Event
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?UuidInterface $id = null;

    #[Column] // Type inferred as 'string'
    private string $title;

    #[Column(type: 'date')] // Date only
    private \DateTimeImmutable $eventDate;

    #[Column(type: 'time')] // Time only
    private \DateTimeImmutable $startTime;

    #[Column(type: 'datetime')] // Standard datetime
    private \DateTimeImmutable $createdAt;

    #[Column(type: 'datetime_tz')] // Timezone-aware datetime (stored as JSON)
    private \DateTimeImmutable $scheduledAt;

    public function __construct(
        string $title,
        \DateTimeImmutable $eventDate,
        \DateTimeImmutable $startTime,
        \DateTimeImmutable $scheduledAt
    ) {
        $this->title = $title;
        $this->eventDate = $eventDate;
        $this->startTime = $startTime;
        $this->scheduledAt = $scheduledAt;
        $this->createdAt = new \DateTimeImmutable();
    }

    // Getters...
    public function getId(): ?UuidInterface { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function getEventDate(): \DateTimeImmutable { return $this->eventDate; }
    public function getStartTime(): \DateTimeImmutable { return $this->startTime; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getScheduledAt(): \DateTimeImmutable { return $this->scheduledAt; }
}

// Setup with in-memory database
$config = [
    'connection' => [
        'driver' => 'sqlite',
        'path' => ':memory:'
    ]
];

$container = ContainerBuilder::createORM($config);
$entityManager = $container->get(\Fduarte42\Aurum\EntityManagerInterface::class);

// Create tables
$connection = $entityManager->getConnection();

// Products table
$connection->execute('
    CREATE TABLE products (
        id TEXT PRIMARY KEY,
        name TEXT NOT NULL,
        price TEXT NOT NULL,
        stock INTEGER NOT NULL,
        active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL
    )
');

// Financial records table
$connection->execute('
    CREATE TABLE financial_records (
        id TEXT PRIMARY KEY,
        amount TEXT NOT NULL,
        tax TEXT,
        commission TEXT NOT NULL
    )
');

// Events table
$connection->execute('
    CREATE TABLE events (
        id TEXT PRIMARY KEY,
        title TEXT NOT NULL,
        event_date TEXT NOT NULL,
        start_time TEXT NOT NULL,
        created_at TEXT NOT NULL,
        scheduled_at TEXT NOT NULL
    )
');

echo "✅ Created database tables\n\n";

// Example 1: Create a product with type inference
echo "📦 Creating Product with Type Inference:\n";
$product = new Product(
    'Laptop',
    BigDecimal::of('999.99'),
    10
);

$entityManager->beginTransaction();
$entityManager->persist($product);
$entityManager->flush();
$entityManager->commit();

echo "- Product: {$product->getName()}\n";
echo "- Price: {$product->getPrice()}\n";
echo "- Stock: {$product->getStock()}\n";
echo "- Active: " . ($product->isActive() ? 'Yes' : 'No') . "\n";
echo "- Created: {$product->getCreatedAt()->format('Y-m-d H:i:s')}\n\n";

// Example 2: Create financial record with different decimal types
echo "💰 Creating Financial Record with Different Decimal Types:\n";
$record = new FinancialRecord(
    BigDecimal::of('1234.5678'),
    '123.456'
);

if (extension_loaded('decimal')) {
    $record->setTax(new Decimal('98.76'));
    echo "- Using ext-decimal for tax\n";
} else {
    echo "- ext-decimal not available, tax will be null\n";
}

$entityManager->beginTransaction();
$entityManager->persist($record);
$entityManager->flush();
$entityManager->commit();

echo "- Amount (BigDecimal): {$record->getAmount()}\n";
echo "- Tax (ext-decimal): " . ($record->getTax() ? $record->getTax() : 'null') . "\n";
echo "- Commission (string): {$record->getCommission()}\n\n";

// Example 3: Create event with different date/time types
echo "📅 Creating Event with Different Date/Time Types:\n";
$event = new Event(
    'Conference 2024',
    new \DateTimeImmutable('2024-06-15'),
    new \DateTimeImmutable('09:00:00'),
    new \DateTimeImmutable('2024-06-15 09:00:00', new \DateTimeZone('America/New_York'))
);

$entityManager->beginTransaction();
$entityManager->persist($event);
$entityManager->flush();
$entityManager->commit();

echo "- Title: {$event->getTitle()}\n";
echo "- Event Date: {$event->getEventDate()->format('Y-m-d')}\n";
echo "- Start Time: {$event->getStartTime()->format('H:i:s')}\n";
echo "- Created At: {$event->getCreatedAt()->format('Y-m-d H:i:s')}\n";
echo "- Scheduled At: {$event->getScheduledAt()->format('Y-m-d H:i:s T')}\n\n";

// Query examples
echo "🔍 Querying Data:\n";

$products = $entityManager->getRepository(Product::class)->findAll();
echo "- Found " . count($products) . " product(s)\n";

$records = $entityManager->getRepository(FinancialRecord::class)->findAll();
echo "- Found " . count($records) . " financial record(s)\n";

$events = $entityManager->getRepository(Event::class)->findAll();
echo "- Found " . count($events) . " event(s)\n\n";

echo "✨ Type System Demo completed!\n";
echo "\n🎯 Key Features Demonstrated:\n";
echo "- ✅ Automatic type inference from PHP property types\n";
echo "- ✅ Multiple decimal implementations (BigDecimal, ext-decimal, string)\n";
echo "- ✅ Specialized date/time types (date, time, datetime, datetime_tz)\n";
echo "- ✅ Timezone-aware datetime with JSON storage\n";
echo "- ✅ Backward compatibility with explicit type declarations\n";
