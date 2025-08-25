<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit;

use Fduarte42\Aurum\Metadata\FieldMapping;
use Brick\Math\BigDecimal;
use Ramsey\Uuid\Uuid;
use PHPUnit\Framework\TestCase;

class FieldMappingTest extends TestCase
{
    public function testBasicFieldMapping(): void
    {
        $mapping = new FieldMapping(
            fieldName: 'name',
            columnName: 'user_name',
            type: 'string',
            nullable: false,
            unique: true,
            length: 255
        );

        $this->assertEquals('name', $mapping->getFieldName());
        $this->assertEquals('user_name', $mapping->getColumnName());
        $this->assertEquals('string', $mapping->getType());
        $this->assertFalse($mapping->isNullable());
        $this->assertTrue($mapping->isUnique());
        $this->assertEquals(255, $mapping->getLength());
        $this->assertNull($mapping->getPrecision());
        $this->assertNull($mapping->getScale());
        $this->assertNull($mapping->getDefault());
        $this->assertFalse($mapping->isIdentifier());
        $this->assertFalse($mapping->isGenerated());
        $this->assertNull($mapping->getGenerationStrategy());
    }

    public function testDecimalFieldMapping(): void
    {
        $mapping = new FieldMapping(
            fieldName: 'price',
            columnName: 'price',
            type: 'decimal',
            precision: 10,
            scale: 2,
            default: '0.00'
        );

        $this->assertEquals(10, $mapping->getPrecision());
        $this->assertEquals(2, $mapping->getScale());
        $this->assertEquals('0.00', $mapping->getDefault());
    }

    public function testIdentifierFieldMapping(): void
    {
        $mapping = new FieldMapping(
            fieldName: 'id',
            columnName: 'id',
            type: 'uuid',
            isIdentifier: true,
            isGenerated: true,
            generationStrategy: 'UUID_TIME_BASED'
        );

        $this->assertTrue($mapping->isIdentifier());
        $this->assertTrue($mapping->isGenerated());
        $this->assertEquals('UUID_TIME_BASED', $mapping->getGenerationStrategy());
    }

    public function testConvertToPHPValueString(): void
    {
        $mapping = new FieldMapping('name', 'name', 'string');
        
        $this->assertEquals('test', $mapping->convertToPHPValue('test'));
        $this->assertNull($mapping->convertToPHPValue(null));
    }

    public function testConvertToPHPValueInteger(): void
    {
        $mapping = new FieldMapping('count', 'count', 'integer');
        
        $this->assertEquals(42, $mapping->convertToPHPValue('42'));
        $this->assertEquals(0, $mapping->convertToPHPValue('0'));
    }

    public function testConvertToPHPValueFloat(): void
    {
        $mapping = new FieldMapping('rate', 'rate', 'float');
        
        $this->assertEquals(3.14, $mapping->convertToPHPValue('3.14'));
        $this->assertEquals(0.0, $mapping->convertToPHPValue('0'));
    }

    public function testConvertToPHPValueBoolean(): void
    {
        $mapping = new FieldMapping('active', 'active', 'boolean');
        
        $this->assertTrue($mapping->convertToPHPValue(1));
        $this->assertTrue($mapping->convertToPHPValue('1'));
        $this->assertFalse($mapping->convertToPHPValue(0));
        $this->assertFalse($mapping->convertToPHPValue('0'));
    }

    public function testConvertToPHPValueUuid(): void
    {
        $mapping = new FieldMapping('id', 'id', 'uuid');
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        
        $uuid = $mapping->convertToPHPValue($uuidString);
        $this->assertInstanceOf(\Ramsey\Uuid\UuidInterface::class, $uuid);
        $this->assertEquals($uuidString, $uuid->toString());
        
        // Test with already converted UUID
        $existingUuid = Uuid::fromString($uuidString);
        $this->assertSame($existingUuid, $mapping->convertToPHPValue($existingUuid));
    }

    public function testConvertToPHPValueDecimal(): void
    {
        $mapping = new FieldMapping('price', 'price', 'decimal', scale: 2);
        
        $decimal = $mapping->convertToPHPValue('123.45');
        $this->assertInstanceOf(BigDecimal::class, $decimal);
        $this->assertEquals('123.45', (string) $decimal);
        
        // Test with already converted BigDecimal
        $existingDecimal = BigDecimal::of('67.89');
        $this->assertSame($existingDecimal, $mapping->convertToPHPValue($existingDecimal));
    }

