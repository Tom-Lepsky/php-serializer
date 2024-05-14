<?php

namespace AdAstra\Serializer\Normalizer;

use AdAstra\Serializer\Accessor\{AccessorException, PropertyAccessor, PropertyMetaData};
use ArrayAccess, Iterator, ReflectionClass, ReflectionProperty, ReflectionEnum, ReflectionException, Throwable, UnitEnum;
use AdAstra\Serializer\Attribute\{ArrayType,
    DenormalizationContext,
    Groups,
    Ignore,
    Name,
    NormalizationContext
};

/**
 * Преобразовывает объект в ассоциативный массив и обратно
 * Порядок получения доступа к свойствам:
 *  - Если у свойства (независимо от модификатора доступа свойства) есть метод сеттер/геттер (методы, начинающиеся с set или get,
 * далее следует с заглавной буквы название свойства) и у него модификатор доступа 'public', то вызывается этот метод
 *  - Если сеттер/геттер отсутствует и свойство имеет модификатор 'public', то значение свойства берётся/устанавливается напрямую,
 *    свойства с модификаторами 'protected' и 'private' напрямую по умолчанию не устанавливаются или не извлекаются. Если
 *    необходимо напрямую иметь доступ к таким свойствам необходимо передать в контекст ObjectNormalizer::INCLUDE_PROTECTED
 *    и ObjectNormalizer::INCLUDE_PRIVATE
 *  - Не инициализированные свойства пропускаются
 *
 * @implements NormalizerInterface<object|null>
 */
class ObjectNormalizer implements NormalizerInterface
{
    protected array $objHash = [];
    protected int $counter = 0;

    protected bool $strictTypes = false;

    protected PropertyAccessor $accessor;

    final public const INCLUDE_PROTECTED        = 1;
    final public const INCLUDE_PRIVATE          = 2;

    /**
     * Ключи с точечной нотацией будут преобразованы во вложенный массив
     * #[Name('data.name')]
     * public string $name = 'Tom';
     *
     * вывод:
     * Array
     * (
     *      [data] => Array
     *          (
     *              [name] => 'Tom'
     *          )
     * )
     */
    final public const SIMPLIFY_COMPOSITE_KEYS = 4;

    /**
     * Поля со значением NULL будут исключены из результата нормализации
     */
    final public const SKIP_NULL_VALUES = 8;

    /**
     * По умолчанию происходит приведение типов для int, float, string, при передаче в контекст этой константы
     * при несовпадении типов при денормализации будет выброшено исключение
     */
    final public const STRICT_TYPES = 16;

    final public const DEPTH = 'depth';

    public function __construct()
    {
        $this->accessor = new PropertyAccessor();
    }

