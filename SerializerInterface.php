<?php

namespace Matryoshka\Serializer;


use Matryoshka\Serializer\Encoder\EncoderException;

/** @template-covariant T of object */
interface SerializerInterface
{
    /**
     * * Преобразует объект в строку, согласно формату $format
     * Процесс преобразования идёт в два этапа:
     * - нормализация - преобразование объекта в ассоциативный массив, подроблее @see ObjectNormalizer
     * - кодирование - преобразование ассоциативного массива в строку, поддерживаются форматы 'json', 'form'
     *
     * @psalm-param T $object
     * @psalm-param string $format 'json', 'form'
     * @psalm-param array<string> $groups группы сериализации, подробнее
     * @see ObjectNormalizer::normalize()
     * @param array $context контекст сериализации, подробнее
     * константы @see ObjectNormalizer
     * @psalm-return string
     */
    public function serialize(object $object, string $format, array $groups = [], array $context = []): string;

    /**
     * Преобразует строку в объект, согласно классу объекта $className
     * Процесс преобразования идёт в два этапа:
     * - декодирование - преобразование строки в ассоциативный массива, поддерживаются форматы json, xml, form
     * - денормализация - преобразование ассоциативного массива в объект, подроблее @see ObjectNormalizer
     *
     * @psalm-param string $data
     * @psalm-param class-string<T> $className
     * @psalm-param string $format 'json', 'xml', 'form'
     * @psalm-param array<string> $groups группы сериализации, подробнее
     * @see ObjectNormalizer::normalize()
     * @psalm-param array $context контекст сериализации, подробнее
     * константы @see ObjectNormalizer
     * @psalm-return T
     * @throws EncoderException
     */
    public function deserialize(string $data, string $className, string $format, array $groups = [], array $context = []): ?object;
}