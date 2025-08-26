<?php

declare(strict_types=1);

namespace Tests\Unit\Type\Basic;

use Fduarte42\Aurum\Type\Basic\BooleanType;
use PHPUnit\Framework\TestCase;

class BooleanTypeTest extends TestCase
{
    private BooleanType $type;

    protected function setUp(): void
    {
        $this->type = new BooleanType();
    }

    public function testGetName(): void
    {
        $this->assertEquals('boolean', $this->type->getName());
    }

    public function testConvertToPHPValue(): void
    {
        $this->assertNull($this->type->convertToPHPValue(null));
        $this->assertTrue($this->type->convertToPHPValue(true));
        $this->assertFalse($this->type->convertToPHPValue(false));
        $this->assertTrue($this->type->convertToPHPValue(1));
        $this->assertFalse($this->type->convertToPHPValue(0));
        $this->assertTrue($this->type->convertToPHPValue('1'));
        $this->assertFalse($this->type->convertToPHPValue(''));
        $this->assertTrue($this->type->convertToPHPValue('true'));
    }

    public function testConvertToDatabaseValue(): void
    {
        $this->assertNull($this->type->convertToDatabaseValue(null));
        $this->assertEquals(1, $this->type->convertToDatabaseValue(true));
        $this->assertEquals(0, $this->type->convertToDatabaseValue(false));
        $this->assertEquals(1, $this->type->convertToDatabaseValue(1));
        $this->assertEquals(0, $this->type->convertToDatabaseValue(0));
        $this->assertEquals(1, $this->type->convertToDatabaseValue('1'));
        $this->assertEquals(0, $this->type->convertToDatabaseValue(''));
    }

    public function testGetSQLDeclaration(): void
    {
        $this->assertEquals('BOOLEAN', $this->type->getSQLDeclaration());
    }

    public function testRequiresLength(): void
    {
        $this->assertFalse($this->type->requiresLength());
    }

    public function testSupportsPrecisionScale(): void
    {
        $this->assertFalse($this->type->supportsPrecisionScale());
    }

    public function testGetDefaultLength(): void
    {
        $this->assertNull($this->type->getDefaultLength());
    }

    public function testGetDefaultPrecision(): void
    {
        $this->assertNull($this->type->getDefaultPrecision());
    }

    public function testGetDefaultScale(): void
    {
        $this->assertNull($this->type->getDefaultScale());
    }

    public function testIsCompatibleWithPHPType(): void
    {
        $this->assertTrue($this->type->isCompatibleWithPHPType('bool'));
        $this->assertTrue($this->type->isCompatibleWithPHPType('boolean'));
        $this->assertFalse($this->type->isCompatibleWithPHPType('string'));
        $this->assertFalse($this->type->isCompatibleWithPHPType('int'));
    }
}