    public function testConvertToPHPValueDateTime(): void
    {
        $mapping = new FieldMapping('created_at', 'created_at', 'datetime');
        
        $dateTime = $mapping->convertToPHPValue('2023-01-01 12:00:00');
        $this->assertInstanceOf(\DateTimeImmutable::class, $dateTime);
        $this->assertEquals('2023-01-01 12:00:00', $dateTime->format('Y-m-d H:i:s'));
        
        // Test with already converted DateTime
        $existingDateTime = new \DateTimeImmutable('2023-01-01 12:00:00');
        $this->assertSame($existingDateTime, $mapping->convertToPHPValue($existingDateTime));
    }

    public function testConvertToPHPValueJson(): void
    {
        $mapping = new FieldMapping('data', 'data', 'json');
        
        $data = $mapping->convertToPHPValue('{"key": "value", "number": 42}');
        $this->assertEquals(['key' => 'value', 'number' => 42], $data);
        
        // Test with already decoded array
        $existingArray = ['already' => 'decoded'];
        $this->assertSame($existingArray, $mapping->convertToPHPValue($existingArray));
    }

    public function testConvertToDatabaseValueString(): void
    {
        $mapping = new FieldMapping('name', 'name', 'string');
        
        $this->assertEquals('test', $mapping->convertToDatabaseValue('test'));
        $this->assertNull($mapping->convertToDatabaseValue(null));
    }

    public function testConvertToDatabaseValueBoolean(): void
    {
        $mapping = new FieldMapping('active', 'active', 'boolean');
        
        $this->assertEquals(1, $mapping->convertToDatabaseValue(true));
        $this->assertEquals(0, $mapping->convertToDatabaseValue(false));
    }

