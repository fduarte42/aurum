<?php

declare(strict_types=1);

namespace Tests\Unit\Type\Basic;

use Fduarte42\Aurum\Type\Basic\StringType;
use PHPUnit\Framework\TestCase;

class StringTypeTest extends TestCase
{
    private StringType $type;

    protected function setUp(): void
    {
        $this->type = new StringType();
    }

    public function testGetName(): void
    {
        $this->assertEquals('string', $this->type->getName());
    }

    public function testConvertToPHPValue(): void
    {
        $this->assertNull($this->type->convertToPHPValue(null));
        $this->assertEquals('test', $this->type->convertToPHPValue('test'));
        $this->assertEquals('123', $this->type->convertToPHPValue(123));
        $this->assertEquals('1', $this->type->convertToPHPValue(true));
    }

    public function testConvertToDatabaseValue(): void
    {
        $this->assertNull($this->type->convertToDatabaseValue(null));
        $this->assertEquals('test', $this->type->convertToDatabaseValue('test'));
        $this->assertEquals('123', $this->type->convertToDatabaseValue(123));
        $this->assertEquals('1', $this->type->convertToDatabaseValue(true));
    }

    public function testGetSQLDeclaration(): void
    {
        $this->assertEquals('VARCHAR(255)', $this->type->getSQLDeclaration());
        $this->assertEquals('VARCHAR(100)', $this->type->getSQLDeclaration(['length' => 100]));
    }

    public function testRequiresLength(): void
    {
        $this->assertTrue($this->type->requiresLength());
    }

    public function testSupportsPrecisionScale(): void
    {
        $this->assertFalse($this->type->supportsPrecisionScale());
    }

    public function testGetDefaultLength(): void
    {
        $this->assertEquals(255, $this->type->getDefaultLength());
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
        $this->assertTrue($this->type->isCompatibleWithPHPType('string'));
        $this->assertFalse($this->type->isCompatibleWithPHPType('int'));
        $this->assertFalse($this->type->isCompatibleWithPHPType('bool'));
    }
}
