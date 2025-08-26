<?php

declare(strict_types=1);

namespace Tests\Unit\Type\Basic;

use Fduarte42\Aurum\Type\Basic\IntegerType;
use PHPUnit\Framework\TestCase;

class IntegerTypeTest extends TestCase
{
    private IntegerType $type;

    protected function setUp(): void
    {
        $this->type = new IntegerType();
    }

    public function testGetName(): void
    {
        $this->assertEquals('integer', $this->type->getName());
    }

    public function testConvertToPHPValue(): void
    {
        $this->assertNull($this->type->convertToPHPValue(null));
        $this->assertEquals(123, $this->type->convertToPHPValue(123));
        $this->assertEquals(123, $this->type->convertToPHPValue('123'));
        $this->assertEquals(1, $this->type->convertToPHPValue(true));
        $this->assertEquals(0, $this->type->convertToPHPValue(false));
        $this->assertEquals(123, $this->type->convertToPHPValue(123.7));
    }

    public function testConvertToDatabaseValue(): void
    {
        $this->assertNull($this->type->convertToDatabaseValue(null));
        $this->assertEquals(123, $this->type->convertToDatabaseValue(123));
        $this->assertEquals(123, $this->type->convertToDatabaseValue('123'));
        $this->assertEquals(1, $this->type->convertToDatabaseValue(true));
        $this->assertEquals(0, $this->type->convertToDatabaseValue(false));
        $this->assertEquals(123, $this->type->convertToDatabaseValue(123.7));
    }

    public function testGetSQLDeclaration(): void
    {
        $this->assertEquals('INTEGER', $this->type->getSQLDeclaration());
        $this->assertEquals('INTEGER', $this->type->getSQLDeclaration(['length' => 100]));
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
        $this->assertTrue($this->type->isCompatibleWithPHPType('int'));
        $this->assertTrue($this->type->isCompatibleWithPHPType('integer'));
        $this->assertFalse($this->type->isCompatibleWithPHPType('string'));
        $this->assertFalse($this->type->isCompatibleWithPHPType('bool'));
    }
}
