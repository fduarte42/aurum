<?php

declare(strict_types=1);

namespace Tests\Unit\Metadata;

use Fduarte42\Aurum\Metadata\MultiColumnFieldMapping;
use Fduarte42\Aurum\Type\TypeRegistry;
use Fduarte42\Aurum\Type\DateTime\DateTimeWithTimezoneType;
use PHPUnit\Framework\TestCase;

class MultiColumnFieldMappingTest extends TestCase
{
    private MultiColumnFieldMapping $mapping;
    private TypeRegistry $typeRegistry;

    protected function setUp(): void
    {
        $this->typeRegistry = new TypeRegistry();
        $this->mapping = new MultiColumnFieldMapping(
            fieldName: 'scheduledAt',
            baseColumnName: 'scheduled_at',
            columnPostfixes: ['_utc', '_local', '_timezone'],
            type: 'datetime_tz',
            nullable: false,
            unique: false,
            typeRegistry: $this->typeRegistry
        );
    }

    public function testGetFieldName(): void
    {
        $this->assertEquals('scheduledAt', $this->mapping->getFieldName());
    }

    public function testGetBaseColumnName(): void
    {
        $this->assertEquals('scheduled_at', $this->mapping->getBaseColumnName());
    }

    public function testGetColumnPostfixes(): void
    {
        $this->assertEquals(['_utc', '_local', '_timezone'], $this->mapping->getColumnPostfixes());
    }

    public function testGetColumnNames(): void
    {
        $expected = ['scheduled_at_utc', 'scheduled_at_local', 'scheduled_at_timezone'];
        $this->assertEquals($expected, $this->mapping->getColumnNames());
    }

    public function testGetColumnNamesWithPostfixes(): void
    {
        $expected = [
            '_utc' => 'scheduled_at_utc',
            '_local' => 'scheduled_at_local',
            '_timezone' => 'scheduled_at_timezone'
        ];
        $this->assertEquals($expected, $this->mapping->getColumnNamesWithPostfixes());
    }

    public function testGetColumnName(): void
    {
        // Should return the first column name for backward compatibility
        $this->assertEquals('scheduled_at_utc', $this->mapping->getColumnName());
    }

    public function testIsMultiColumn(): void
    {
        $this->assertTrue($this->mapping->isMultiColumn());
    }

    public function testGetType(): void
    {
        $this->assertEquals('datetime_tz', $this->mapping->getType());
    }

    public function testIsNullable(): void
    {
        $this->assertFalse($this->mapping->isNullable());
    }

    public function testIsUnique(): void
    {
        $this->assertFalse($this->mapping->isUnique());
    }

    public function testIsIdentifier(): void
    {
        $this->assertFalse($this->mapping->isIdentifier());
    }

    public function testIsGenerated(): void
    {
        $this->assertFalse($this->mapping->isGenerated());
    }

    public function testGetTypeInstance(): void
    {
        $typeInstance = $this->mapping->getTypeInstance();
        $this->assertInstanceOf(DateTimeWithTimezoneType::class, $typeInstance);
    }

    public function testConvertToMultipleDatabaseValues(): void
    {
        $dateTime = new \DateTimeImmutable('2023-12-01 15:30:45', new \DateTimeZone('America/New_York'));
        $result = $this->mapping->convertToMultipleDatabaseValues($dateTime);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('_utc', $result);
        $this->assertArrayHasKey('_local', $result);
        $this->assertArrayHasKey('_timezone', $result);
        
        $this->assertEquals('2023-12-01 20:30:45', $result['_utc']); // UTC time
        $this->assertEquals('2023-12-01 15:30:45', $result['_local']); // Local time
        $this->assertEquals('America/New_York', $result['_timezone']);
    }

    public function testConvertFromMultipleDatabaseValues(): void
    {
        $values = [
            '_utc' => '2023-12-01 20:30:45',
            '_local' => '2023-12-01 15:30:45',
            '_timezone' => 'America/New_York'
        ];

        $result = $this->mapping->convertFromMultipleDatabaseValues($values);

        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
        $this->assertEquals('2023-12-01 15:30:45', $result->format('Y-m-d H:i:s'));
        $this->assertEquals('America/New_York', $result->getTimezone()->getName());
    }

    public function testConvertFromMultipleDatabaseValuesWithNulls(): void
    {
        $values = [
            '_utc' => null,
            '_local' => null,
            '_timezone' => null
        ];

        $result = $this->mapping->convertFromMultipleDatabaseValues($values);
        $this->assertNull($result);
    }

    public function testGetMultiColumnSQLDeclarations(): void
    {
        $declarations = $this->mapping->getMultiColumnSQLDeclarations();

        $this->assertIsArray($declarations);
        $this->assertArrayHasKey('_utc', $declarations);
        $this->assertArrayHasKey('_local', $declarations);
        $this->assertArrayHasKey('_timezone', $declarations);
        
        $this->assertEquals('DATETIME', $declarations['_utc']);
        $this->assertEquals('DATETIME', $declarations['_local']);
        $this->assertEquals('VARCHAR(50)', $declarations['_timezone']);
    }

    public function testGetSQLDeclaration(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('getSQLDeclaration() is not supported for multi-column types. Use getMultiColumnSQLDeclarations() instead.');

        $this->mapping->getSQLDeclaration();
    }

    public function testWithNullableMapping(): void
    {
        $nullableMapping = new MultiColumnFieldMapping(
            fieldName: 'optionalDate',
            baseColumnName: 'optional_date',
            columnPostfixes: ['_utc', '_local', '_timezone'],
            type: 'datetime_tz',
            nullable: true,
            typeRegistry: $this->typeRegistry
        );

        $this->assertTrue($nullableMapping->isNullable());
    }

    public function testWithUniqueMapping(): void
    {
        $uniqueMapping = new MultiColumnFieldMapping(
            fieldName: 'uniqueDate',
            baseColumnName: 'unique_date',
            columnPostfixes: ['_utc', '_local', '_timezone'],
            type: 'datetime_tz',
            unique: true,
            typeRegistry: $this->typeRegistry
        );

        $this->assertTrue($uniqueMapping->isUnique());
    }

    public function testWithIdentifierMapping(): void
    {
        $identifierMapping = new MultiColumnFieldMapping(
            fieldName: 'id',
            baseColumnName: 'id',
            columnPostfixes: ['_utc', '_local', '_timezone'],
            type: 'datetime_tz',
            isIdentifier: true,
            isGenerated: true,
            generationStrategy: 'auto',
            typeRegistry: $this->typeRegistry
        );

        $this->assertTrue($identifierMapping->isIdentifier());
        $this->assertTrue($identifierMapping->isPrimaryKey());
        $this->assertTrue($identifierMapping->isGenerated());
        $this->assertEquals('auto', $identifierMapping->getGenerationStrategy());
    }

    public function testWithLengthPrecisionScale(): void
    {
        $mappingWithOptions = new MultiColumnFieldMapping(
            fieldName: 'testField',
            baseColumnName: 'test_field',
            columnPostfixes: ['_utc', '_local', '_timezone'],
            type: 'datetime_tz',
            length: 255,
            precision: 10,
            scale: 2,
            default: 'default_value',
            typeRegistry: $this->typeRegistry
        );

        $this->assertEquals(255, $mappingWithOptions->getLength());
        $this->assertEquals(10, $mappingWithOptions->getPrecision());
        $this->assertEquals(2, $mappingWithOptions->getScale());
        $this->assertEquals('default_value', $mappingWithOptions->getDefault());
    }
}
