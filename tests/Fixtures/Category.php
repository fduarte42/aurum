<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Fixtures;

use Fduarte42\Aurum\Attribute\Column;
use Fduarte42\Aurum\Attribute\Entity;
use Fduarte42\Aurum\Attribute\Id;
use Fduarte42\Aurum\Attribute\ManyToOne;
use Fduarte42\Aurum\Attribute\OneToMany;

#[Entity(table: 'categories')]
class Category
{
    #[Id(strategy: 'AUTO')]
    #[Column(type: 'integer')]
    public private(set) ?int $id = null;

    #[Column(type: 'string', length: 255)]
    public string $name = '';

    #[ManyToOne(targetEntity: Category::class, inversedBy: 'children')]
    public ?Category $parent = null;

    #[OneToMany(targetEntity: Category::class, mappedBy: 'parent')]
    public array $children = [];

    public function __construct(string $name = '', ?Category $parent = null)
    {
        $this->name = $name;
        $this->parent = $parent;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
