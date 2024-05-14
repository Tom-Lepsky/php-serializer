<?php

namespace AdAstra\Serializer\Attribute;


use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Groups
{
    public function __construct(public array $groups) {}
}