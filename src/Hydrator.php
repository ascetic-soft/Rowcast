<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast;

use AsceticSoft\Rowcast\NameConverter\NameConverterInterface;
use AsceticSoft\Rowcast\TypeConverter\TypeConverterInterface;

final readonly class Hydrator
{
    public function __construct(
        private TypeConverterInterface $typeConverter,
        private NameConverterInterface $nameConverter,
    ) {
    }

    /**
     * @param class-string $className
     * @param array<string, mixed> $row
     */
    public function hydrate(string $className, array $row, ?Mapping $mapping = null): object
    {
        $reflectionClass = new \ReflectionClass($className);
        $object = $reflectionClass->newInstanceWithoutConstructor();

        foreach (Mapping::resolvePropertiesFor($mapping, $reflectionClass, $this->nameConverter) as $columnName => $propertyName) {
            if (!\array_key_exists($columnName, $row)) {
                continue;
            }

            $property = $reflectionClass->getProperty($propertyName);
            $this->setProperty($object, $property, $row[$columnName]);
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
        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrate($className, $row, $mapping);
        }

        return $result;
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
