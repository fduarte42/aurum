<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Fixtures;

use Fduarte42\Aurum\Attribute\Column;
use Fduarte42\Aurum\Attribute\Entity;
use Fduarte42\Aurum\Attribute\Id;
use Fduarte42\Aurum\Attribute\ManyToOne;
use Brick\Math\BigDecimal;
use Ramsey\Uuid\UuidInterface;

#[Entity(table: 'todos')]
class Todo
{
    #[Id]
    #[Column(type: 'uuid')]
    public private(set) ?UuidInterface $id = null;

    #[Column(type: 'string', length: 255)]
    public string $title = '';

    #[Column(type: 'string', nullable: true)]
    public ?string $description = null;

    #[Column(type: 'boolean')]
    public bool $completed = false {
        set {
            $this->completed = $value;
            $this->completedAt = $value ? new \DateTimeImmutable() : null;
        }
    }

    #[Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    public ?BigDecimal $priority = null;

    #[Column(type: 'datetime')]
    public \DateTimeImmutable $createdAt;

    #[Column(type: 'datetime', nullable: true)]
    public private(set) ?\DateTimeImmutable $completedAt = null;

    #[Column(type: 'uuid', nullable: true)]
    public private(set) ?UuidInterface $userId = null;

    #[ManyToOne(targetEntity: User::class, inversedBy: 'todos')]
    public ?User $user = null {
        set {
            $this->user = $value;
            $this->userId = $value?->id;
        }
    }

    public function __construct(string $title = '', ?string $description = null, ?BigDecimal $priority = null, ?\DateTimeImmutable $createdAt = null)
    {
        $this->title = $title;
        $this->description = $description;
        $this->priority = $priority;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function complete(): void
    {
        $this->completed = true;
    }

    public function reopen(): void
    {
        $this->completed = false;
    }

    // Temporary backward compatibility methods for tests
    public function getId(): ?UuidInterface { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function setCompleted(bool $completed): void { $this->completed = $completed; }
    public function setPriority(?BigDecimal $priority): void { $this->priority = $priority; }
}
