<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast;

use AsceticSoft\Rowcast\NameConverter\NameConverterInterface;
use AsceticSoft\Rowcast\TypeConverter\TypeConverterInterface;

final class Hydrator
{
    /** @var array<class-string, \ReflectionClass<object>> */
    private static array $reflectionClasses = [];

    /** @var array<class-string, array<string, \ReflectionProperty>> */
    private static array $reflectionProperties = [];

    public function __construct(
        private TypeConverterInterface $typeConverter,
        private NameConverterInterface $nameConverter,
        private ?PropertyMapResolver $propertyMapResolver = null,
    ) {
    }

    /**
     * @param class-string $className
     * @param array<string, mixed> $row
     */
    public function hydrate(string $className, array $row, ?Mapping $mapping = null): object
    {
        [$reflectionClass, $propertyMap, $properties] = $this->prepareMetadata($className, $mapping);
        $object = $reflectionClass->newInstanceWithoutConstructor();

        foreach ($propertyMap as $columnName => $propertyName) {
            if (!\array_key_exists($columnName, $row)) {
                continue;
            }

            $this->setProperty($object, $properties[$propertyName], $row[$columnName]);
        }

        return $object;
    }

    /**
     * @param class-string $className
     * @param list<array<string, mixed>> $rows
     * @return list<object>
     */
    public function hydrateAll(string $className, array $rows, ?Mapping $mapping = null): array
    {
        [$reflectionClass, $propertyMap, $properties] = $this->prepareMetadata($className, $mapping);
        $result = [];

        foreach ($rows as $row) {
            $object = $reflectionClass->newInstanceWithoutConstructor();

            foreach ($propertyMap as $columnName => $propertyName) {
                if (!\array_key_exists($columnName, $row)) {
                    continue;
                }

                $this->setProperty($object, $properties[$propertyName], $row[$columnName]);
            }

            $result[] = $object;
        }

        return $result;
    }

    /**
     * @param class-string $className
     * @return array{0: \ReflectionClass<object>, 1: array<string, string>, 2: array<string, \ReflectionProperty>}
     */
    private function prepareMetadata(string $className, ?Mapping $mapping): array
    {
        $reflectionClass = self::$reflectionClasses[$className] ??= new \ReflectionClass($className);
        $propertyMapResolver = $this->propertyMapResolver ?? new PropertyMapResolver();
        $propertyMap = $propertyMapResolver->resolve($mapping, $reflectionClass, $this->nameConverter);
        $properties = self::$reflectionProperties[$className] ??= $this->buildPropertyCache($reflectionClass);

        return [$reflectionClass, $propertyMap, $properties];
    }

    /**
     * @param \ReflectionClass<object> $reflectionClass
     * @return array<string, \ReflectionProperty>
     */
    private function buildPropertyCache(\ReflectionClass $reflectionClass): array
    {
        $properties = [];

        foreach ($reflectionClass->getProperties() as $property) {
            $properties[$property->getName()] = $property;
        }

        return $properties;
    }

    private function setProperty(object $object, \ReflectionProperty $property, mixed $value): void
    {
        $typeName = $this->resolveTypeName($property);
        if ($typeName !== null) {
            $value = $this->typeConverter->toPhp($value, $typeName);
        }

        $property->setValue($object, $value);
    }

    private function resolveTypeName(\ReflectionProperty $property): ?string
    {
        $type = $property->getType();
        if (!$type instanceof \ReflectionNamedType) {
            return null;
        }

        $name = $type->getName();
        if ($name === 'mixed') {
            return null;
        }

        return $type->allowsNull() ? '?' . $name : $name;
    }
}
