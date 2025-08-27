<?php

declare(strict_types=1);

namespace Tests\Integration;

use Fduarte42\Aurum\Attribute\Column;
use Fduarte42\Aurum\Attribute\Entity;
use Fduarte42\Aurum\Attribute\Id;
use Fduarte42\Aurum\Metadata\MetadataFactory;
use Fduarte42\Aurum\Metadata\MultiColumnFieldMapping;
use Fduarte42\Aurum\Type\TypeRegistry;
use PHPUnit\Framework\TestCase;

#[Entity(table: 'events')]
class TestEvent
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?string $id = null;

    #[Column(type: 'string', length: 255)]
    private string $name;

    #[Column(type: 'datetime_tz')]
    private ?\DateTimeImmutable $scheduledAt = null;

    #[Column(type: 'datetime_tz', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getScheduledAt(): ?\DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?\DateTimeImmutable $scheduledAt): void
    {
        $this->scheduledAt = $scheduledAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): void
    {
        $this->completedAt = $completedAt;
    }
}

class MultiColumnFieldMappingIntegrationTest extends TestCase
{
    private MetadataFactory $metadataFactory;
    private TypeRegistry $typeRegistry;

    protected function setUp(): void
    {
        $this->typeRegistry = new TypeRegistry();
        $this->metadataFactory = new MetadataFactory($this->typeRegistry);
    }

    public function testMetadataFactoryCreatesMultiColumnMappings(): void
    {
        $metadata = $this->metadataFactory->getMetadataFor(TestEvent::class);

        // Check that we have the expected field mappings
        $fieldMappings = $metadata->getFieldMappings();
        $this->assertArrayHasKey('id', $fieldMappings);
        $this->assertArrayHasKey('name', $fieldMappings);
        $this->assertArrayHasKey('scheduledAt', $fieldMappings);
        $this->assertArrayHasKey('completedAt', $fieldMappings);

        // Check that datetime_tz fields use multi-column mappings
        $scheduledAtMapping = $fieldMappings['scheduledAt'];
        $this->assertInstanceOf(MultiColumnFieldMapping::class, $scheduledAtMapping);
        $this->assertTrue($scheduledAtMapping->isMultiColumn());

        $completedAtMapping = $fieldMappings['completedAt'];
        $this->assertInstanceOf(MultiColumnFieldMapping::class, $completedAtMapping);
        $this->assertTrue($completedAtMapping->isMultiColumn());

        // Check that regular fields use single-column mappings
        $idMapping = $fieldMappings['id'];
        $this->assertFalse($idMapping->isMultiColumn());

        $nameMapping = $fieldMappings['name'];
        $this->assertFalse($nameMapping->isMultiColumn());
    }

    public function testMultiColumnFieldMappingColumnNames(): void
    {
        $metadata = $this->metadataFactory->getMetadataFor(TestEvent::class);
        $scheduledAtMapping = $metadata->getFieldMapping('scheduledAt');

        $this->assertInstanceOf(MultiColumnFieldMapping::class, $scheduledAtMapping);

        // Check column names
        $expectedColumns = ['scheduled_at_utc', 'scheduled_at_local', 'scheduled_at_timezone'];
        $this->assertEquals($expectedColumns, $scheduledAtMapping->getColumnNames());

        // Check column names with postfixes
        $expectedWithPostfixes = [
            '_utc' => 'scheduled_at_utc',
            '_local' => 'scheduled_at_local',
            '_timezone' => 'scheduled_at_timezone'
        ];
        $this->assertEquals($expectedWithPostfixes, $scheduledAtMapping->getColumnNamesWithPostfixes());

        // Check base column name
        $this->assertEquals('scheduled_at', $scheduledAtMapping->getBaseColumnName());

        // Check postfixes
        $this->assertEquals(['_utc', '_local', '_timezone'], $scheduledAtMapping->getColumnPostfixes());
    }

    public function testEntityMetadataGetColumnNames(): void
    {
        $metadata = $this->metadataFactory->getMetadataFor(TestEvent::class);
        $columnNames = $metadata->getColumnNames();

        // Should include all columns from both single and multi-column mappings
        $expectedColumns = [
            'id',
            'name',
            'scheduled_at_utc',
            'scheduled_at_local',
            'scheduled_at_timezone',
            'completed_at_utc',
            'completed_at_local',
            'completed_at_timezone'
        ];

        $this->assertEquals($expectedColumns, $columnNames);
    }

