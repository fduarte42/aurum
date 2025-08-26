<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit\Attribute;

use Fduarte42\Aurum\Attribute\JoinColumn;
use PHPUnit\Framework\TestCase;

class JoinColumnTest extends TestCase
{
    public function testJoinColumnCreation(): void
    {
        $joinColumn = new JoinColumn(
            name: 'user_id',
            referencedColumnName: 'id',
            nullable: false,
            unique: true,
            onDelete: 'CASCADE',
            onUpdate: 'RESTRICT'
        );

        $this->assertEquals('user_id', $joinColumn->getName());
        $this->assertEquals('id', $joinColumn->getReferencedColumnName());
        $this->assertFalse($joinColumn->isNullable());
        $this->assertTrue($joinColumn->isUnique());
        $this->assertEquals('CASCADE', $joinColumn->getOnDelete());
        $this->assertEquals('RESTRICT', $joinColumn->getOnUpdate());
    }

    public function testJoinColumnDefaults(): void
    {
        $joinColumn = new JoinColumn(name: 'simple_id');

        $this->assertEquals('simple_id', $joinColumn->getName());
        $this->assertNull($joinColumn->getReferencedColumnName());
        $this->assertTrue($joinColumn->isNullable());
        $this->assertFalse($joinColumn->isUnique());
        $this->assertNull($joinColumn->getOnDelete());
        $this->assertNull($joinColumn->getOnUpdate());
    }
}
