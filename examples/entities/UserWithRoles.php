<?php

declare(strict_types=1);

namespace Examples\Entity;

use Fduarte42\Aurum\Attribute\{Entity, Id, Column, ManyToMany, JoinTable, JoinColumn};

#[Entity(table: 'users_with_roles')]
class UserWithRoles
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?string $id = null;

    #[Column(type: 'string', length: 255)]
    private string $name;

    #[ManyToMany(targetEntity: RoleEntity::class)]
    #[JoinTable(
        name: 'user_role_assignments',
        joinColumns: [new JoinColumn(name: 'user_id', referencedColumnName: 'id')],
        inverseJoinColumns: [new JoinColumn(name: 'role_id', referencedColumnName: 'id')]
    )]
    private array $roles = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getId(): ?string { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getRoles(): array { return $this->roles; }
    
    public function addRole(RoleEntity $role): void
    {
        if (!in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }
    }
}

#[Entity(table: 'role_entities')]
class RoleEntity
{
    #[Id]
    #[Column(type: 'uuid')]
    private ?string $id = null;

    #[Column(type: 'string', length: 100)]
    private string $name;

    #[ManyToMany(targetEntity: UserWithRoles::class, mappedBy: 'roles')]
    private array $users = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getId(): ?string { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getUsers(): array { return $this->users; }
}
