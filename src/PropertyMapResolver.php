<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast;

use AsceticSoft\Rowcast\NameConverter\NameConverterInterface;

final readonly class PropertyMapResolver
{
    /**
     * @param \ReflectionClass<object> $reflectionClass
     * @return array<string, string> column => property
     */
    public function resolve(
        ?Mapping $mapping,
        \ReflectionClass $reflectionClass,
        NameConverterInterface $nameConverter,
    ): array {
        $result = [];
        if ($mapping !== null && !$mapping->isAutoDiscover()) {
            foreach ($mapping->getColumns() as $columnName => $propertyName) {
                if ($mapping->isIgnored($propertyName) || !$reflectionClass->hasProperty($propertyName)) {
                    continue;
                }

                $result[$columnName] = $propertyName;
            }

            return $result;
        }

        foreach ($reflectionClass->getProperties() as $property) {
            $propertyName = $property->getName();
            if ($mapping?->isIgnored($propertyName) === true) {
                continue;
            }

            $columnName = $mapping?->getColumnForProperty($propertyName)
                ?? $nameConverter->toColumnName($propertyName);
            $result[$columnName] = $propertyName;
        }

        return $result;
    }
}
