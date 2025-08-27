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

        // Test DateTimeImmutable passthrough
        $dateTime = new DateTimeImmutable('2023-12-01 15:30:45', new DateTimeZone('UTC'));
        $result = $this->type->convertToPHPValue($dateTime);
        $this->assertSame($dateTime, $result);

        // Test DateTime conversion to DateTimeImmutable
        $dateTime = new \DateTime('2023-12-01 15:30:45', new DateTimeZone('America/New_York'));
        $result = $this->type->convertToPHPValue($dateTime);
        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertEquals('2023-12-01 15:30:45', $result->format('Y-m-d H:i:s'));
        $this->assertEquals('America/New_York', $result->getTimezone()->getName());

        // Test string parsing
        $result = $this->type->convertToPHPValue('2023-12-01 15:30:45');
        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertEquals('2023-12-01 15:30:45', $result->format('Y-m-d H:i:s'));
    }

    public function testConvertToDatabaseValue(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('convertToDatabaseValue() is not supported for multi-column types. Use convertToMultipleDatabaseValues() instead.');

        $dateTime = new DateTimeImmutable('2023-12-01 15:30:45', new DateTimeZone('America/New_York'));
        $this->type->convertToDatabaseValue($dateTime);
    }

    public function testGetSQLDeclaration(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('getSQLDeclaration() is not supported for multi-column types. Use getMultiColumnSQLDeclarations() instead.');

        $this->type->getSQLDeclaration();
    }

    public function testGetRequiredColumnPostfixes(): void
    {
        $expected = ['_utc', '_local', '_timezone'];
        $this->assertEquals($expected, $this->type->getRequiredColumnPostfixes());
    }

    public function testRequiresMultiColumnStorage(): void
    {
        $this->assertTrue($this->type->requiresMultiColumnStorage());
    }

    public function testConvertToMultipleDatabaseValues(): void
    {
        // Test null conversion
        $nullResult = $this->type->convertToMultipleDatabaseValues(null);
        $this->assertIsArray($nullResult);
        $this->assertNull($nullResult['_utc']);
        $this->assertNull($nullResult['_local']);
        $this->assertNull($nullResult['_timezone']);

        // Test DateTimeImmutable conversion
        $dateTime = new DateTimeImmutable('2023-12-01 15:30:45', new DateTimeZone('America/New_York'));
        $result = $this->type->convertToMultipleDatabaseValues($dateTime);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('_utc', $result);
        $this->assertArrayHasKey('_local', $result);
        $this->assertArrayHasKey('_timezone', $result);

        $this->assertEquals('2023-12-01 20:30:45', $result['_utc']); // UTC time (EST is UTC-5)
        $this->assertEquals('2023-12-01 15:30:45', $result['_local']); // Local time
        $this->assertEquals('America/New_York', $result['_timezone']);

        // Test string conversion
        $result = $this->type->convertToMultipleDatabaseValues('2023-12-01 15:30:45');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('_utc', $result);
        $this->assertArrayHasKey('_local', $result);
        $this->assertArrayHasKey('_timezone', $result);

        // Test unsupported type
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot convert value to multiple database values: unsupported type');
        $this->type->convertToMultipleDatabaseValues(['invalid' => 'data']);
    }

    public function testConvertFromMultipleDatabaseValues(): void
    {
        // Test with all values present
        $values = [
            '_utc' => '2023-12-01 20:30:45',
            '_local' => '2023-12-01 15:30:45',
            '_timezone' => 'America/New_York'
        ];
        $result = $this->type->convertFromMultipleDatabaseValues($values);

        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertEquals('2023-12-01 15:30:45', $result->format('Y-m-d H:i:s'));
        $this->assertEquals('America/New_York', $result->getTimezone()->getName());

        // Test with null values
        $nullValues = [
            '_utc' => null,
            '_local' => null,
            '_timezone' => null
        ];
        $this->assertNull($this->type->convertFromMultipleDatabaseValues($nullValues));

        // Test with missing timezone but UTC available
        $utcOnlyValues = [
            '_utc' => '2023-12-01 20:30:45',
            '_local' => null,
            '_timezone' => null
        ];
        $result = $this->type->convertFromMultipleDatabaseValues($utcOnlyValues);
        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertEquals('UTC', $result->getTimezone()->getName());

        // Test with invalid timezone
        $invalidTimezoneValues = [
            '_utc' => '2023-12-01 20:30:45',
            '_local' => '2023-12-01 15:30:45',
            '_timezone' => 'Invalid/Timezone'
        ];
        $result = $this->type->convertFromMultipleDatabaseValues($invalidTimezoneValues);
        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertEquals('UTC', $result->getTimezone()->getName());
    }

    public function testGetMultiColumnSQLDeclarations(): void
    {
        $postfixes = ['_utc', '_local', '_timezone'];
        $declarations = $this->type->getMultiColumnSQLDeclarations($postfixes);

        $this->assertIsArray($declarations);
        $this->assertArrayHasKey('_utc', $declarations);
        $this->assertArrayHasKey('_local', $declarations);
        $this->assertArrayHasKey('_timezone', $declarations);

        $this->assertEquals('DATETIME', $declarations['_utc']);
        $this->assertEquals('DATETIME', $declarations['_local']);
        $this->assertEquals('VARCHAR(50)', $declarations['_timezone']);

        // Test with unknown postfix
        $unknownPostfixes = ['_unknown'];
        $unknownDeclarations = $this->type->getMultiColumnSQLDeclarations($unknownPostfixes);
        $this->assertEquals('TEXT', $unknownDeclarations['_unknown']);
    }

    public function testGetPlatformMultiColumnSQLDeclarations(): void
    {
        $postfixes = ['_utc', '_local', '_timezone'];

        // Test SQLite platform
        $sqliteDeclarations = $this->type->getPlatformMultiColumnSQLDeclarations('sqlite', $postfixes);
        $this->assertEquals('TEXT', $sqliteDeclarations['_utc']);
        $this->assertEquals('TEXT', $sqliteDeclarations['_local']);
        $this->assertEquals('TEXT', $sqliteDeclarations['_timezone']);

        // Test MySQL platform
        $mysqlDeclarations = $this->type->getPlatformMultiColumnSQLDeclarations('mysql', $postfixes);
        $this->assertEquals('DATETIME', $mysqlDeclarations['_utc']);
        $this->assertEquals('DATETIME', $mysqlDeclarations['_local']);
        $this->assertEquals('VARCHAR(50)', $mysqlDeclarations['_timezone']);

        // Test generic platform
        $genericDeclarations = $this->type->getPlatformMultiColumnSQLDeclarations('postgresql', $postfixes);
        $this->assertEquals('DATETIME', $genericDeclarations['_utc']);
        $this->assertEquals('DATETIME', $genericDeclarations['_local']);
        $this->assertEquals('VARCHAR(50)', $genericDeclarations['_timezone']);
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
