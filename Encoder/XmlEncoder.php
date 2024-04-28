<?php

namespace Matryoshka\Serializer\Encoder;

class XmlEncoder implements EncoderInterface
{

    //ToDo Доделать кодирование в xml
    public function encode(array $data, array $context = [], ?string $namespace = null): string
    {
        return '';
    }


    /**
     * Декодирует xml строку в ассоциативный массив
     *
     * На данный момент игнорируются аттрибуты тегов xml
     *
     * @throws EncoderException
     */
    public function decode(string $data, array $context = []): array
    {
        if (function_exists('libxml_use_internal_errors')) {
            libxml_use_internal_errors();
        }
        $xml = simplexml_load_string($data);
        if (!$xml) {
            if (function_exists('libxml_get_last_error')) {
                $error = libxml_get_last_error();
                throw new EncoderException($error->message);
            } else {
                throw new EncoderException("Wrong xml format");
            }
        }

        $decoded = json_decode(json_encode($xml, JSON_UNESCAPED_UNICODE), true);
        return $this->simplifyNestedArray($decoded);
    }

    private function simplifyNestedArray(array $data): array
    {
        foreach ($data as &$value) {
            if (is_array($value)) {
                if (array_key_exists('@attributes', $value)) {
                    unset($value['@attributes']);
                }
                if (1 === count($value)) {
                    if (is_array($val = array_shift($value))) {
                        $value = $val;
                    } else {
                        array_unshift($value, $val);
                    }
                }
                $value = $this->simplifyNestedArray($value);
            }
        }
        return $data;
    }
}