<?php

declare(strict_types=1);

namespace Tests\Unit\Type\DateTime;

use Fduarte42\Aurum\Type\DateTime\DateTimeType;
use DateTimeImmutable;
use DateTime;
use PHPUnit\Framework\TestCase;

class DateTimeTypeTest extends TestCase
{
    private DateTimeType $type;

    protected function setUp(): void
    {
        $this->type = new DateTimeType();
    }

    public function testGetName(): void
    {
        $this->assertEquals('datetime', $this->type->getName());
    }

    public function testConvertToPHPValue(): void
    {
        $this->assertNull($this->type->convertToPHPValue(null));

        // Test string conversion
        $result = $this->type->convertToPHPValue('2023-12-01 15:30:45');
        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertEquals('2023-12-01 15:30:45', $result->format('Y-m-d H:i:s'));

        // Test DateTimeImmutable passthrough
        $dateTime = new DateTimeImmutable('2023-12-01 15:30:45');
        $result = $this->type->convertToPHPValue($dateTime);
        $this->assertSame($dateTime, $result);

        // Test DateTime conversion to DateTimeImmutable
        $dateTime = new DateTime('2023-12-01 15:30:45');
        $result = $this->type->convertToPHPValue($dateTime);
        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertEquals('2023-12-01 15:30:45', $result->format('Y-m-d H:i:s'));
    }

    public function testConvertToDatabaseValue(): void
    {
        $this->assertNull($this->type->convertToDatabaseValue(null));

        // Test DateTimeImmutable conversion
        $dateTime = new DateTimeImmutable('2023-12-01 15:30:45');
        $result = $this->type->convertToDatabaseValue($dateTime);
        $this->assertEquals('2023-12-01 15:30:45', $result);

        // Test DateTime conversion
        $dateTime = new DateTime('2023-12-01 15:30:45');
        $result = $this->type->convertToDatabaseValue($dateTime);
        $this->assertEquals('2023-12-01 15:30:45', $result);

        // Test string conversion
        $result = $this->type->convertToDatabaseValue('2023-12-01 15:30:45');
        $this->assertEquals('2023-12-01 15:30:45', $result);
    }

    public function testGetSQLDeclaration(): void
    {
        $this->assertEquals('DATETIME', $this->type->getSQLDeclaration());
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
        $this->assertTrue($this->type->isCompatibleWithPHPType('DateTimeImmutable'));
        $this->assertTrue($this->type->isCompatibleWithPHPType('DateTime'));
        $this->assertTrue($this->type->isCompatibleWithPHPType('DateTimeInterface'));
        $this->assertFalse($this->type->isCompatibleWithPHPType('string'));
        $this->assertFalse($this->type->isCompatibleWithPHPType('int'));
    }
}
