<?php

declare(strict_types=1);

namespace Tests\Integration;

use Fduarte42\Aurum\Attribute\Column;
use Fduarte42\Aurum\Attribute\DiscriminatorColumn;
use Fduarte42\Aurum\Attribute\Entity;
use Fduarte42\Aurum\Attribute\Id;
use Fduarte42\Aurum\Attribute\InheritanceType;
use Fduarte42\Aurum\Metadata\MetadataFactory;
use Fduarte42\Aurum\Metadata\InheritanceMapping;
use Fduarte42\Aurum\Type\TypeRegistry;
use PHPUnit\Framework\TestCase;

#[Entity(table: 'vehicles')]
#[InheritanceType(strategy: InheritanceType::SINGLE_TABLE)]
#[DiscriminatorColumn(name: 'vehicle_type', type: 'string', length: 50)]
abstract class Vehicle
{
    #[Id]
    #[Column(type: 'uuid')]
    protected ?string $id = null;

    #[Column(type: 'string', length: 255)]
    protected string $brand;

    #[Column(type: 'string', length: 255)]
    protected string $model;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    public function getBrand(): string
    {
        return $this->brand;
    }

    public function setBrand(string $brand): void
    {
        $this->brand = $brand;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setModel(string $model): void
    {
        $this->model = $model;
    }
}

#[Entity]
class Car extends Vehicle
{
    #[Column(type: 'integer')]
    private int $doors = 4;

    #[Column(type: 'boolean')]
    private bool $isElectric = false;

    public function getDoors(): int
    {
        return $this->doors;
    }

    public function setDoors(int $doors): void
    {
        $this->doors = $doors;
    }

    public function isElectric(): bool
    {
        return $this->isElectric;
    }

    public function setElectric(bool $isElectric): void
    {
        $this->isElectric = $isElectric;
    }
}

#[Entity]
class Motorcycle extends Vehicle
{
    #[Column(type: 'integer')]
    private int $engineSize;

    #[Column(type: 'boolean')]
    private bool $hasSidecar = false;

    public function getEngineSize(): int
    {
        return $this->engineSize;
    }

    public function setEngineSize(int $engineSize): void
    {
        $this->engineSize = $engineSize;
    }

    public function hasSidecar(): bool
    {
        return $this->hasSidecar;
    }

    public function setHasSidecar(bool $hasSidecar): void
    {
        $this->hasSidecar = $hasSidecar;
    }
}

#[Entity]
class Truck extends Vehicle
{
    #[Column(type: 'decimal', precision: 10, scale: 2)]
    private string $maxLoadWeight;

    #[Column(type: 'integer')]
    private int $axles = 2;

    public function getMaxLoadWeight(): string
    {
        return $this->maxLoadWeight;
    }

    public function setMaxLoadWeight(string $maxLoadWeight): void
    {
        $this->maxLoadWeight = $maxLoadWeight;
    }

    public function getAxles(): int
    {
        return $this->axles;
    }

    public function setAxles(int $axles): void
    {
        $this->axles = $axles;
    }
}

class InheritanceIntegrationTest extends TestCase
{
    private MetadataFactory $metadataFactory;
    private TypeRegistry $typeRegistry;

    protected function setUp(): void
    {
        $this->typeRegistry = new TypeRegistry();
        $this->metadataFactory = new MetadataFactory($this->typeRegistry);
    }

    public function testRootClassInheritanceMetadata(): void
    {
        $metadata = $this->metadataFactory->getMetadataFor(Vehicle::class);

        $this->assertTrue($metadata->hasInheritance());
        $this->assertTrue($metadata->isInheritanceRoot());

        $inheritanceMapping = $metadata->getInheritanceMapping();
        $this->assertNotNull($inheritanceMapping);
        $this->assertEquals(InheritanceType::SINGLE_TABLE, $inheritanceMapping->getStrategy());
        $this->assertEquals('vehicle_type', $inheritanceMapping->getDiscriminatorColumn());
        $this->assertEquals('string', $inheritanceMapping->getDiscriminatorType());
        $this->assertEquals(50, $inheritanceMapping->getDiscriminatorLength());
        $this->assertEquals(Vehicle::class, $inheritanceMapping->getRootClassName());
        $this->assertTrue($inheritanceMapping->isRootClass());
    }

    public function testChildClassInheritanceMetadata(): void
    {
        // Load child class metadata
        $carMetadata = $this->metadataFactory->getMetadataFor(Car::class);
        $motorcycleMetadata = $this->metadataFactory->getMetadataFor(Motorcycle::class);
        $truckMetadata = $this->metadataFactory->getMetadataFor(Truck::class);

        // Check that child classes have inheritance metadata
        $this->assertTrue($carMetadata->hasInheritance());
        $this->assertFalse($carMetadata->isInheritanceRoot());

        $this->assertTrue($motorcycleMetadata->hasInheritance());
        $this->assertFalse($motorcycleMetadata->isInheritanceRoot());

        $this->assertTrue($truckMetadata->hasInheritance());
        $this->assertFalse($truckMetadata->isInheritanceRoot());

        // Check discriminator values
        $this->assertEquals(Car::class, $carMetadata->getDiscriminatorValue());
        $this->assertEquals(Motorcycle::class, $motorcycleMetadata->getDiscriminatorValue());
        $this->assertEquals(Truck::class, $truckMetadata->getDiscriminatorValue());
    }

