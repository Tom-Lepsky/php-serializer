<?php

namespace Matryoshka\Serializer;

use Exception;
use Matryoshka\Serializer\Encoder\EncoderInterface;
use Matryoshka\Serializer\Normalizer\NormalizerInterface;

class Serializer implements SerializerInterface
{
    /**
     * @param NormalizerInterface[] $normalizers
     * @param EncoderInterface[] $encoders
     * @throws Exception
     */
    public function __construct(public array $normalizers, public array $encoders, public array $context = [])
    {
        if (empty($this->normalizers) || empty($this->encoders)) {
            throw new Exception("You should set at least one normalizer and one encoder");
        }

    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function serialize(object $object, string $format, array $groups = [], array $context = []): string
    {
        if (!array_key_exists($format, $this->encoders)) {
            throw new Exception("Can not find encoder for format \"$format\"");
        }

        if (empty($context)) {
            $context = $this->context;
        }

        $normalizer = $this->normalizers[0];
        $array = $normalizer->normalize($object, $groups, $context);
        $encoder = $this->encoders[$format];
        return $encoder->encode($array, $context);
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function deserialize(string $data, string $className, string $format, array $groups = [], array $context = []): ?object
    {
        if (!array_key_exists($format, $this->encoders)) {
            throw new Exception("Can not find encoder for format \"$format\"");
        }

        if (empty($context)) {
            $context = $this->context;
        }

        $encoder = $this->encoders[$format];
        $array = $encoder->decode($data, $context);
        $normalizer = $this->normalizers[0];
        return $normalizer->denormalize($array, $className, $groups, $context);
    }
}