    /**
     * Преобразование объекта в ассоциативный массив, порядок @see ObjectNormalizer
     * Для управления преобразованием используются аттрибуты
     * Не инициализированные свойства пропускаются
     * Объект с рекурсивной ссылкой (на самого себя) преобразуется в null
     * Примеры:
     *
     * class A
     * {
     *      public int $number;
     *      private string $name;
     *      public A $object;
     *      public array $array;
     *
     *      public function getName(): string
     *      {
     *          return $this->name;
     *      }
     *
     *      public function setName(string $name): void
     *      {
     *          $this->name = 'my name ' . $name;
     *      }
     *  }
     *
     * $a = new A();
     * $a->number = 12;
     * $a->setName('Tom');
     * $a1 = new A();
     * $a1->number = 21;
     * $a->object = $a1;
     * $a->array = [
     *      'obj1' => $a1,
     *      'obj2' => $a1,
     * ];
     *
     * вывод:
     * Array
     * (
     *  [number] => 12
     *  [name] => my name Tom
     *  [object] => Array
     *      (
     *          [number] => 21
     *      )
     *  [array] => Array
     *      (
     *          [obj1] => Array
     *              (
     *                  [number] => 21
     *              )
     *          [obj2] => Array
     *              (
     *                  [number] => 21
     *              )
     *      )
     *  )
     *
     * Аттрибут @see Name Изменяет название нормализуемого свойства
     *
     * #[Name('order_number')]
     * public int $number;
     *
     * вывод:
     * [order_number] => 12
     *
     * Аттрибут @see Ignore Игнорирует свойство, вне зависимости от его модификатора и наличия геттера
     *
     * Аттрибут @see Groups Позволяет указывать группы нормализации для гибкого указания только тех свойств,
     * которые необходимо нормализовать. Для работы с группами необходимо передать массив с названиями групп в $groups
     *
     * ...
     * #[Groups(['my_group'])]
     * public int $number;
     *
     * #[Groups(['my_group', 'another_group'])]
     * private string $name;
     *
     * public A $object;
     * ...
     *
     * $result = $normalizer->normalize($a, ['my_group']);
     * вывод:
     * Array
     *  (
     *      [number] => 12
     *      [name] => my name Tom
     *  )
     *
     * Мы передали в нормализатор группу 'my_group' и нормализовались свойства только у которых в аттрибуте была 'my_group'.
     *
     * $result = $normalizer->normalize($a, ['another_group']);
     * вывод:
     * Array
     *  (
     *      [name] => my name Tom
     *  )
     *
     * Есть специальная группа '*' при передачи её в нормализатор будут нормализовываться все свойства,
     * у которых есть хотя бы одна любая группа.
     * Так же группу '*' можно установить на свойстве, в этом случае оно будет нормализовано,
     * если в нормализатор передана хотя бы одна любая группа.
     * Аттрибут Groups учитывается только если передан не пустой массив Groups
     *
     *
     * @inheritDoc
     */
    public function normalize(object $object, array $groups = [], array $context = []): array
    {
        if (in_array(spl_object_hash($object), $this->objHash) || (isset($context[self::DEPTH]) && $context[self::DEPTH] <= $this->counter)) {
            return [];
        }
        $this->counter++;
        $reflectionObject = new ReflectionClass($object);
        $normalizedObject = [];
        do {
            foreach ($reflectionObject->getProperties($filter ?? null) as $property) {
                $this->objHash[] = spl_object_hash($object);
                try {
                    $propertyMetaData = new PropertyMetaData($object, $property->getName());
                    $propertyMetaData->attributes = $propertyMetaData->attributes[NormalizationContext::class] ?? $propertyMetaData->attributes;

                    if ($this->skipProperty($reflectionObject, $groups, $propertyMetaData->attributes)) {
                        array_pop($this->objHash);
                        continue;
                    }

                    $propertyMetaData->value = $this->accessor->getValue($object, $property, $context);
                    $propertyMetaData = $this->processAttributes($propertyMetaData);

                    $propertyMetaData->value = $this->normalizeValue($propertyMetaData->value, $groups, $context);

                    if (in_array(self::SKIP_NULL_VALUES, $context)) {
                        if (!$propertyMetaData->isNullable() && null === $propertyMetaData->value) {
                            continue;
                        } elseif (is_array($propertyMetaData->value) && !$propertyMetaData->isObject()) {
                            $propertyMetaData->value = $this->skipNullValues($propertyMetaData->value);
                        }
                    }
                } catch (Throwable) {
                    array_pop($this->objHash);
                    continue;
                }

                array_pop($this->objHash);
                $normalizedObject[$propertyMetaData->name] = $propertyMetaData->value;
            }

            $filter = ReflectionProperty::IS_PRIVATE;
        } while ($reflectionObject = $reflectionObject->getParentClass());

        if (0 === --$this->counter) {
            if (in_array(self::SIMPLIFY_COMPOSITE_KEYS, $context)) {
                $normalizedObject = $this->simplifyCompositeKeys($normalizedObject);
            }

            $this->objHash = [];
        }
        return $normalizedObject;
    }

    /**
     * Преобразование строки в объект, работает по аналогии с @see ObjectNormalizer::normalize() ,
     * но в обратном порядке
     *
     * Аттрибут @see ArrayType При передаче в него названия класса(вместе с пространством имён) позволяет
     * денормализовать ассоциативный массив в массив объектов, при условии, что такой класс существует
     *
     * Остальные аттрибуты и группы работают аналогично @see ObjectNormalizer::normalize()
     *
     * @inheritDoc
     */
    public function denormalize(array $data, string $className, array $groups = [], array $context = []): ?object
    {
        try {
            $reflectionObject = new ReflectionClass($className);
            if (!$reflectionObject->isInstantiable()) {
                return null;
            }
            $object = $reflectionObject->newInstance();
        } catch (Throwable) {
            return null;
        }

        $this->strictTypes = in_array(self::STRICT_TYPES, $context);

        do {
            foreach ($reflectionObject->getProperties($filter ?? null) as $property) {
                try {
                    $propertyMetaData = new PropertyMetaData($object, $property->getName());
                    $propertyMetaData->attributes = $propertyMetaData->attributes[DenormalizationContext::class] ?? $propertyMetaData->attributes;

                    if ($this->skipProperty($reflectionObject, $groups, $propertyMetaData->attributes)) {
                        continue;
                    }

                    $propertyMetaData = $this->processAttributes($propertyMetaData);
                    $propertyMetaData->value = $this->extractValue($data, $propertyMetaData->name, $context);
                    $value = $this->denormalizeValue($propertyMetaData, $groups, $context);
                    $this->accessor->setValue($value, $object, $property, $context);
                } catch (Throwable) {
                    continue;
                }
            }

            $filter = ReflectionProperty::IS_PRIVATE;
        } while ($reflectionObject = $reflectionObject->getParentClass());

        return $object;
    }

