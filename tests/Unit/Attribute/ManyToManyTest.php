<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit\Attribute;

use Fduarte42\Aurum\Attribute\ManyToMany;
use PHPUnit\Framework\TestCase;

class ManyToManyTest extends TestCase
{
    public function testManyToManyAttributeCreation(): void
    {
        $attribute = new ManyToMany(
            targetEntity: 'App\\Entity\\Role',
            inversedBy: 'users',
            cascade: ['persist', 'remove'],
            fetch: ['EAGER']
        );

        $this->assertEquals('App\\Entity\\Role', $attribute->getTargetEntity());
        $this->assertEquals('users', $attribute->getInversedBy());
        $this->assertNull($attribute->getMappedBy());
        $this->assertEquals(['persist', 'remove'], $attribute->getCascade());
        $this->assertEquals(['EAGER'], $attribute->getFetch());
        $this->assertFalse($attribute->isOrphanRemoval());
        $this->assertTrue($attribute->isOwningSide());
        $this->assertFalse($attribute->isInverseSide());
    }

    public function testManyToManyInverseSide(): void
    {
        $attribute = new ManyToMany(
            targetEntity: 'App\\Entity\\User',
            mappedBy: 'roles'
        );

        $this->assertEquals('App\\Entity\\User', $attribute->getTargetEntity());
        $this->assertEquals('roles', $attribute->getMappedBy());
        $this->assertNull($attribute->getInversedBy());
        $this->assertFalse($attribute->isOwningSide());
        $this->assertTrue($attribute->isInverseSide());
    }

    public function testManyToManyWithOrphanRemoval(): void
    {
        $attribute = new ManyToMany(
            targetEntity: 'App\\Entity\\Tag',
            orphanRemoval: true
        );

        $this->assertTrue($attribute->isOrphanRemoval());
    }

    public function testManyToManyDefaults(): void
    {
        $attribute = new ManyToMany(targetEntity: 'App\\Entity\\Category');

        $this->assertEquals('App\\Entity\\Category', $attribute->getTargetEntity());
        $this->assertNull($attribute->getMappedBy());
        $this->assertNull($attribute->getInversedBy());
        $this->assertEquals([], $attribute->getCascade());
        $this->assertEquals(['LAZY'], $attribute->getFetch());
        $this->assertFalse($attribute->isOrphanRemoval());
        $this->assertTrue($attribute->isOwningSide());
    }
}
