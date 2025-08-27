<?php

declare(strict_types=1);

namespace Tests\Unit\Metadata;

use Fduarte42\Aurum\Attribute\InheritanceType;
use Fduarte42\Aurum\Metadata\InheritanceMapping;
use PHPUnit\Framework\TestCase;

class InheritanceMappingTest extends TestCase
{
    private InheritanceMapping $rootMapping;
    private InheritanceMapping $childMapping;

    protected function setUp(): void
    {
        $this->rootMapping = new InheritanceMapping(
            strategy: InheritanceType::SINGLE_TABLE,
            discriminatorColumn: 'dtype',
            discriminatorType: 'string',
            discriminatorLength: 255,
            rootClassName: 'App\\Entity\\Vehicle',
            parentClassName: null
        );

        $this->childMapping = new InheritanceMapping(
            strategy: InheritanceType::SINGLE_TABLE,
            discriminatorColumn: 'dtype',
            discriminatorType: 'string',
            discriminatorLength: 255,
            rootClassName: 'App\\Entity\\Vehicle',
            parentClassName: 'App\\Entity\\Vehicle'
        );
    }

    public function testGetStrategy(): void
    {
        $this->assertEquals(InheritanceType::SINGLE_TABLE, $this->rootMapping->getStrategy());
        $this->assertEquals(InheritanceType::SINGLE_TABLE, $this->childMapping->getStrategy());
    }

    public function testGetDiscriminatorColumn(): void
    {
        $this->assertEquals('dtype', $this->rootMapping->getDiscriminatorColumn());
        $this->assertEquals('dtype', $this->childMapping->getDiscriminatorColumn());
    }

    public function testGetDiscriminatorType(): void
    {
        $this->assertEquals('string', $this->rootMapping->getDiscriminatorType());
        $this->assertEquals('string', $this->childMapping->getDiscriminatorType());
    }

    public function testGetDiscriminatorLength(): void
    {
        $this->assertEquals(255, $this->rootMapping->getDiscriminatorLength());
        $this->assertEquals(255, $this->childMapping->getDiscriminatorLength());
    }

    public function testIsRootClass(): void
    {
        $this->assertTrue($this->rootMapping->isRootClass());
        $this->assertFalse($this->childMapping->isRootClass());
    }

    public function testGetRootClassName(): void
    {
        $this->assertEquals('App\\Entity\\Vehicle', $this->rootMapping->getRootClassName());
        $this->assertEquals('App\\Entity\\Vehicle', $this->childMapping->getRootClassName());
    }

    public function testGetParentClassName(): void
    {
        $this->assertNull($this->rootMapping->getParentClassName());
        $this->assertEquals('App\\Entity\\Vehicle', $this->childMapping->getParentClassName());
    }

    public function testGetChildClassNames(): void
    {
        $this->assertEmpty($this->rootMapping->getChildClassNames());
        $this->assertEmpty($this->childMapping->getChildClassNames());
    }

    public function testAddChildClass(): void
    {
        $this->rootMapping->addChildClass('App\\Entity\\Car');
        $this->rootMapping->addChildClass('App\\Entity\\Motorcycle');

        $childClasses = $this->rootMapping->getChildClassNames();
        $this->assertCount(2, $childClasses);
        $this->assertContains('App\\Entity\\Car', $childClasses);
        $this->assertContains('App\\Entity\\Motorcycle', $childClasses);
    }

    public function testAddChildClassDuplicates(): void
    {
        $this->rootMapping->addChildClass('App\\Entity\\Car');
        $this->rootMapping->addChildClass('App\\Entity\\Car'); // Duplicate

        $childClasses = $this->rootMapping->getChildClassNames();
        $this->assertCount(1, $childClasses);
        $this->assertContains('App\\Entity\\Car', $childClasses);
    }

    public function testGetDiscriminatorValue(): void
    {
        $this->assertEquals('App\\Entity\\Vehicle', $this->rootMapping->getDiscriminatorValue('App\\Entity\\Vehicle'));
        $this->assertEquals('App\\Entity\\Car', $this->rootMapping->getDiscriminatorValue('App\\Entity\\Car'));
    }

    public function testGetClassNameForDiscriminatorValue(): void
    {
        $this->rootMapping->addChildClass('App\\Entity\\Car');

        $this->assertEquals('App\\Entity\\Vehicle', $this->rootMapping->getClassNameForDiscriminatorValue('App\\Entity\\Vehicle'));
        $this->assertEquals('App\\Entity\\Car', $this->rootMapping->getClassNameForDiscriminatorValue('App\\Entity\\Car'));
        
        // Test fallback for unknown discriminator value
        $this->assertEquals('Unknown\\Class', $this->rootMapping->getClassNameForDiscriminatorValue('Unknown\\Class'));
    }

