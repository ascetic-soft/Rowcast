<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast;

use AsceticSoft\Rowcast\NameConverter\NameConverterInterface;
use AsceticSoft\Rowcast\TypeConverter\TypeConverterInterface;

final class Extractor
{
    /** @var array<class-string, \ReflectionClass<object>> */
    private static array $reflectionClasses = [];

    /** @var array<class-string, array<string, \ReflectionProperty>> */
    private static array $reflectionProperties = [];

    public function __construct(
        private readonly NameConverterInterface $nameConverter,
        private readonly TypeConverterInterface $typeConverter,
        private readonly ?PropertyMapResolver   $propertyMapResolver = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function extract(object $dto, ?Mapping $mapping = null): array
    {
        $className = $dto::class;
        self::$reflectionClasses[$className] = new \ReflectionClass($dto);
        $reflectionClass = self::$reflectionClasses[$className];
        $result = [];
        $propertyMapResolver = $this->propertyMapResolver ?? new PropertyMapResolver();
        self::$reflectionProperties[$className] = $this->buildPropertyCache($reflectionClass);
        $properties = self::$reflectionProperties[$className];

        foreach ($propertyMapResolver->resolve($mapping, $reflectionClass, $this->nameConverter) as $columnName => $propertyName) {
            $property = $properties[$propertyName];
            if (!$property->isInitialized($dto)) {
                continue;
            }

            $result[$columnName] = $this->typeConverter->toDb($property->getValue($dto));
        }

        return $result;
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
}
