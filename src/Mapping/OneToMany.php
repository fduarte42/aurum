<?php

namespace Fduarte42\Aurum\Mapping;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class OneToMany
{
    public function __construct(
        public string $targetEntity,
        public string $mappedBy,
        public bool $cascade = false
    ) {
    }
}