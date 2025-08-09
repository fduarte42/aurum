<?php

namespace Fduarte42\Aurum\Mapping;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ManyToOne
{
    public function __construct(
        public string $targetEntity,
        public ?string $inversedBy = null,
        public bool $cascade = false
    ) {
    }
}