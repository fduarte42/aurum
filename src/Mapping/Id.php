<?php

namespace Fduarte42\Aurum\Mapping;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Id
{
    public function __construct(public bool $isGenerated = true) {}
}