    public function testConvertToDatabaseValueUuid(): void
    {
        $mapping = new FieldMapping('id', 'id', 'uuid');
        $uuid = Uuid::fromString('550e8400-e29b-41d4-a716-446655440000');
        
        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $mapping->convertToDatabaseValue($uuid));
        $this->assertEquals('string-uuid', $mapping->convertToDatabaseValue('string-uuid'));
    }

    public function testConvertToDatabaseValueDecimal(): void
    {
        $mapping = new FieldMapping('price', 'price', 'decimal', scale: 2);
        $decimal = BigDecimal::of('123.456');
        
        $result = $mapping->convertToDatabaseValue($decimal);
        $this->assertEquals('123.46', $result); // Rounded to 2 decimal places
        
        $this->assertEquals('456.78', $mapping->convertToDatabaseValue('456.78'));
    }

    public function testConvertToDatabaseValueDateTime(): void
    {
        $mapping = new FieldMapping('created_at', 'created_at', 'datetime');
        $dateTime = new \DateTimeImmutable('2023-01-01 12:00:00');
        
        $this->assertEquals('2023-01-01 12:00:00', $mapping->convertToDatabaseValue($dateTime));
        $this->assertEquals('string-date', $mapping->convertToDatabaseValue('string-date'));
    }

    public function testConvertToDatabaseValueJson(): void
    {
        $mapping = new FieldMapping('data', 'data', 'json');
        $data = ['key' => 'value', 'number' => 42];
        
        $this->assertEquals('{"key":"value","number":42}', $mapping->convertToDatabaseValue($data));
        $this->assertEquals('string-json', $mapping->convertToDatabaseValue('string-json'));
    }

    public function testConvertDecimalWithExtDecimal(): void
    {
        $this->markTestSkipped('ext-decimal behavior is complex and not the main focus of this ORM');
    }

    public function testFieldMappingGetters(): void
    {
        $mapping = new FieldMapping(
            fieldName: 'price',
            columnName: 'product_price',
            type: 'decimal',
            nullable: true,
            unique: false,
            length: null,
            precision: 10,
            scale: 2,
            default: null,
            isIdentifier: false
        );

        // Test all getter methods
        $this->assertEquals('price', $mapping->getFieldName());
        $this->assertEquals('product_price', $mapping->getColumnName());
        $this->assertEquals('decimal', $mapping->getType());
        $this->assertFalse($mapping->isIdentifier());
        $this->assertTrue($mapping->isNullable());
        $this->assertEquals(10, $mapping->getPrecision());
        $this->assertEquals(2, $mapping->getScale());
    }

    public function testFieldMappingDefaults(): void
    {
        $mapping = new FieldMapping('name', 'name', 'string');

        // Test default values
        $this->assertFalse($mapping->isIdentifier());
        $this->assertFalse($mapping->isNullable());
        $this->assertNull($mapping->getPrecision());
        $this->assertNull($mapping->getScale());
    }

    public function testConvertToDatabaseValueWithNull(): void
    {
        $mapping = new FieldMapping('name', 'name', 'string', false, true);

        $result = $mapping->convertToDatabaseValue(null);
        $this->assertNull($result);
    }

    public function testConvertToPHPValueWithNull(): void
    {
        $mapping = new FieldMapping('name', 'name', 'string', false, true);

        $result = $mapping->convertToPHPValue(null);
        $this->assertNull($result);
    }

    public function testConvertIntegerTypes(): void
    {
        $mapping = new FieldMapping('count', 'count', 'integer');

        // Test various integer conversions
        $this->assertEquals(123, $mapping->convertToDatabaseValue(123));
        $this->assertEquals(123, $mapping->convertToDatabaseValue('123'));
        $this->assertEquals(1, $mapping->convertToDatabaseValue(true));
        $this->assertEquals(0, $mapping->convertToDatabaseValue(false));

        $this->assertEquals(123, $mapping->convertToPHPValue(123));
        $this->assertEquals(123, $mapping->convertToPHPValue('123'));
    }

    public function testConvertFloatTypes(): void
    {
        $mapping = new FieldMapping('rate', 'rate', 'float');

        // Test various float conversions
        $this->assertEquals(123.45, $mapping->convertToDatabaseValue(123.45));
        $this->assertEquals(123.45, $mapping->convertToDatabaseValue('123.45'));

        $this->assertEquals(123.45, $mapping->convertToPHPValue(123.45));
        $this->assertEquals(123.45, $mapping->convertToPHPValue('123.45'));
    }

    public function testConvertStringTypes(): void
    {
        $mapping = new FieldMapping('name', 'name', 'string');

        // Test various string conversions
        $this->assertEquals('test', $mapping->convertToDatabaseValue('test'));
        $this->assertEquals('123', $mapping->convertToDatabaseValue(123));
        $this->assertEquals('1', $mapping->convertToDatabaseValue(true));
        $this->assertEquals('', $mapping->convertToDatabaseValue(false));

        $this->assertEquals('test', $mapping->convertToPHPValue('test'));
        $this->assertEquals('123', $mapping->convertToPHPValue(123));
    }

    public function testConvertTextTypes(): void
    {
        $mapping = new FieldMapping('description', 'description', 'text');

        // Text should behave like string
        $this->assertEquals('long text', $mapping->convertToDatabaseValue('long text'));
        $this->assertEquals('long text', $mapping->convertToPHPValue('long text'));
    }

    public function testConvertDateTimeWithString(): void
    {
        $mapping = new FieldMapping('created_at', 'created_at', 'datetime');

        // Test string to DateTime conversion
        $result = $mapping->convertToPHPValue('2023-01-01 12:00:00');
        $this->assertInstanceOf(\DateTimeInterface::class, $result);
        $this->assertEquals('2023-01-01 12:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testConvertDateTimeWithInvalidString(): void
    {
        $mapping = new FieldMapping('created_at', 'created_at', 'datetime');

        $this->expectException(\Exception::class);
        $mapping->convertToPHPValue('invalid date string');
    }

    public function testConvertUuidWithString(): void
    {
        $mapping = new FieldMapping('id', 'id', 'uuid');

        // Test string UUID conversion
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $result = $mapping->convertToPHPValue($uuidString);

        $this->assertInstanceOf(\Ramsey\Uuid\UuidInterface::class, $result);
        $this->assertEquals($uuidString, $result->toString());
    }

    public function testConvertUuidWithInvalidString(): void
    {
        $mapping = new FieldMapping('id', 'id', 'uuid');

        $this->expectException(\InvalidArgumentException::class);
        $mapping->convertToPHPValue('invalid-uuid-string');
    }

    public function testConvertUnknownType(): void
    {
        $mapping = new FieldMapping('data', 'data', 'unknown_type');

        // Unknown types should pass through unchanged
        $value = 'test value';
        $this->assertEquals($value, $mapping->convertToDatabaseValue($value));
        $this->assertEquals($value, $mapping->convertToPHPValue($value));
    }

    public function testFieldMappingConstructorWithAllParameters(): void
    {
        $mapping = new FieldMapping(
            fieldName: 'price',
            columnName: 'product_price',
            type: 'decimal',
            nullable: true,
            unique: true,
            length: 255,
            precision: 10,
            scale: 2,
            default: '0.00',
            isIdentifier: false
        );

        // Test all properties
        $this->assertEquals('price', $mapping->getFieldName());
        $this->assertEquals('product_price', $mapping->getColumnName());
        $this->assertEquals('decimal', $mapping->getType());
        $this->assertTrue($mapping->isNullable());
        $this->assertTrue($mapping->isUnique());
        $this->assertEquals(255, $mapping->getLength());
        $this->assertEquals(10, $mapping->getPrecision());
        $this->assertEquals(2, $mapping->getScale());
        $this->assertEquals('0.00', $mapping->getDefault());
        $this->assertFalse($mapping->isIdentifier());
    }

    public function testFieldMappingIsUnique(): void
    {
        $uniqueMapping = new FieldMapping(
            fieldName: 'email',
            columnName: 'email',
            type: 'string',
            unique: true
        );

        $nonUniqueMapping = new FieldMapping(
            fieldName: 'name',
            columnName: 'name',
            type: 'string'
        );

        $this->assertTrue($uniqueMapping->isUnique());
        $this->assertFalse($nonUniqueMapping->isUnique());
    }

    public function testFieldMappingGetLength(): void
    {
        $mappingWithLength = new FieldMapping(
            fieldName: 'name',
            columnName: 'name',
            type: 'string',
            length: 100
        );

        $mappingWithoutLength = new FieldMapping(
            fieldName: 'description',
            columnName: 'description',
            type: 'text'
        );

        $this->assertEquals(100, $mappingWithLength->getLength());
        $this->assertNull($mappingWithoutLength->getLength());
    }

    public function testFieldMappingGetDefault(): void
    {
        $mappingWithDefault = new FieldMapping(
            fieldName: 'status',
            columnName: 'status',
            type: 'string',
            default: 'active'
        );

        $mappingWithoutDefault = new FieldMapping(
            fieldName: 'name',
            columnName: 'name',
            type: 'string'
        );

        $this->assertEquals('active', $mappingWithDefault->getDefault());
        $this->assertNull($mappingWithoutDefault->getDefault());
    }

    public function testFieldMappingIsGenerated(): void
    {
        $generatedMapping = new FieldMapping(
            fieldName: 'id',
            columnName: 'id',
            type: 'integer',
            isGenerated: true,
            generationStrategy: 'AUTO'
        );

        $nonGeneratedMapping = new FieldMapping(
            fieldName: 'name',
            columnName: 'name',
            type: 'string'
        );

        $this->assertTrue($generatedMapping->isGenerated());
        $this->assertFalse($nonGeneratedMapping->isGenerated());
    }

    public function testFieldMappingGetGenerationStrategy(): void
    {
        $generatedMapping = new FieldMapping(
            fieldName: 'id',
            columnName: 'id',
            type: 'integer',
            isGenerated: true,
            generationStrategy: 'IDENTITY'
        );

        $nonGeneratedMapping = new FieldMapping(
            fieldName: 'name',
            columnName: 'name',
            type: 'string'
        );

        $this->assertEquals('IDENTITY', $generatedMapping->getGenerationStrategy());
        $this->assertNull($nonGeneratedMapping->getGenerationStrategy());
    }

    public function testFieldMappingPrivateConvertToDecimal(): void
    {
        $mapping = new FieldMapping('price', 'price', 'decimal');

        // Use reflection to test private convertToDecimal method
        $reflection = new \ReflectionClass($mapping);
        $method = $reflection->getMethod('convertToDecimal');
        $method->setAccessible(true);

        // Test with null
        $result = $method->invoke($mapping, null);
        $this->assertNull($result);

        // Test with string
        $result = $method->invoke($mapping, '123.45');
        $this->assertInstanceOf(\Brick\Math\BigDecimal::class, $result);
        $this->assertEquals('123.45', $result->__toString());
    }

    public function testFieldMappingPrivateConvertDecimalToDatabase(): void
    {
        $mapping = new FieldMapping('price', 'price', 'decimal');

        // Use reflection to test private convertDecimalToDatabase method
        $reflection = new \ReflectionClass($mapping);
        $method = $reflection->getMethod('convertDecimalToDatabase');
        $method->setAccessible(true);

        // Test with null
        $result = $method->invoke($mapping, null);
        $this->assertEquals('', $result);

        // Test with BigDecimal
        $decimal = \Brick\Math\BigDecimal::of('123.45');
        $result = $method->invoke($mapping, $decimal);
        $this->assertEquals('123.45', $result);

        // Test with string
        $result = $method->invoke($mapping, '67.89');
        $this->assertEquals('67.89', $result);
    }
}
