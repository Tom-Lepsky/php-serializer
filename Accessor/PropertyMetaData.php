<?php

namespace AdAstra\Serializer\Accessor;

use ArrayAccess, Iterator;
use AdAstra\Serializer\Attribute\{ArrayType, Nullable};
use ReflectionClass;
use ReflectionException;

class PropertyMetaData
{
    public ?string $arrayType = null;

    protected bool $isEnum = false;

    public ?string $type = null;

    public mixed $value = null;

    public array $attributes = [];

    public function __construct(protected object $object, public string $name)
    {
        $accessor = new PropertyAccessor();
        $this->type = $accessor->getType($object, $name);
        $this->isEnum = $accessor->isEnum($this->type);
        $this->attributes = $accessor->getPropertyAttributes($object, $name);
        if (array_key_exists(ArrayType::class, $this->attributes)) {
            $this->arrayType = $this->attributes[ArrayType::class]['type'];
        }
    }

    public function isArrayOfObjects(): bool
    {
        $isArrayType = fn(): bool => null !== $this->arrayType && class_exists($this->arrayType);
        if (null === $this->type) {
            return $isArrayType();
        }

        if ($this->isObjectAsArray()) {
            return $isArrayType();
        }

        if (in_array($this->type, ['array', 'iterator', 'iterable', 'mixed']) && $isArrayType()) {
            return true;
        }

        return false;
    }

    public function isObject(): bool
    {
        return is_object($this->value) || $this->type === 'object' || class_exists($this->type);
    }

    public function isNullable(): bool
    {
        return array_key_exists(Nullable::class, $this->attributes);
    }

    public function isEnum(): bool
    {
        return $this->isEnum;
    }

    public function isArray(): bool
    {
        return is_array($this->value) || $this->type === 'array' || $this->isObjectAsArray();
    }

    public function isObjectAsArray(): bool
    {
        return is_a($this->type, ArrayAccess::class, true) && is_a($this->type, Iterator::class, true);
    }

    public function isInternal(): bool
    {
        try {
            return (new ReflectionClass($this->type))->isInternal();
        } catch (ReflectionException) {
            return false;
        }
    }

    /**
     * @throws ReflectionException
     * @throws AccessorException
     */
    public function getObjectArrayInstance(): ArrayAccess & Iterator
    {
        if ($this->isObjectAsArray()) {
            return (new ReflectionClass($this->type))->newInstance();
        }
        throw new AccessorException("object should implements " . ArrayAccess::class . " and " . Iterator::class);
    }
}