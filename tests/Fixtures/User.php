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
    private ?UuidInterface $id = null;

    #[Column(type: 'string', length: 255, unique: true)]
    private string $email;

    #[Column(type: 'string', length: 255)]
    private string $name;

    #[Column(type: 'datetime')]
    private \DateTimeImmutable $createdAt;

    #[OneToMany(targetEntity: Todo::class, mappedBy: 'user')]
    private array $todos = [];

    public function __construct(string $email, string $name)
    {
        $this->email = $email;
        $this->name = $name;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getTodos(): array
    {
        return $this->todos;
    }

    public function addTodo(Todo $todo): void
    {
        $this->todos[] = $todo;
        $todo->setUser($this);
    }

    public function removeTodo(Todo $todo): void
    {
        $key = array_search($todo, $this->todos, true);
        if ($key !== false) {
            unset($this->todos[$key]);
            $todo->setUser(null);
        }
    }
}