    public function testEntityMetadataGetFieldName(): void
    {
        $metadata = $this->metadataFactory->getMetadataFor(TestEvent::class);

        // Test single-column mapping
        $this->assertEquals('id', $metadata->getFieldName('id'));
        $this->assertEquals('name', $metadata->getFieldName('name'));

        // Test multi-column mapping
        $this->assertEquals('scheduledAt', $metadata->getFieldName('scheduled_at_utc'));
        $this->assertEquals('scheduledAt', $metadata->getFieldName('scheduled_at_local'));
        $this->assertEquals('scheduledAt', $metadata->getFieldName('scheduled_at_timezone'));

        $this->assertEquals('completedAt', $metadata->getFieldName('completed_at_utc'));
        $this->assertEquals('completedAt', $metadata->getFieldName('completed_at_local'));
        $this->assertEquals('completedAt', $metadata->getFieldName('completed_at_timezone'));

        // Test fallback for unknown column
        $this->assertEquals('unknown_column', $metadata->getFieldName('unknown_column'));
    }

    public function testEntityMetadataFieldValueConversion(): void
    {
        $metadata = $this->metadataFactory->getMetadataFor(TestEvent::class);
        $entity = new TestEvent();

        // Test setting a datetime value
        $dateTime = new \DateTimeImmutable('2023-12-01 15:30:45', new \DateTimeZone('America/New_York'));
        $entity->setScheduledAt($dateTime);

        // Test getting field value as multiple columns
        $multiColumnValues = $metadata->getFieldValueAsMultipleColumns($entity, 'scheduledAt');
        
        $this->assertIsArray($multiColumnValues);
        $this->assertArrayHasKey('_utc', $multiColumnValues);
        $this->assertArrayHasKey('_local', $multiColumnValues);
        $this->assertArrayHasKey('_timezone', $multiColumnValues);
        
        $this->assertEquals('2023-12-01 20:30:45', $multiColumnValues['_utc']); // UTC time
        $this->assertEquals('2023-12-01 15:30:45', $multiColumnValues['_local']); // Local time
        $this->assertEquals('America/New_York', $multiColumnValues['_timezone']);

        // Test setting field value from multiple columns
        $newEntity = new TestEvent();
        $columnValues = [
            '_utc' => '2023-12-02 10:15:30',
            '_local' => '2023-12-02 05:15:30',
            '_timezone' => 'America/New_York'
        ];
        
        $metadata->setFieldValueFromMultipleColumns($newEntity, 'scheduledAt', $columnValues);
        $retrievedDateTime = $newEntity->getScheduledAt();
        
        $this->assertInstanceOf(\DateTimeImmutable::class, $retrievedDateTime);
        $this->assertEquals('2023-12-02 05:15:30', $retrievedDateTime->format('Y-m-d H:i:s'));
        $this->assertEquals('America/New_York', $retrievedDateTime->getTimezone()->getName());
    }

    public function testNullableMultiColumnField(): void
    {
        $metadata = $this->metadataFactory->getMetadataFor(TestEvent::class);
        $completedAtMapping = $metadata->getFieldMapping('completedAt');

        $this->assertInstanceOf(MultiColumnFieldMapping::class, $completedAtMapping);
        $this->assertTrue($completedAtMapping->isNullable());

        // Test null conversion
        $entity = new TestEvent();
        $multiColumnValues = $metadata->getFieldValueAsMultipleColumns($entity, 'completedAt');
        
        $this->assertIsArray($multiColumnValues);
        $this->assertNull($multiColumnValues['_utc']);
        $this->assertNull($multiColumnValues['_local']);
        $this->assertNull($multiColumnValues['_timezone']);

        // Test setting null from multiple columns
        $nullColumnValues = [
            '_utc' => null,
            '_local' => null,
            '_timezone' => null
        ];
        
        $metadata->setFieldValueFromMultipleColumns($entity, 'completedAt', $nullColumnValues);
        $this->assertNull($entity->getCompletedAt());
    }

    public function testMultiColumnSQLDeclarations(): void
    {
        $metadata = $this->metadataFactory->getMetadataFor(TestEvent::class);
        $scheduledAtMapping = $metadata->getFieldMapping('scheduledAt');

        $this->assertInstanceOf(MultiColumnFieldMapping::class, $scheduledAtMapping);

        $declarations = $scheduledAtMapping->getMultiColumnSQLDeclarations();
        
        $this->assertIsArray($declarations);
        $this->assertArrayHasKey('_utc', $declarations);
        $this->assertArrayHasKey('_local', $declarations);
        $this->assertArrayHasKey('_timezone', $declarations);
        
        $this->assertEquals('DATETIME', $declarations['_utc']);
        $this->assertEquals('DATETIME', $declarations['_local']);
        $this->assertEquals('VARCHAR(50)', $declarations['_timezone']);
    }
}
