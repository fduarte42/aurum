<?php

declare(strict_types=1);

/**
 * Aurum Repository Dependency Injection Example
 * 
 * This example demonstrates the new dependency injection features
 * for Repository classes while maintaining backward compatibility.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Fduarte42\Aurum\Attribute\{Entity, Id, Column};
use Fduarte42\Aurum\DependencyInjection\ContainerBuilder;
use Fduarte42\Aurum\Repository\Repository;
use Fduarte42\Aurum\Repository\RepositoryFactory;
use Ramsey\Uuid\UuidInterface;

echo "🔧 Aurum Repository Dependency Injection Example\n";
echo "===============================================\n\n";

// Define a simple entity
#[Entity(table: 'products')]
class Product
{
    #[Id]
    #[Column(type: 'uuid')]
    public private(set) ?UuidInterface $id = null;

    public function __construct(
        #[Column(type: 'string', length: 255)]
        public string $name,

        #[Column(type: 'decimal', precision: 10, scale: 2)]
        public string $price
    ) {
    }
}

// Custom service for dependency injection
class PriceCalculator
{
    public function calculateDiscount(string $price, float $discountPercent): string
    {
        $priceValue = (float) $price;
        $discountedPrice = $priceValue * (1 - $discountPercent / 100);
        return number_format($discountedPrice, 2);
    }
}

// Custom repository with default constructor (uses DI)
class ProductRepositoryWithDI extends Repository
{
    private ?PriceCalculator $priceCalculator = null;

    // Default constructor - dependencies will be injected via reflection
    public function __construct()
    {
        parent::__construct();
    }

    // Setter for custom dependency injection
    public function setPriceCalculator(PriceCalculator $priceCalculator): void
    {
        $this->priceCalculator = $priceCalculator;
    }

    public function findExpensiveProducts(string $minPrice): array
    {
        return iterator_to_array($this->findBySql(
            'SELECT * FROM products WHERE CAST(price AS REAL) > CAST(? AS REAL) ORDER BY price DESC',
            [$minPrice]
        ));
    }

    public function calculateDiscountedPrice(Product $product, float $discountPercent): string
    {
        if ($this->priceCalculator === null) {
            throw new \RuntimeException('PriceCalculator not injected');
        }
        
        return $this->priceCalculator->calculateDiscount($product->price, $discountPercent);
    }
}

// Custom repository with custom constructor
class ProductRepositoryWithCustomConstructor extends Repository
{
    private PriceCalculator $priceCalculator;

    public function __construct(PriceCalculator $priceCalculator)
    {
        parent::__construct(); // Call parent with no args for DI support
        $this->priceCalculator = $priceCalculator;
    }

    public function findDiscountedProducts(float $discountPercent): array
    {
        $products = $this->findAll();
        $discountedProducts = [];
        
        foreach ($products as $product) {
            $discountedPrice = $this->priceCalculator->calculateDiscount($product->price, $discountPercent);
            $discountedProducts[] = [
                'product' => $product,
                'original_price' => $product->price,
                'discounted_price' => $discountedPrice
            ];
        }
        
        return $discountedProducts;
    }
}

// Traditional repository (backward compatibility)
class TraditionalProductRepository extends Repository
{
    // Uses traditional constructor - still works!
    
    public function findByPriceRange(string $minPrice, string $maxPrice): array
    {
        return iterator_to_array($this->findBySql(
            'SELECT * FROM products WHERE CAST(price AS REAL) BETWEEN CAST(? AS REAL) AND CAST(? AS REAL)',
            [$minPrice, $maxPrice]
        ));
    }
}

// Setup
$config = [
    'connection' => [
        'driver' => 'sqlite',
        'path' => ':memory:'
    ]
];

$container = ContainerBuilder::createORM($config);
$entityManager = $container->get(\Fduarte42\Aurum\EntityManagerInterface::class);

// Register custom service in container
$container->set(PriceCalculator::class, new PriceCalculator());

// Create schema
$connection = $entityManager->getConnection();
$connection->execute('
    CREATE TABLE products (
        id TEXT PRIMARY KEY,
        name TEXT NOT NULL,
        price TEXT NOT NULL
    )
');

echo "1. Testing Default Repository (uses traditional DI)\n";
echo "================================================\n";

$defaultRepo = $entityManager->getRepository(Product::class);
echo "Repository class: " . get_class($defaultRepo) . "\n";

// Create some test products
$entityManager->beginTransaction();
$product1 = new Product('Laptop', '999.99');
$product2 = new Product('Mouse', '29.99');
$product3 = new Product('Keyboard', '79.99');

$defaultRepo->save($product1);
$defaultRepo->save($product2);
$defaultRepo->save($product3);
$entityManager->commit();

echo "Created " . $defaultRepo->count() . " products\n\n";

echo "2. Testing Custom Repository with Default Constructor + DI\n";
echo "========================================================\n";

$factory = new RepositoryFactory($entityManager, $container);
$diRepo = $factory->createRepository(Product::class, ProductRepositoryWithDI::class);

echo "Repository class: " . get_class($diRepo) . "\n";
echo "Entity class: " . $diRepo->getClassName() . "\n";

$expensiveProducts = $diRepo->findExpensiveProducts('50.00');
echo "Found " . count($expensiveProducts) . " expensive products (>$50)\n";

// Test custom dependency injection
$discountedPrice = $diRepo->calculateDiscountedPrice($product1, 10.0);
echo "Laptop with 10% discount: $" . $discountedPrice . "\n\n";

echo "3. Testing Custom Repository with Custom Constructor\n";
echo "==================================================\n";

$customRepo = $factory->createRepository(Product::class, ProductRepositoryWithCustomConstructor::class);
echo "Repository class: " . get_class($customRepo) . "\n";

$discountedProducts = $customRepo->findDiscountedProducts(15.0);
echo "Products with 15% discount:\n";
foreach ($discountedProducts as $item) {
    echo "- {$item['product']->name}: \${$item['original_price']} -> \${$item['discounted_price']}\n";
}
echo "\n";

echo "4. Testing Traditional Repository (Backward Compatibility)\n";
echo "=========================================================\n";

$traditionalRepo = $factory->createRepository(Product::class, TraditionalProductRepository::class);

echo "Repository class: " . get_class($traditionalRepo) . "\n";

$midRangeProducts = $traditionalRepo->findByPriceRange('25.00', '100.00');
echo "Found " . count($midRangeProducts) . " mid-range products ($25-$100)\n\n";

echo "5. Container Integration\n";
echo "=======================\n";

echo "EntityManager has container: " . ($entityManager->getContainer() ? 'Yes' : 'No') . "\n";
echo "Container has PriceCalculator: " . ($container->has(PriceCalculator::class) ? 'Yes' : 'No') . "\n";

// Test setting a new container
$newContainer = new \Fduarte42\Aurum\DependencyInjection\SimpleContainer([
    PriceCalculator::class => new PriceCalculator()
]);
$entityManager->setContainer($newContainer);
echo "Set new container successfully\n\n";

echo "✅ All dependency injection features working correctly!\n";
echo "\nKey Features Demonstrated:\n";
echo "- Default constructor support with reflection-based DI\n";
echo "- Custom constructors with user-defined dependencies\n";
echo "- Framework dependency injection (EntityManager, Metadata)\n";
echo "- Container integration for external services\n";
echo "- Full backward compatibility with traditional repositories\n";
