<?php

namespace AdAstra\Serializer\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY|Attribute::TARGET_CLASS)]
class DenormalizationContext
{
    public function __construct(
        public ?Groups $groups = null,
        public ?Name $name = null,
        public ?Ignore $ignore = null,
        public ?ArrayType $arrayType = null,
        public ?Nullable $nullable = null
    ) {}
}