<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Hydration;

use AsceticSoft\Rowcast\Mapping\NameConverter\NameConverterInterface;
use AsceticSoft\Rowcast\Mapping\NameConverter\SnakeCaseToCamelCaseConverter;
use AsceticSoft\Rowcast\Mapping\ResultSetMapping;
use AsceticSoft\Rowcast\TypeCaster\TypeCasterInterface;
use AsceticSoft\Rowcast\TypeCaster\TypeCasterRegistry;

final readonly class ReflectionHydrator implements HydratorInterface
{
    private TypeCasterInterface $typeCaster;
    private NameConverterInterface $nameConverter;

    public function __construct(
        ?TypeCasterInterface $typeCaster = null,
        ?NameConverterInterface $nameConverter = null,
    ) {
        $this->typeCaster = $typeCaster ?? TypeCasterRegistry::createDefault();
        $this->nameConverter = $nameConverter ?? new SnakeCaseToCamelCaseConverter();
    }

    public function hydrate(string $className, array $row, ?ResultSetMapping $rsm = null): object
    {
        $reflClass = new \ReflectionClass($className);
        $object = $reflClass->newInstanceWithoutConstructor();

        if ($rsm !== null) {
            $this->hydrateWithRsm($reflClass, $object, $row, $rsm);
        } else {
            $this->hydrateAuto($reflClass, $object, $row);
        }

        return $object;
    }

    public function hydrateAll(string $className, array $rows, ?ResultSetMapping $rsm = null): array
    {
        $result = [];

        foreach ($rows as $row) {
            $result[] = $this->hydrate($className, $row, $rsm);
        }

        return $result;
    }

    /**
     * Hydrate using explicit ResultSetMapping (column -> property map).
     *
     * @param \ReflectionClass<object> $reflClass
     * @param array<string, mixed>     $row
     */
    private function hydrateWithRsm(\ReflectionClass $reflClass, object $object, array $row, ResultSetMapping $rsm): void
    {
        foreach ($rsm->getFields() as $columnName => $propertyName) {
            if (!\array_key_exists($columnName, $row)) {
                continue;
            }

            $reflProperty = $reflClass->getProperty($propertyName);
            $this->setPropertyValue($reflProperty, $object, $row[$columnName]);
        }
    }

    /**
     * Hydrate using auto-discovery: reads class properties via Reflection
     * and uses NameConverter to derive column names.
     *
     * @param \ReflectionClass<object> $reflClass
     * @param array<string, mixed>     $row
     */
    private function hydrateAuto(\ReflectionClass $reflClass, object $object, array $row): void
    {
        foreach ($reflClass->getProperties() as $reflProperty) {
            $columnName = $this->nameConverter->toColumnName($reflProperty->getName());

            if (!\array_key_exists($columnName, $row)) {
                continue;
            }

            $this->setPropertyValue($reflProperty, $object, $row[$columnName]);
        }
    }

    /**
     * Set a property value on an object, applying type casting when possible.
     */
    private function setPropertyValue(\ReflectionProperty $reflProperty, object $object, mixed $value): void
    {
        $typeName = $this->resolveTypeName($reflProperty);

        if ($typeName !== null) {
            $value = $this->typeCaster->cast($value, $typeName);
        }

        $reflProperty->setValue($object, $value);
    }

    /**
     * Resolve the type name from a ReflectionProperty for use with the TypeCaster.
     *
     * Returns null for untyped or mixed properties (no casting needed).
     * Nullable types are prefixed with "?" to match TypeCasterRegistry convention.
     */
    private function resolveTypeName(\ReflectionProperty $property): ?string
    {
        $type = $property->getType();

        if ($type === null) {
            return null;
        }

        if (!$type instanceof \ReflectionNamedType) {
            // Union/intersection types are not supported — skip casting
            return null;
        }

        $name = $type->getName();

        if ($name === 'mixed') {
            return null;
        }

        if ($type->allowsNull()) {
            return '?' . $name;
        }

        return $name;
    }
}
