<?php

namespace AdAstra\Serializer\Encoder;

interface EncoderInterface
{
    public function encode(array $data, array $context = []): string;

    public function decode(string $data, array $context = []): array;
}