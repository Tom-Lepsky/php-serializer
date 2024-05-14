<?php

namespace AdAstra\Serializer\Encoder;

class FormUrlEncoded implements EncoderInterface
{
    public final const DISABLE_URL_ENCODING = 1;
    public final const RAW_DECODING = 2;

    public function encode(array $data, array $context = []): string
    {
        if (isset($context['encoder']) && in_array(self::DISABLE_URL_ENCODING, $context['encoder'])) {
            $data = $this->flat($data);
            $params = [];
            foreach ($data as $key => $value) {
                $params[] = "$key=$value";
            }
            return implode('&', $params);
        }
        return http_build_query($data, arg_separator: '&');
    }

    public function decode(string $data, array $context = []): array
    {
        if (isset($context['encoder']) && in_array(self::RAW_DECODING, $context['encoder'])) {
            $decoded = rawurldecode($data);
        } else {
            $decoded = urldecode($data);
        }
        parse_str($decoded, $result);
        return $result;
    }

    protected function flat(array $data, bool $root = true): array
    {
        $flat = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $datum = $this->flat($value, false);
                foreach ($datum as $k => $v) {
                    $flat[$root ? "$key$k" : "[$key]$k"] = $v;
                }
            } else {
                $flat[$root ? "$key" : "[$key]"] = $value;
            }
        }
        return $flat;
    }
}