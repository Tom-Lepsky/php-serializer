<?php

namespace Matryoshka\Serializer\Accessor;

use Matryoshka\Serializer\Normalizer\ObjectNormalizer;
use Matryoshka\Serializer\SerializerException;
use ReflectionClass, ReflectionException, ReflectionProperty, Reflector, ReflectionMethod, ReflectionParameter;

class PropertyAccessor
{
    /**
     * @throws ReflectionException|SerializerException
     */
    public function getValue(object $object, ReflectionProperty $property, array $context = []): mixed
    {
        if ($property->isInitialized($object)) {
            if ($accessMethod = $this->retrievePropertyAccessMethod($property, true)) {
                return $accessMethod->invoke($object);
            } elseif ($this->isAccessible($property, $context)) {
                return $property->getValue($object);
            }
        } elseif ($property->hasDefaultValue() && $this->isAccessible($property, $context)) {
            return $property->getDefaultValue();
        }

        throw new SerializerException("Couldn't get value of $" . $property->getName() . " from " . $object::class . ", perhaps it's not initialized");
    }

    /**
     * @throws ReflectionException|SerializerException
     */
    public function setValue(mixed $value, object $object, ReflectionProperty $property, array $context = []): void
    {
        if (($accessMethod = $this->retrievePropertyAccessMethod($property, false)) && 0 < $accessMethod->getNumberOfParameters()) {
            $parameter = $accessMethod->getParameters()[0];
            if (!$this->isCompatibleTypes($value, $parameter, $context)) {
                throw new SerializerException("Parameter's type \"" . $parameter->getType()->getName() . "\" \$" . $parameter->getName() . " in method " . $object::class . "::" . $accessMethod->getName() . "() incompatible with given value's type \"" . gettype($value) . "\"");
            }
            $accessMethod->invoke($object,$value);
        } elseif ($this->isAccessible($property, $context)) {
            if (!$this->isCompatibleTypes($value, $property, $context)) {
                throw new SerializerException("Parameter's type \"" . $property->getType()->getName() . "\" \$" . $property->getName() . " incompatible with given value's type " . gettype($value) . "\"");
            }
            $property->setValue($object, $value);
        }
    }

    public function getType(object $object, string $name): string
    {
        try {
            $property = new ReflectionProperty($object, $name);
            if (null !== ($type = $property->getType()) && $type->getName() !== 'mixed') {
                return $type->getName();
            } elseif ($property->isInitialized($object)) {
                $val = $property->getValue($object);
                return gettype($val);
            }
        } catch (ReflectionException) {}
        return 'mixed';
    }

    public function isEnum(string $type): bool
    {
        try {
            return (new ReflectionClass($type))->isEnum();
        } catch (ReflectionException) {
            return false;
        }
    }

    protected function isCompatibleTypes(mixed $value, Reflector $reflector, array $context = []): bool
    {
        if (!is_a($reflector, ReflectionProperty::class) && !is_a($reflector, ReflectionParameter::class)) {
            return false;
        }

        if (null === ($type = $reflector->getType())) {
            return true;
        }

        if (is_object($value) && is_a($value, $type->getName())) {
            return true;
        }
        if (null === $value && $type->allowsNull()) {
            return true;
        }

        if (in_array($typeName = $type->getName(), ['array', 'iterator', 'mixed']) && is_array($value)) {
            return true;
        }

        $castTypes = ['int', 'integer', 'float', 'double', 'bool', 'boolean', 'string'];
        if (in_array(ObjectNormalizer::STRICT_TYPES, $context)) {
            return gettype($value) === $typeName;
        } elseif (in_array(gettype($value), $castTypes) && in_array($typeName, $castTypes)) {
            return true;
        }

        return false;
    }

    protected function isAccessible(ReflectionProperty $property, array $context = []): bool
    {
        return $property->isPublic() ||
            ($property->isProtected() && in_array(ObjectNormalizer::INCLUDE_PROTECTED, $context)) ||
            ($property->isPrivate() && in_array(ObjectNormalizer::INCLUDE_PRIVATE, $context));
    }

    public function getPropertyAttributes(object $object, string $name): array
    {
        try {
            $reflector = new ReflectionProperty($object, $name);
        } catch (ReflectionException) {
            return [];
        }
        return $this->getAttributes($reflector);
    }

    public function getAttributes(Reflector $reflector): array
    {
        $attributes = [];
        if ($reflector instanceof ReflectionProperty || $reflector instanceof ReflectionClass) {
            foreach ($reflector->getAttributes() as $attr) {
                $attributes[$attr->getName()] = $this->iterateAttributes($attr->newInstance());
            }
        }
        return $attributes;
    }

    protected function iterateAttributes(object $object): array
    {
        $reflector = new ReflectionClass($object);
        $args = [];
        foreach ($reflector->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (null === ($val = $property->getValue($object))) {
                continue;
            } elseif (is_object($val)) {
                $args[$val::class] = $this->iterateAttributes($val);
            } else {
                $args[$property->getName()] = $val;
            }
        }
        return $args;
    }

    protected function retrievePropertyAccessMethod(ReflectionProperty $property, bool $getter): ?ReflectionMethod
    {
        $reflectionObject = $property->getDeclaringClass();
        $accessMethodName = $getter ? 'get' : 'set';
        $accessMethodName .= ucfirst($property->getName());
        return $reflectionObject->hasMethod($accessMethodName) &&
        ($accessMethod = $reflectionObject->getMethod($accessMethodName)) &&
        $accessMethod->isPublic() ? $accessMethod : null;
    }
}