<?php

declare(strict_types=1);

namespace Tests\Unit\Type\Decimal;

use Fduarte42\Aurum\Type\Decimal\BigDecimalType;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

class BigDecimalTypeTest extends TestCase
{
    private BigDecimalType $type;

    protected function setUp(): void
    {
        $this->type = new BigDecimalType();
    }

    public function testGetName(): void
    {
        $this->assertEquals('decimal', $this->type->getName());
    }

    public function testConvertToPHPValue(): void
    {
        $this->assertNull($this->type->convertToPHPValue(null));

        // Test string conversion
        $result = $this->type->convertToPHPValue('123.45');
        $this->assertInstanceOf(BigDecimal::class, $result);
        $this->assertEquals('123.45', (string) $result);

        // Test numeric conversion
        $result = $this->type->convertToPHPValue(123.45);
        $this->assertInstanceOf(BigDecimal::class, $result);
        $this->assertEquals('123.45', (string) $result);

        // Test BigDecimal passthrough
        $bigDecimal = BigDecimal::of('123.45');
        $result = $this->type->convertToPHPValue($bigDecimal);
        $this->assertSame($bigDecimal, $result);
    }

    public function testConvertToDatabaseValue(): void
    {
        $this->assertNull($this->type->convertToDatabaseValue(null));

        // Test BigDecimal conversion
        $bigDecimal = BigDecimal::of('123.45');
        $result = $this->type->convertToDatabaseValue($bigDecimal);
        $this->assertEquals('123.45', $result);

        // Test string conversion
        $result = $this->type->convertToDatabaseValue('123.45');
        $this->assertEquals('123.45', $result);

        // Test numeric conversion
        $result = $this->type->convertToDatabaseValue(123.45);
        $this->assertEquals('123.45', $result);
    }

    public function testGetSQLDeclaration(): void
    {
        $this->assertEquals('DECIMAL(10, 2)', $this->type->getSQLDeclaration());
        $this->assertEquals('DECIMAL(15, 4)', $this->type->getSQLDeclaration([
            'precision' => 15,
            'scale' => 4
        ]));
    }

    public function testSupportsPrecisionScale(): void
    {
        $this->assertTrue($this->type->supportsPrecisionScale());
    }

    public function testRequiresLength(): void
    {
        $this->assertFalse($this->type->requiresLength());
    }

    public function testGetDefaultPrecision(): void
    {
        $this->assertEquals(10, $this->type->getDefaultPrecision());
    }

    public function testGetDefaultScale(): void
    {
        $this->assertEquals(2, $this->type->getDefaultScale());
    }

    public function testGetDefaultLength(): void
    {
        $this->assertNull($this->type->getDefaultLength());
    }

    public function testIsCompatibleWithPHPType(): void
    {
        $this->assertTrue($this->type->isCompatibleWithPHPType('Brick\\Math\\BigDecimal'));
        $this->assertTrue($this->type->isCompatibleWithPHPType('BigDecimal'));
        $this->assertFalse($this->type->isCompatibleWithPHPType('string'));
        $this->assertFalse($this->type->isCompatibleWithPHPType('int'));
        $this->assertFalse($this->type->isCompatibleWithPHPType('Decimal\\Decimal'));
    }
}
