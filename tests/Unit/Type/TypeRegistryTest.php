<?php

declare(strict_types=1);

namespace Tests\Unit\Type;

use Fduarte42\Aurum\Type\TypeRegistry;
use Fduarte42\Aurum\Type\TypeInterface;
use Fduarte42\Aurum\Exception\ORMException;
use PHPUnit\Framework\TestCase;

class TypeRegistryTest extends TestCase
{
    private TypeRegistry $typeRegistry;

    protected function setUp(): void
    {
        $this->typeRegistry = new TypeRegistry();
    }

    public function testRegisterAndGetType(): void
    {
        $mockType = $this->createMock(TypeInterface::class);
        $mockType->method('getName')->willReturn('test_type');

        $this->typeRegistry->registerType('test_type', $mockType);

        $this->assertTrue($this->typeRegistry->hasType('test_type'));
        $this->assertSame($mockType, $this->typeRegistry->getType('test_type'));
    }

    public function testGetUnknownTypeThrowsException(): void
    {
        $this->expectException(ORMException::class);
        $this->expectExceptionMessage('Unknown type "unknown_type".');

        $this->typeRegistry->getType('unknown_type');
    }

    public function testHasType(): void
    {
        $this->assertFalse($this->typeRegistry->hasType('non_existent'));
        $this->assertTrue($this->typeRegistry->hasType('string')); // Default type
    }

    public function testGetTypeNames(): void
    {
        $typeNames = $this->typeRegistry->getTypeNames();

        $this->assertIsArray($typeNames);
        $this->assertContains('string', $typeNames);
        $this->assertContains('integer', $typeNames);
        $this->assertContains('boolean', $typeNames);
        $this->assertContains('decimal', $typeNames);
        $this->assertContains('datetime', $typeNames);
    }

    public function testInferTypeFromPHPType(): void
    {
        $this->assertEquals('string', $this->typeRegistry->inferTypeFromPHPType('string'));
        $this->assertEquals('integer', $this->typeRegistry->inferTypeFromPHPType('int'));
        $this->assertEquals('boolean', $this->typeRegistry->inferTypeFromPHPType('bool'));
        $this->assertEquals('datetime', $this->typeRegistry->inferTypeFromPHPType('DateTimeImmutable'));
        $this->assertEquals('uuid', $this->typeRegistry->inferTypeFromPHPType('Ramsey\\Uuid\\UuidInterface'));
        $this->assertEquals('decimal', $this->typeRegistry->inferTypeFromPHPType('Brick\\Math\\BigDecimal'));
        $this->assertEquals('decimal_ext', $this->typeRegistry->inferTypeFromPHPType('Decimal\\Decimal'));
    }

    public function testInferTypeFromNullablePHPType(): void
    {
        $this->assertEquals('string', $this->typeRegistry->inferTypeFromPHPType('?string'));
        $this->assertEquals('integer', $this->typeRegistry->inferTypeFromPHPType('?int'));
    }

    public function testInferTypeFromUnionType(): void
    {
        $this->assertEquals('string', $this->typeRegistry->inferTypeFromPHPType('string|null'));
        $this->assertEquals('integer', $this->typeRegistry->inferTypeFromPHPType('int|null'));
    }

    public function testInferTypeFromUnknownType(): void
    {
        $this->assertNull($this->typeRegistry->inferTypeFromPHPType('UnknownClass'));
        $this->assertEquals('string', $this->typeRegistry->inferTypeFromPHPType('string|int')); // Takes first type
        $this->assertNull($this->typeRegistry->inferTypeFromPHPType('SomeRandomClass'));
        $this->assertNull($this->typeRegistry->inferTypeFromPHPType('UnknownClass|AnotherUnknownClass')); // Multiple unknown types
    }

    public function testDefaultTypesAreRegistered(): void
    {
        // Basic types
        $this->assertTrue($this->typeRegistry->hasType('string'));
        $this->assertTrue($this->typeRegistry->hasType('integer'));
        $this->assertTrue($this->typeRegistry->hasType('float'));
        $this->assertTrue($this->typeRegistry->hasType('boolean'));
        $this->assertTrue($this->typeRegistry->hasType('text'));
        $this->assertTrue($this->typeRegistry->hasType('json'));
        $this->assertTrue($this->typeRegistry->hasType('uuid'));

        // Decimal types
        $this->assertTrue($this->typeRegistry->hasType('decimal'));
        $this->assertTrue($this->typeRegistry->hasType('decimal_ext'));
        $this->assertTrue($this->typeRegistry->hasType('decimal_string'));

        // Date/Time types
        $this->assertTrue($this->typeRegistry->hasType('date'));
        $this->assertTrue($this->typeRegistry->hasType('time'));
        $this->assertTrue($this->typeRegistry->hasType('datetime'));
        $this->assertTrue($this->typeRegistry->hasType('datetime_tz'));
    }
}
