<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit\Attribute;

use Fduarte42\Aurum\Attribute\JoinTable;
use Fduarte42\Aurum\Attribute\JoinColumn;
use PHPUnit\Framework\TestCase;

class JoinTableTest extends TestCase
{
    public function testJoinTableCreation(): void
    {
        $joinColumns = [
            new JoinColumn(name: 'user_id', referencedColumnName: 'id'),
        ];
        
        $inverseJoinColumns = [
            new JoinColumn(name: 'role_id', referencedColumnName: 'id'),
        ];

        $joinTable = new JoinTable(
            name: 'user_roles',
            joinColumns: $joinColumns,
            inverseJoinColumns: $inverseJoinColumns
        );

        $this->assertEquals('user_roles', $joinTable->getName());
        $this->assertEquals($joinColumns, $joinTable->getJoinColumns());
        $this->assertEquals($inverseJoinColumns, $joinTable->getInverseJoinColumns());
    }

    public function testJoinTableWithDefaults(): void
    {
        $joinTable = new JoinTable(name: 'simple_table');

        $this->assertEquals('simple_table', $joinTable->getName());
        $this->assertEquals([], $joinTable->getJoinColumns());
        $this->assertEquals([], $joinTable->getInverseJoinColumns());
    }
}