    public function testDiscriminatorFieldMapping(): void
    {
        $metadata = $this->metadataFactory->getMetadataFor(Vehicle::class);
        $fieldMappings = $metadata->getFieldMappings();

        // Check that discriminator field mapping is added
        $this->assertArrayHasKey('__discriminator', $fieldMappings);

        $discriminatorMapping = $fieldMappings['__discriminator'];
        $this->assertEquals('__discriminator', $discriminatorMapping->getFieldName());
        $this->assertEquals('vehicle_type', $discriminatorMapping->getColumnName());
        $this->assertEquals('string', $discriminatorMapping->getType());
        $this->assertFalse($discriminatorMapping->isNullable());
        $this->assertFalse($discriminatorMapping->isIdentifier());
    }

    public function testColumnNamesIncludeDiscriminator(): void
    {
        $metadata = $this->metadataFactory->getMetadataFor(Vehicle::class);
        $columnNames = $metadata->getColumnNames();

        $this->assertContains('vehicle_type', $columnNames);
        $this->assertContains('id', $columnNames);
        $this->assertContains('brand', $columnNames);
        $this->assertContains('model', $columnNames);
    }

    public function testDiscriminatorFieldValueHandling(): void
    {
        $carMetadata = $this->metadataFactory->getMetadataFor(Car::class);
        $car = new Car();
        $car->setBrand('Toyota');
        $car->setModel('Camry');

        // Test getting discriminator field value
        $discriminatorValue = $carMetadata->getFieldValue($car, '__discriminator');
        $this->assertEquals(Car::class, $discriminatorValue);

        // Test setting discriminator field value (should be ignored)
        $carMetadata->setFieldValue($car, '__discriminator', 'SomeOtherValue');
        $discriminatorValueAfterSet = $carMetadata->getFieldValue($car, '__discriminator');
        $this->assertEquals(Car::class, $discriminatorValueAfterSet); // Should still be Car::class
    }

    public function testInheritanceHierarchyDiscovery(): void
    {
        // Load all classes to trigger hierarchy discovery
        $vehicleMetadata = $this->metadataFactory->getMetadataFor(Vehicle::class);
        $this->metadataFactory->getMetadataFor(Car::class);
        $this->metadataFactory->getMetadataFor(Motorcycle::class);
        $this->metadataFactory->getMetadataFor(Truck::class);

        $inheritanceMapping = $vehicleMetadata->getInheritanceMapping();
        $this->assertNotNull($inheritanceMapping);

        // Check that child classes are registered
        $childClasses = $inheritanceMapping->getChildClassNames();
        $this->assertContains(Car::class, $childClasses);
        $this->assertContains(Motorcycle::class, $childClasses);
        $this->assertContains(Truck::class, $childClasses);

        // Check discriminator map
        $discriminatorMap = $inheritanceMapping->getDiscriminatorMap();
        $this->assertArrayHasKey(Vehicle::class, $discriminatorMap);
        $this->assertArrayHasKey(Car::class, $discriminatorMap);
        $this->assertArrayHasKey(Motorcycle::class, $discriminatorMap);
        $this->assertArrayHasKey(Truck::class, $discriminatorMap);

        $this->assertEquals(Vehicle::class, $discriminatorMap[Vehicle::class]);
        $this->assertEquals(Car::class, $discriminatorMap[Car::class]);
        $this->assertEquals(Motorcycle::class, $discriminatorMap[Motorcycle::class]);
        $this->assertEquals(Truck::class, $discriminatorMap[Truck::class]);
    }

    public function testInheritanceMappingMethods(): void
    {
        $vehicleMetadata = $this->metadataFactory->getMetadataFor(Vehicle::class);
        $this->metadataFactory->getMetadataFor(Car::class);
        $this->metadataFactory->getMetadataFor(Motorcycle::class);

        $inheritanceMapping = $vehicleMetadata->getInheritanceMapping();
        $this->assertInstanceOf(InheritanceMapping::class, $inheritanceMapping);

        // Test isInHierarchy
        $this->assertTrue($inheritanceMapping->isInHierarchy(Vehicle::class));
        $this->assertTrue($inheritanceMapping->isInHierarchy(Car::class));
        $this->assertTrue($inheritanceMapping->isInHierarchy(Motorcycle::class));
        $this->assertFalse($inheritanceMapping->isInHierarchy('NonExistentClass'));

        // Test getAllClassNames
        $allClasses = $inheritanceMapping->getAllClassNames();
        $this->assertContains(Vehicle::class, $allClasses);
        $this->assertContains(Car::class, $allClasses);
        $this->assertContains(Motorcycle::class, $allClasses);

        // Test isChildClass
        $this->assertFalse($inheritanceMapping->isChildClass(Vehicle::class));
        $this->assertTrue($inheritanceMapping->isChildClass(Car::class));
        $this->assertTrue($inheritanceMapping->isChildClass(Motorcycle::class));
    }
}
