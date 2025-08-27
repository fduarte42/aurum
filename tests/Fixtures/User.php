<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Fixtures;

use Fduarte42\Aurum\Attribute\Column;
use Fduarte42\Aurum\Attribute\Entity;
use Fduarte42\Aurum\Attribute\Id;
use Fduarte42\Aurum\Attribute\OneToMany;
use Ramsey\Uuid\UuidInterface;

#[Entity(table: 'users')]
class User
{
    #[Id]
    #[Column(type: 'uuid')]
    public private(set) ?UuidInterface $id = null;

    #[Column(type: 'string', length: 255, unique: true)]
    public string $email = '';

    #[Column(type: 'string', length: 255)]
    public string $name = '';

    #[Column(type: 'datetime')]
    public \DateTimeImmutable $createdAt;

    #[OneToMany(targetEntity: Todo::class, mappedBy: 'user')]
    public array $todos = [];

    public function __construct(string $email = '', string $name = '', ?\DateTimeImmutable $createdAt = null)
    {
        $this->email = $email;
        $this->name = $name;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function addTodo(Todo $todo): void
    {
        $this->todos[] = $todo;
        $todo->user = $this;
    }

    public function removeTodo(Todo $todo): void
    {
        $key = array_search($todo, $this->todos, true);
        if ($key !== false) {
            unset($this->todos[$key]);
            $todo->user = null;
        }
    }

    // Temporary backward compatibility methods for tests
    public function getId(): ?UuidInterface { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
}
