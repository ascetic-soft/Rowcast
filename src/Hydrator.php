<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast;

use AsceticSoft\Rowcast\NameConverter\NameConverterInterface;
use AsceticSoft\Rowcast\NameConverter\SnakeCaseToCamelCase;
use AsceticSoft\Rowcast\TypeConverter\TypeConverterInterface;
use AsceticSoft\Rowcast\TypeConverter\TypeConverterRegistry;

final readonly class Hydrator
{
    private TypeConverterInterface $typeConverter;
    private NameConverterInterface $nameConverter;

    public function __construct(
        ?TypeConverterInterface $typeConverter = null,
        ?NameConverterInterface $nameConverter = null,
    ) {
        $this->typeConverter = $typeConverter ?? TypeConverterRegistry::defaults();
        $this->nameConverter = $nameConverter ?? new SnakeCaseToCamelCase();
    }

    /**
     * @param class-string $className
     * @param array<string, mixed> $row
     */
    public function hydrate(string $className, array $row, ?Mapping $mapping = null): object
    {
        $reflectionClass = new \ReflectionClass($className);
        $object = $reflectionClass->newInstanceWithoutConstructor();

        if ($mapping !== null && !$mapping->isAutoDiscover()) {
            foreach ($mapping->getColumns() as $columnName => $propertyName) {
                if (!\array_key_exists($columnName, $row) || $mapping->isIgnored($propertyName)) {
                    continue;
                }

                $property = $reflectionClass->getProperty($propertyName);
                $this->setProperty($object, $property, $row[$columnName]);
            }

            return $object;
        }

        foreach ($row as $columnName => $value) {
            $propertyName = $mapping?->getPropertyForColumn($columnName)
                ?? $this->nameConverter->toPropertyName($columnName);

            if ($mapping?->isIgnored($propertyName) === true) {
                continue;
            }

            if (!$reflectionClass->hasProperty($propertyName)) {
                continue;
            }

            $property = $reflectionClass->getProperty($propertyName);
            $this->setProperty($object, $property, $value);
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
