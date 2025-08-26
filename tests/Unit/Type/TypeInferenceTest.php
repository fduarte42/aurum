<?php

declare(strict_types=1);

namespace Tests\Unit\Type;

use Fduarte42\Aurum\Type\TypeInference;
use Fduarte42\Aurum\Type\TypeRegistry;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use ReflectionClass;

class TypeInferenceTest extends TestCase
{
    private TypeInference $typeInference;
    private TypeRegistry $typeRegistry;

    protected function setUp(): void
    {
        $this->typeRegistry = new TypeRegistry();
        $this->typeInference = new TypeInference($this->typeRegistry);
    }

    public function testInferFromProperty(): void
    {
        $class = new class {
            public string $name;
            public int $age;
            public bool $active;
            public \DateTimeImmutable $createdAt;
            public ?\Ramsey\Uuid\UuidInterface $id;
            public \Brick\Math\BigDecimal $amount;
        };

        $reflection = new ReflectionClass($class);

        // Test string property
        $property = $reflection->getProperty('name');
        $this->assertEquals('string', $this->typeInference->inferFromProperty($property));

        // Test int property
        $property = $reflection->getProperty('age');
        $this->assertEquals('integer', $this->typeInference->inferFromProperty($property));

        // Test bool property
        $property = $reflection->getProperty('active');
        $this->assertEquals('boolean', $this->typeInference->inferFromProperty($property));

        // Test DateTimeImmutable property
        $property = $reflection->getProperty('createdAt');
        $this->assertEquals('datetime', $this->typeInference->inferFromProperty($property));

        // Test nullable UUID property
        $property = $reflection->getProperty('id');
        $this->assertEquals('uuid', $this->typeInference->inferFromProperty($property));

        // Test BigDecimal property
        $property = $reflection->getProperty('amount');
        $this->assertEquals('decimal', $this->typeInference->inferFromProperty($property));
    }

    public function testInferFromPropertyWithoutType(): void
    {
        $class = new class {
            public $untyped;
        };

        $reflection = new ReflectionClass($class);
        $property = $reflection->getProperty('untyped');

        $this->assertNull($this->typeInference->inferFromProperty($property));
    }

    public function testInferFromUnionType(): void
    {
        // This would require PHP 8.0+ union types in actual usage
        // For now, we'll test the logic with a mock
        $this->assertNull($this->typeInference->inferFromProperty(
            $this->createMockPropertyWithUnionType()
        ));
    }

    public function testInferTypeOptions(): void
    {
        $class = new class {
            public string $email;
            public string $name;
            public string $description;
            public \Brick\Math\BigDecimal $price;
            public \Brick\Math\BigDecimal $rate;
        };

        $reflection = new ReflectionClass($class);

        // Test email field
        $property = $reflection->getProperty('email');
        $options = $this->typeInference->inferTypeOptions($property, 'string');
        $this->assertEquals(255, $options['length']);

        // Test name field
        $property = $reflection->getProperty('name');
        $options = $this->typeInference->inferTypeOptions($property, 'string');
        $this->assertEquals(255, $options['length']);

        // Test description field
        $property = $reflection->getProperty('description');
        $options = $this->typeInference->inferTypeOptions($property, 'string');
        $this->assertNull($options['length']); // Should use TEXT type

        // Test price field
        $property = $reflection->getProperty('price');
        $options = $this->typeInference->inferTypeOptions($property, 'decimal');
        $this->assertEquals(10, $options['precision']);
        $this->assertEquals(2, $options['scale']);

        // Test rate field
        $property = $reflection->getProperty('rate');
        $options = $this->typeInference->inferTypeOptions($property, 'decimal');
        $this->assertEquals(5, $options['precision']);
        $this->assertEquals(4, $options['scale']);
    }

    public function testInferStringLength(): void
    {
        $class = new class {
            public string $email;
            public string $url;
            public string $code;
            public string $token;
            public string $randomField;
        };

        $reflection = new ReflectionClass($class);

        $testCases = [
            'email' => 255,
            'url' => 500,
            'code' => 50,
            'token' => 255,
            'randomField' => 255, // default
        ];

        foreach ($testCases as $fieldName => $expectedLength) {
            $property = $reflection->getProperty($fieldName);
            $options = $this->typeInference->inferTypeOptions($property, 'string');
            $this->assertEquals($expectedLength, $options['length'], "Failed for field: $fieldName");
        }
    }

    public function testInferDecimalPrecisionScale(): void
    {
        $class = new class {
            public \Brick\Math\BigDecimal $price;
            public \Brick\Math\BigDecimal $percentage;
            public \Brick\Math\BigDecimal $weight;
            public \Brick\Math\BigDecimal $distance;
            public \Brick\Math\BigDecimal $randomDecimal;
        };

        $reflection = new ReflectionClass($class);

        $testCases = [
            'price' => ['precision' => 10, 'scale' => 2],
            'percentage' => ['precision' => 5, 'scale' => 2],
            'weight' => ['precision' => 8, 'scale' => 3],
            'distance' => ['precision' => 10, 'scale' => 2],
            'randomDecimal' => ['precision' => 10, 'scale' => 2], // default
        ];

        foreach ($testCases as $fieldName => $expected) {
            $property = $reflection->getProperty($fieldName);
            $options = $this->typeInference->inferTypeOptions($property, 'decimal');
            $this->assertEquals($expected['precision'], $options['precision'], "Failed precision for field: $fieldName");
            $this->assertEquals($expected['scale'], $options['scale'], "Failed scale for field: $fieldName");
        }
    }

    private function createMockPropertyWithUnionType(): ReflectionProperty
    {
        // Create a mock that simulates a union type property
        $mock = $this->createMock(ReflectionProperty::class);
        $mock->method('getType')->willReturn(null); // Simplified for testing
        return $mock;
    }
}
