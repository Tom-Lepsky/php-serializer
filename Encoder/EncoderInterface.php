<?php

namespace Matryoshka\Serializer\Encoder;

interface EncoderInterface
{
    public function encode(array $data, array $context = []): string;

    public function decode(string $data, array $context = []): array;
}