    public function testGetDiscriminatorMap(): void
    {
        $this->rootMapping->addChildClass('App\\Entity\\Car');
        $this->rootMapping->addChildClass('App\\Entity\\Motorcycle');

        $discriminatorMap = $this->rootMapping->getDiscriminatorMap();
        
        $this->assertArrayHasKey('App\\Entity\\Vehicle', $discriminatorMap);
        $this->assertArrayHasKey('App\\Entity\\Car', $discriminatorMap);
        $this->assertArrayHasKey('App\\Entity\\Motorcycle', $discriminatorMap);
        
        $this->assertEquals('App\\Entity\\Vehicle', $discriminatorMap['App\\Entity\\Vehicle']);
        $this->assertEquals('App\\Entity\\Car', $discriminatorMap['App\\Entity\\Car']);
        $this->assertEquals('App\\Entity\\Motorcycle', $discriminatorMap['App\\Entity\\Motorcycle']);
    }

    public function testIsInHierarchy(): void
    {
        $this->rootMapping->addChildClass('App\\Entity\\Car');
        $this->rootMapping->addChildClass('App\\Entity\\Motorcycle');

        $this->assertTrue($this->rootMapping->isInHierarchy('App\\Entity\\Vehicle'));
        $this->assertTrue($this->rootMapping->isInHierarchy('App\\Entity\\Car'));
        $this->assertTrue($this->rootMapping->isInHierarchy('App\\Entity\\Motorcycle'));
        $this->assertFalse($this->rootMapping->isInHierarchy('App\\Entity\\User'));
    }

    public function testGetAllClassNames(): void
    {
        $this->rootMapping->addChildClass('App\\Entity\\Car');
        $this->rootMapping->addChildClass('App\\Entity\\Motorcycle');

        $allClasses = $this->rootMapping->getAllClassNames();
        
        $this->assertCount(3, $allClasses);
        $this->assertContains('App\\Entity\\Vehicle', $allClasses);
        $this->assertContains('App\\Entity\\Car', $allClasses);
        $this->assertContains('App\\Entity\\Motorcycle', $allClasses);
        
        // Root class should be first
        $this->assertEquals('App\\Entity\\Vehicle', $allClasses[0]);
    }

    public function testIsChildClass(): void
    {
        $this->rootMapping->addChildClass('App\\Entity\\Car');
        $this->rootMapping->addChildClass('App\\Entity\\Motorcycle');

        $this->assertFalse($this->rootMapping->isChildClass('App\\Entity\\Vehicle'));
        $this->assertTrue($this->rootMapping->isChildClass('App\\Entity\\Car'));
        $this->assertTrue($this->rootMapping->isChildClass('App\\Entity\\Motorcycle'));
        $this->assertFalse($this->rootMapping->isChildClass('App\\Entity\\User'));
    }

    public function testRootClassIncludedInDiscriminatorMap(): void
    {
        // Root class should be automatically included in discriminator map
        $discriminatorMap = $this->rootMapping->getDiscriminatorMap();
        
        $this->assertArrayHasKey('App\\Entity\\Vehicle', $discriminatorMap);
        $this->assertEquals('App\\Entity\\Vehicle', $discriminatorMap['App\\Entity\\Vehicle']);
    }

    public function testInheritanceMappingWithCustomDiscriminatorColumn(): void
    {
        $customMapping = new InheritanceMapping(
            strategy: InheritanceType::SINGLE_TABLE,
            discriminatorColumn: 'entity_type',
            discriminatorType: 'string',
            discriminatorLength: 100,
            rootClassName: 'App\\Entity\\Animal',
            parentClassName: null
        );

        $this->assertEquals('entity_type', $customMapping->getDiscriminatorColumn());
        $this->assertEquals(100, $customMapping->getDiscriminatorLength());
        $this->assertEquals('App\\Entity\\Animal', $customMapping->getRootClassName());
    }

    public function testInheritanceMappingWithJoinedStrategy(): void
    {
        $joinedMapping = new InheritanceMapping(
            strategy: InheritanceType::JOINED,
            discriminatorColumn: 'dtype',
            discriminatorType: 'string',
            discriminatorLength: 255,
            rootClassName: 'App\\Entity\\Person',
            parentClassName: null
        );

        $this->assertEquals(InheritanceType::JOINED, $joinedMapping->getStrategy());
        $this->assertTrue($joinedMapping->isRootClass());
    }

    public function testChildMappingInheritanceProperties(): void
    {
        $this->assertEquals(InheritanceType::SINGLE_TABLE, $this->childMapping->getStrategy());
        $this->assertEquals('dtype', $this->childMapping->getDiscriminatorColumn());
        $this->assertEquals('App\\Entity\\Vehicle', $this->childMapping->getRootClassName());
        $this->assertEquals('App\\Entity\\Vehicle', $this->childMapping->getParentClassName());
        $this->assertFalse($this->childMapping->isRootClass());
    }
}
