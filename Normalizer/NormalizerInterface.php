<?php

namespace Matryoshka\Serializer\Normalizer;

/** @template-covariant T of object */
interface NormalizerInterface
{
    /**
     * @psalm-param T $object
     * @psalm-param array<string> $groups
     * @psalm-param array $context
     * @psalm-return  array
     */
    public function normalize(object $object, array $groups = [], array $context = []): array;

    /**
     * @psalm-param array<string, mixed> $data
     * @psalm-param class-string<T> $className
     * @psalm-param array<string> $groups
     * @psalm-param array $context
     * @psalm-return T
     */
    public function denormalize(array $data, string $className, array $groups = [], array $context = []): ?object;
}