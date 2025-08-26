<?php

declare(strict_types=1);

namespace Tests\Unit\Type\DateTime;

use Fduarte42\Aurum\Type\DateTime\DateTimeWithTimezoneType;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class DateTimeWithTimezoneTypeTest extends TestCase
{
    private DateTimeWithTimezoneType $type;

    protected function setUp(): void
    {
        $this->type = new DateTimeWithTimezoneType();
    }

    public function testGetName(): void
    {
        $this->assertEquals('datetime_tz', $this->type->getName());
    }

    public function testConvertToPHPValue(): void
    {
        $this->assertNull($this->type->convertToPHPValue(null));

        // Test JSON string conversion
        $jsonData = json_encode([
            'datetime' => '2023-12-01 15:30:45',
            'timezone' => 'America/New_York'
        ]);
        $result = $this->type->convertToPHPValue($jsonData);
        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertEquals('2023-12-01 15:30:45', $result->format('Y-m-d H:i:s'));
        $this->assertEquals('America/New_York', $result->getTimezone()->getName());

        // Test array conversion
        $arrayData = [
            'datetime' => '2023-12-01 15:30:45',
            'timezone' => 'Europe/London'
        ];
        $result = $this->type->convertToPHPValue($arrayData);
        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertEquals('Europe/London', $result->getTimezone()->getName());

        // Test DateTimeImmutable passthrough
        $dateTime = new DateTimeImmutable('2023-12-01 15:30:45', new DateTimeZone('UTC'));
        $result = $this->type->convertToPHPValue($dateTime);
        $this->assertSame($dateTime, $result);

        // Test fallback string parsing
        $result = $this->type->convertToPHPValue('2023-12-01 15:30:45');
        $this->assertInstanceOf(DateTimeImmutable::class, $result);
    }

    public function testConvertToDatabaseValue(): void
    {
        $this->assertNull($this->type->convertToDatabaseValue(null));

        // Test DateTimeImmutable conversion
        $dateTime = new DateTimeImmutable('2023-12-01 15:30:45', new DateTimeZone('America/New_York'));
        $result = $this->type->convertToDatabaseValue($dateTime);
        $decoded = json_decode($result, true);
        
        $this->assertIsArray($decoded);
        $this->assertEquals('2023-12-01 15:30:45', $decoded['datetime']);
        $this->assertEquals('America/New_York', $decoded['timezone']);

        // Test string conversion
        $result = $this->type->convertToDatabaseValue('2023-12-01 15:30:45');
        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $this->assertEquals('2023-12-01 15:30:45', $decoded['datetime']);

        // Test array conversion
        $arrayData = [
            'datetime' => '2023-12-01 15:30:45',
            'timezone' => 'Europe/London'
        ];
        $result = $this->type->convertToDatabaseValue($arrayData);
        $this->assertEquals(json_encode($arrayData), $result);
    }

    public function testGetSQLDeclaration(): void
    {
        $this->assertEquals('JSON', $this->type->getSQLDeclaration());
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
