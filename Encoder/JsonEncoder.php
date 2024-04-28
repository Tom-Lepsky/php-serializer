<?php

namespace Matryoshka\Serializer\Encoder;

class JsonEncoder implements EncoderInterface
{

    public function encode(array $data, array $context = []): string
    {
        $flags = $context['encoder'] ?? 0;
        return json_encode($data, $flags);
    }

    /**
     * @throws EncoderException
     */
    public function decode(string $data, array $context = []): array
    {
        $flags = $context['encoder'] ?? 0;
        $decoded = json_decode($data, true, flags: $flags);
        if (JSON_ERROR_NONE !== ($jsonErrorCode = json_last_error())) {
            throw new EncoderException("Json parse error, json_decode() error " . $jsonErrorCode);
        }
        return $decoded;
    }
}