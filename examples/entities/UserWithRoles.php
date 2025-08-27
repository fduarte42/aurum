<?php

declare(strict_types=1);

namespace Examples\Entity;

use Fduarte42\Aurum\Attribute\{Entity, Id, Column, ManyToMany, JoinTable, JoinColumn};

#[Entity(table: 'users_with_roles')]
class UserWithRoles
{
    #[Id]
    #[Column(type: 'uuid')]
    public private(set) ?string $id = null;

    #[ManyToMany(targetEntity: RoleEntity::class)]
    #[JoinTable(
        name: 'user_role_assignments',
        joinColumns: [new JoinColumn(name: 'user_id', referencedColumnName: 'id')],
        inverseJoinColumns: [new JoinColumn(name: 'role_id', referencedColumnName: 'id')]
    )]
    public array $roles = [];

    public function __construct(
        #[Column(type: 'string', length: 255)]
        public string $name
    ) {
    }

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
    public private(set) ?string $id = null;

    #[ManyToMany(targetEntity: UserWithRoles::class, mappedBy: 'roles')]
    public array $users = [];

    public function __construct(
        #[Column(type: 'string', length: 100)]
        public string $name
    ) {
    }
}
