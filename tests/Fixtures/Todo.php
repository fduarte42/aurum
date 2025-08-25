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
    private ?UuidInterface $id = null;

    #[Column(type: 'string', length: 255)]
    private string $title;

    #[Column(type: 'string', nullable: true)]
    private ?string $description = null;

    #[Column(type: 'boolean')]
    private bool $completed = false;

    #[Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?BigDecimal $priority = null;

    #[Column(type: 'datetime')]
    private \DateTimeImmutable $createdAt;

    #[Column(type: 'datetime', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[Column(type: 'uuid', nullable: true)]
    private ?UuidInterface $userId = null;

    #[ManyToOne(targetEntity: User::class, inversedBy: 'todos')]
    private ?User $user = null;

    public function __construct(string $title, ?string $description = null)
    {
        $this->title = $title;
        $this->description = $description;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function isCompleted(): bool
    {
        return $this->completed;
    }

    public function setCompleted(bool $completed): void
    {
        $this->completed = $completed;
        $this->completedAt = $completed ? new \DateTimeImmutable() : null;
    }

    public function getPriority(): ?BigDecimal
    {
        return $this->priority;
    }

    public function setPriority(?BigDecimal $priority): void
    {
        $this->priority = $priority;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
        $this->userId = $user?->getId();
    }

    public function getUserId(): ?UuidInterface
    {
        return $this->userId;
    }

    public function setUserId(?UuidInterface $userId): void
    {
        $this->userId = $userId;
    }

    public function complete(): void
    {
        $this->setCompleted(true);
    }

    public function reopen(): void
    {
        $this->setCompleted(false);
    }
}
