<?php

namespace Matryoshka\Serializer\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Name
{
    public function __construct(public string $name) {}
}