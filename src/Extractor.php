<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast;

use AsceticSoft\Rowcast\NameConverter\NameConverterInterface;
use AsceticSoft\Rowcast\TypeConverter\TypeConverterInterface;

final readonly class Extractor
{
    public function __construct(
        private NameConverterInterface $nameConverter,
        private TypeConverterInterface $typeConverter,
        private ?PropertyMapResolver $propertyMapResolver = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function extract(object $dto, ?Mapping $mapping = null): array
    {
        $reflectionClass = new \ReflectionClass($dto);
        $result = [];
        $propertyMapResolver = $this->propertyMapResolver ?? new PropertyMapResolver();

        foreach ($propertyMapResolver->resolve($mapping, $reflectionClass, $this->nameConverter) as $columnName => $propertyName) {
            $property = $reflectionClass->getProperty($propertyName);
            if (!$property->isInitialized($dto)) {
                continue;
            }

            $result[$columnName] = $this->typeConverter->toDb($property->getValue($dto));
        }

        return $result;
    }
}
