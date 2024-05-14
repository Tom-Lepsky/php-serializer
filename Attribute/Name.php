<?php

namespace AdAstra\Serializer\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Name
{
    public function __construct(public string $name) {}
}