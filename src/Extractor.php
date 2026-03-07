<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast;

use AsceticSoft\Rowcast\NameConverter\NameConverterInterface;
use AsceticSoft\Rowcast\NameConverter\SnakeCaseToCamelCase;
use AsceticSoft\Rowcast\TypeConverter\TypeConverterInterface;
use AsceticSoft\Rowcast\TypeConverter\TypeConverterRegistry;

final readonly class Extractor
{
    private NameConverterInterface $nameConverter;
    private TypeConverterInterface $typeConverter;

    public function __construct(
        ?NameConverterInterface $nameConverter = null,
        ?TypeConverterInterface $typeConverter = null,
    ) {
        $this->nameConverter = $nameConverter ?? new SnakeCaseToCamelCase();
        $this->typeConverter = $typeConverter ?? TypeConverterRegistry::defaults();
    }

    /**
     * @return array<string, mixed>
     */
    public function extract(object $dto, ?Mapping $mapping = null): array
    {
        $reflectionClass = new \ReflectionClass($dto);
        $result = [];

        if ($mapping !== null && !$mapping->isAutoDiscover()) {
            foreach ($mapping->getColumns() as $columnName => $propertyName) {
                if ($mapping->isIgnored($propertyName) || !$reflectionClass->hasProperty($propertyName)) {
                    continue;
                }

                $property = $reflectionClass->getProperty($propertyName);
                if (!$property->isInitialized($dto)) {
                    continue;
                }

                $result[$columnName] = $this->typeConverter->toDb($property->getValue($dto));
            }

            return $result;
        }

        foreach ($reflectionClass->getProperties() as $property) {
            if (!$property->isInitialized($dto)) {
                continue;
            }

            $propertyName = $property->getName();
            if ($mapping?->isIgnored($propertyName) === true) {
                continue;
            }

            $columnName = $mapping?->getColumnForProperty($propertyName)
                ?? $this->nameConverter->toColumnName($propertyName);

            $result[$columnName] = $this->typeConverter->toDb($property->getValue($dto));
        }

        return $result;
    }
}
