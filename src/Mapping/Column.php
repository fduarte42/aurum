<?php

namespace Fduarte42\Aurum\Mapping;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Column {
    public function __construct(public ?string $name = null) {}
}