    /**
     * @throws ReflectionException
     */
    protected function normalizeValue(mixed $value, array $groups = [], array $context = []): mixed
    {
         if (is_array($value) || (is_a($value, ArrayAccess::class) && is_a($value, Iterator::class))) {
            $items = [];
            foreach ($value as $key => $item) {
                $items[$key] = $this->normalizeValue($item, $groups, $context);
            }
            return $items;
        } elseif (is_object($value)) {
            if (in_array(spl_object_hash($value), $this->objHash)) {
                return null;
            }
            if ($value instanceof UnitEnum) {
                return (new ReflectionEnum($value))->isBacked() ? $value->value : $value->name;
            } else {
                $normalizedObj = $this->normalize($value, $groups, $context);
                return empty($normalizedObj) ? null : $normalizedObj;
            }
        }
        return $value;
    }

    /**
     * @throws ReflectionException
     * @throws AccessorException
     */
    protected function denormalizeValue(PropertyMetaData $propertyMetaData, array $groups = [], array $context = []): mixed
    {
        if ($propertyMetaData->isArrayOfObjects()) {
            $array = $propertyMetaData->isObjectAsArray() ? $propertyMetaData->getObjectArrayInstance() : [];
            foreach ($propertyMetaData->value as $items) {
                if (is_array($items)) {
                    $array[] = $this->denormalize($items, $propertyMetaData->arrayType, $groups, $context);
                }
            }
            return $array;
        } elseif ($propertyMetaData->isObject()) {
            if ($propertyMetaData->isEnum()) {
                $reflector = new ReflectionEnum($propertyMetaData->type);
                if ($reflector->isBacked()) {
                    foreach ($reflector->getCases() as $reflectionCase) {
                        $case = $reflectionCase->getValue();
                        if ($this->strictTypes ? $propertyMetaData->value === $case->value : $propertyMetaData->value == $case->value) {
                            return $case;
                        }
                    }
                    return null;
                } elseif ($reflector->hasCase($propertyMetaData->value)) {
                    return $reflector->getCase($propertyMetaData->value)->getValue();
                } else {
                    return null;
                }
            } elseif ($propertyMetaData->isInternal()) {
                return $propertyMetaData->value;
            } else {
                return $this->denormalize($propertyMetaData->value, $propertyMetaData->type, $groups, $context);
            }
        } else {
            return $propertyMetaData->value;
        }
    }

    protected function processAttributes(PropertyMetaData $propertyMetaData): PropertyMetaData
    {
        foreach ($propertyMetaData->attributes as $name => $args) {
            switch ($name) {
                case Name::class:
                    $propertyMetaData->name = $args['name'];
                    break;
                case ArrayType::class:
                    $propertyMetaData->arrayType = $args['type'];
                    break;
            }
        }

        return $propertyMetaData;
    }

    /**
     * @throws NotFoundValue
     */
    protected function extractValue(array $data, string $key, array $context = []): mixed
    {
        if (in_array(self::SIMPLIFY_COMPOSITE_KEYS, $context)) {
            $compositeKey = explode('.', $key, 2);
            if (count($compositeKey) > 1 && array_key_exists($compositeKey[0], $data) && is_array($data[$compositeKey[0]])) {
                return $this->extractValue($data[$compositeKey[0]], $compositeKey[1], $context);
            }
        }
        return array_key_exists($key, $data) ? $data[$key] : throw new NotFoundValue("Value for key $key not found");
    }

    protected function grouped(array $groups, array $propertyGroups): bool
    {
        if (empty($groups)) {
            return true;
        }

        if (count(array_intersect($groups, $propertyGroups)) > 0) {
            return true;
        }

        if (in_array('*', $groups) && !empty($propertyGroups)) {
            return true;
        }

        if (in_array('*', $propertyGroups)) {
            return true;
        }

        return false;
    }

    protected function isIgnored(array $attrs): bool
    {
        return array_key_exists(Ignore::class, $attrs);
    }

    protected function skipProperty(ReflectionClass $reflectionObject, array $groups, array $attributes): bool
    {
        return (!$this->grouped($groups, $attributes[Groups::class]['groups'] ?? []) && !$reflectionObject->isInternal()) || $this->isIgnored($attributes);
    }

    protected function simplifyCompositeKeys(array $data): array
    {
        foreach ($data as $key => &$value) {
            $compositeKey = explode('@', $key, 2);
            $compositeKey[0] = explode('.', $compositeKey[0], 2);
            $realKey = $compositeKey[0][0];
            if (isset($compositeKey[0][1])) {
                $compositePart = $compositeKey[0][1];
                if (isset($compositeKey[1])) {
                    $compositePart .= '@' . $compositeKey[1];
                }
                if (isset($data[$realKey]) && !is_array($data[$realKey])) {
                    $value = [$data[$realKey], $compositePart => $value];
                    $data[$realKey] = $value;
                } else {
                    $data[$realKey][$compositePart] = $value;
                }

                unset($data[$key]);
            }
            if (isset($data[$realKey]) && is_array($data[$realKey])) {
                $data[$realKey] = $this->simplifyCompositeKeys($data[$realKey]);
            }
        }
        return $data;
    }

    protected function skipNullValues(array $data): array
    {
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $value = $this->skipNullValues($value);
            } elseif (null === $value) {
                unset($data[$key]);
            }
        }
        return $data;
    }
}