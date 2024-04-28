<?php

namespace Matryoshka\Serializer\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ArrayType
{
    public function __construct(public string $type) {}
}